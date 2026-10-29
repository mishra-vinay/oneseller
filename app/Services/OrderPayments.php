<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Activity;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class OrderPayments
{
    /**
     * Payment for online orders stays reserved this long before the order is cancelled and stock returned.
     */
    public const PAYMENT_WINDOW_MINUTES = 30;

    public function __construct(private OrderEvents $events) {}

    /**
     * Marks an online payment captured. Safe to call twice (browser callback and webhook).
     *
     * @param  array<string, mixed>  $payload
     */
    public function markPaid(Payment $payment, string $gatewayPaymentId, array $payload = []): bool
    {
        $becamePaid = DB::transaction(function () use ($payment, $gatewayPaymentId, $payload) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

            if ($order->payment_status === PaymentStatus::Paid) {
                return false;
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                // Paid after the order expired: record it so an admin can refund.
                $payment->update(['status' => 'captured_late', 'gateway_payment_id' => $gatewayPaymentId, 'payload' => $payload]);
                Activity::record('payment.late', "Payment received after order {$order->number} expired. Refund needed.", $order);

                return false;
            }

            $payment->update(['status' => 'captured', 'gateway_payment_id' => $gatewayPaymentId, 'payload' => $payload]);

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Placed,
                'placed_at' => now(),
            ]);

            $order->subOrders()->where('status', SubOrderStatus::PendingPayment)->update(['status' => SubOrderStatus::Placed]);

            return true;
        });

        if ($becamePaid) {
            $this->events->placed($payment->order->fresh());
        }

        return $becamePaid;
    }

    /**
     * Cancels online orders whose payment never arrived and returns their stock.
     */
    public function expireUnpaid(): int
    {
        $expired = 0;

        Order::where('status', OrderStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES))
            ->pluck('id')
            ->each(function (int $id) use (&$expired) {
                DB::transaction(function () use ($id, &$expired) {
                    $order = Order::whereKey($id)->lockForUpdate()->first();

                    if ($order->status !== OrderStatus::PendingPayment) {
                        return;
                    }

                    foreach ($order->subOrders()->with('items.variant')->get() as $subOrder) {
                        foreach ($subOrder->items as $item) {
                            $item->variant?->increment('stock', $item->quantity);
                        }

                        $subOrder->update(['status' => SubOrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Payment not completed', 'cancelled_by' => 'system']);
                    }

                    $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Failed]);
                    $order->payments()->where('status', 'created')->update(['status' => 'expired']);
                    $expired++;
                });
            });

        return $expired;
    }
}

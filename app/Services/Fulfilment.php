<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubOrderStatus;
use App\Models\Activity;
use App\Models\Order;
use App\Models\SubOrder;
use App\Notifications\SubOrderCancelled;
use App\Notifications\SubOrderDelivered;
use App\Notifications\SubOrderShipped;
use App\Support\AdminNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A seller's sub-order moves placed → accepted → shipped → delivered, or is cancelled
 * before it ships. Each seller moves at their own pace; the parent order is complete
 * once every remaining sub-order is delivered.
 */
class Fulfilment
{
    public function __construct(private Ledger $ledger) {}

    public function accept(SubOrder $subOrder): void
    {
        $this->transition($subOrder, [SubOrderStatus::Placed], SubOrderStatus::Accepted, ['accepted_at' => now()]);
    }

    public function ship(SubOrder $subOrder, string $courier, string $trackingNumber): void
    {
        $this->transition($subOrder, [SubOrderStatus::Accepted], SubOrderStatus::Shipped, [
            'shipped_at' => now(),
            'courier' => $courier,
            'tracking_number' => $trackingNumber,
        ]);

        $subOrder->order->buyer->notify(new SubOrderShipped($subOrder));
    }

    public function deliver(SubOrder $subOrder): void
    {
        DB::transaction(function () use ($subOrder) {
            $this->transition($subOrder, [SubOrderStatus::Shipped], SubOrderStatus::Delivered, ['delivered_at' => now()]);
            $this->ledger->creditDelivery($subOrder, $subOrder->order->payment_method === PaymentMethod::Cod);
            $this->refreshOrder($subOrder->order);
        });

        $subOrder->order->buyer->notify(new SubOrderDelivered($subOrder));
    }

    /**
     * Cancels one seller's portion only. Stock is returned. Sellers are credited on delivery,
     * and only unshipped portions can be cancelled, so there is never a credit to reverse.
     */
    public function cancel(SubOrder $subOrder, string $reason, Role $by): void
    {
        DB::transaction(function () use ($subOrder, $reason, $by) {
            $locked = SubOrder::whereKey($subOrder->id)->lockForUpdate()->first();

            if (! $locked->status->canBeCancelled()) {
                throw ValidationException::withMessages(['reason' => __('This portion can no longer be cancelled.')]);
            }

            foreach ($locked->items()->with('variant')->get() as $item) {
                $item->variant?->increment('stock', $item->quantity);
            }

            $locked->update([
                'status' => SubOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $by->value,
            ]);

            $subOrder->setRawAttributes($locked->getAttributes(), true);
            $this->refreshOrder($subOrder->order);
        });

        Activity::record('order.cancelled', "Portion {$subOrder->number} cancelled by {$by->value}: {$reason}", $subOrder->order);

        $notification = new SubOrderCancelled($subOrder);
        $subOrder->order->buyer->notify($notification);
        $subOrder->store->user->notify($notification);
        AdminNotifier::send($notification);
    }

    /**
     * Admin records that an online payment for a cancelled portion was refunded (outside the system).
     */
    public function markRefunded(SubOrder $subOrder): void
    {
        $order = $subOrder->order;

        if ($subOrder->status !== SubOrderStatus::Cancelled || $subOrder->refunded_at
            || ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            throw ValidationException::withMessages(['reason' => __('Only a cancelled portion of a paid order can be marked refunded.')]);
        }

        DB::transaction(function () use ($subOrder, $order) {
            $subOrder->update(['refunded_at' => now()]);

            $refunded = $order->refunded_total + $subOrder->total();
            $order->update([
                'refunded_total' => $refunded,
                'payment_status' => $refunded >= $order->grand_total ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);
        });

        Activity::record('order.refunded', "Refund recorded for {$subOrder->number}", $order);
    }

    /**
     * @param  array<int, SubOrderStatus>  $from
     * @param  array<string, mixed>  $changes
     */
    private function transition(SubOrder $subOrder, array $from, SubOrderStatus $to, array $changes): void
    {
        DB::transaction(function () use ($subOrder, $from, $to, $changes) {
            $locked = SubOrder::whereKey($subOrder->id)->lockForUpdate()->first();

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => __('This order is :status and cannot be changed that way.', ['status' => strtolower($locked->status->label())])]);
            }

            $locked->update(['status' => $to, ...$changes]);
            $subOrder->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function refreshOrder(Order $order): void
    {
        $statuses = $order->subOrders()->pluck('status')->map(fn ($s) => $s instanceof SubOrderStatus ? $s : SubOrderStatus::from($s));
        $remaining = $statuses->reject(fn ($s) => $s === SubOrderStatus::Cancelled);

        if ($remaining->isEmpty()) {
            $order->update(['status' => OrderStatus::Cancelled]);

            return;
        }

        if ($remaining->every(fn ($s) => $s === SubOrderStatus::Delivered)) {
            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
                // Cash on Delivery is fully collected once everything is delivered.
                'payment_status' => $order->payment_method === PaymentMethod::Cod ? PaymentStatus::Paid : $order->payment_status,
            ]);
        }
    }
}

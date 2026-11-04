<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Activity;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SubOrder;
use App\Services\Ledger;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Automatic split settlement with Razorpay Route.
 *
 * When an online payment is captured, each seller with a linked account gets a transfer of
 * their net payout, held by Razorpay. Delivery releases the hold (and the ledger records the
 * payout); cancelling the parcel reverses it. Cash on Delivery and sellers without a linked
 * account keep using manual payouts from the ledger.
 *
 * Any Razorpay error is logged and leaves that parcel on manual payout; it never blocks the order.
 */
class RouteSettlement
{
    private const API = 'https://api.razorpay.com/v1';

    public function __construct(private Settings $settings, private RazorpayGateway $razorpay, private Ledger $ledger) {}

    public function enabled(): bool
    {
        return $this->settings->get('route_settlement_enabled') && $this->razorpay->isConfigured();
    }

    public function createTransfers(Order $order, Payment $payment): void
    {
        if (! $this->enabled() || $order->payment_method !== PaymentMethod::Online || ! $payment->gateway_payment_id) {
            return;
        }

        $parcels = $order->subOrders()->with('store')->get()->filter(fn (SubOrder $s) => filled($s->store->razorpay_account_id));

        if ($parcels->isEmpty()) {
            return;
        }

        // Transfers cannot exceed what was paid (possible when a large coupon beats the commission).
        if ($parcels->sum('net_payout') > $payment->amount) {
            Activity::record('route.skipped', "Automatic payout skipped for {$order->number}: seller shares exceed the payment. Pay out manually.", $order);

            return;
        }

        $response = $this->client()->post(self::API."/payments/{$payment->gateway_payment_id}/transfers", [
            'transfers' => $parcels->map(fn (SubOrder $s) => [
                'account' => $s->store->razorpay_account_id,
                'amount' => $s->net_payout,
                'currency' => 'INR',
                'on_hold' => 1,
                'notes' => ['sub_order' => $s->number],
            ])->values()->all(),
        ]);

        if (! $response->successful()) {
            $parcels->each(fn (SubOrder $s) => $s->forceFill(['transfer_status' => 'failed'])->save());
            $this->failed($order, 'create', $response->body());

            return;
        }

        foreach ($response->json('items', []) as $transfer) {
            $number = $transfer['notes']['sub_order'] ?? null;
            $parcels->firstWhere('number', $number)?->forceFill(['razorpay_transfer_id' => $transfer['id'], 'transfer_status' => 'on_hold'])->save();
        }
    }

    /**
     * Called after delivery: the seller is paid, so the ledger records the payout.
     */
    public function release(SubOrder $subOrder): void
    {
        if ($subOrder->transfer_status !== 'on_hold' || ! $this->razorpay->isConfigured()) {
            return;
        }

        $response = $this->client()->patch(self::API."/transfers/{$subOrder->razorpay_transfer_id}", ['on_hold' => 0]);

        if (! $response->successful()) {
            $this->failed($subOrder->order, 'release', $response->body());

            return;
        }

        $subOrder->forceFill(['transfer_status' => 'released'])->save();
        $this->ledger->recordPayout($subOrder->store, $subOrder->net_payout, "Razorpay Route transfer {$subOrder->razorpay_transfer_id} for {$subOrder->number}");
    }

    /**
     * Called when a parcel is cancelled before delivery: the held money returns to the platform.
     */
    public function reverse(SubOrder $subOrder): void
    {
        if ($subOrder->transfer_status !== 'on_hold' || ! $this->razorpay->isConfigured()) {
            return;
        }

        $response = $this->client()->post(self::API."/transfers/{$subOrder->razorpay_transfer_id}/reversals", ['amount' => $subOrder->net_payout]);

        if (! $response->successful()) {
            $this->failed($subOrder->order, 'reverse', $response->body());

            return;
        }

        $subOrder->forceFill(['transfer_status' => 'reversed'])->save();
    }

    private function client()
    {
        return Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))->acceptJson()->timeout(15);
    }

    private function failed(Order $order, string $step, string $body): void
    {
        Log::error("Razorpay Route {$step} failed", ['order' => $order->number, 'response' => $body]);
        Activity::record('route.failed', "Automatic payout ({$step}) failed for {$order->number}. Check the Razorpay dashboard and pay out manually if needed.", $order);
    }
}

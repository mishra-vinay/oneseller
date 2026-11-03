<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Enums\SubOrderStatus;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\ReturnRequested;
use App\Notifications\ReturnUpdated;
use App\Support\AdminNotifier;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns and replacements for delivered items:
 * requested → approved | rejected; approved → received (optionally restocked);
 * received → refunded (admin pays the buyer back) | replaced (seller ships a new one).
 */
class Returns
{
    public function __construct(private Settings $settings, private Ledger $ledger) {}

    public function windowOpen(SubOrder $subOrder): bool
    {
        return $subOrder->status === SubOrderStatus::Delivered
            && $subOrder->delivered_at
            && $subOrder->delivered_at->copy()->addDays($this->settings->get('return_window_days'))->isFuture();
    }

    /**
     * Items still returnable, with remaining_quantity set (ordered minus any non-rejected returns).
     *
     * @return Collection<int, OrderItem>
     */
    public function returnableItems(SubOrder $subOrder): Collection
    {
        $taken = DB::table('return_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_items.return_request_id')
            ->where('return_requests.sub_order_id', $subOrder->id)
            ->where('return_requests.status', '!=', ReturnStatus::Rejected->value)
            ->groupBy('return_items.order_item_id')
            ->selectRaw('return_items.order_item_id, sum(return_items.quantity) as taken')
            ->pluck('taken', 'order_item_id');

        return $subOrder->items
            ->each(fn (OrderItem $item) => $item->setAttribute('remaining_quantity', $item->quantity - (int) ($taken[$item->id] ?? 0)))
            ->filter(fn (OrderItem $item) => $item->remaining_quantity > 0)
            ->values();
    }

    /**
     * @param  array<int, int>  $quantities  order item id => quantity to return
     */
    public function request(User $buyer, SubOrder $subOrder, array $quantities, ReturnType $type, string $reason, ?string $details): ReturnRequest
    {
        abort_unless($subOrder->order->buyer_id === $buyer->id, 404);

        if (! $this->windowOpen($subOrder)) {
            throw ValidationException::withMessages(['items' => __('Returns for this parcel are closed. They can be requested within :days days of delivery.', ['days' => $this->settings->get('return_window_days')])]);
        }

        $return = DB::transaction(function () use ($buyer, $subOrder, $quantities, $type, $reason, $details) {
            SubOrder::whereKey($subOrder->id)->lockForUpdate()->first();
            $returnable = $this->returnableItems($subOrder->load('items'))->keyBy('id');
            $lines = collect($quantities)->map(fn ($q) => (int) $q)->filter(fn ($q) => $q > 0);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Choose at least one item to return.')]);
            }

            foreach ($lines as $itemId => $quantity) {
                if (! $returnable->has($itemId) || $quantity > $returnable[$itemId]->remaining_quantity) {
                    throw ValidationException::withMessages(['items' => __('One of those quantities is more than you can return.')]);
                }
            }

            $itemValue = (int) $lines->sum(fn ($q, $id) => $returnable[$id]->unit_price * $q);

            $return = ReturnRequest::create([
                'number' => $subOrder->number.'-R'.($subOrder->returns()->count() + 1),
                'sub_order_id' => $subOrder->id,
                'buyer_id' => $buyer->id,
                'type' => $type,
                'reason' => $reason,
                'details' => $details,
                'status' => ReturnStatus::Requested,
                'refund_amount' => $itemValue - $this->discountShare($subOrder, $itemValue),
            ]);

            foreach ($lines as $itemId => $quantity) {
                $return->items()->create(['order_item_id' => $itemId, 'quantity' => $quantity]);
            }

            return $return;
        });

        Activity::record('return.requested', "{$type->label()} requested: {$return->number}", $subOrder->order);
        $subOrder->store->user->notify(new ReturnRequested($return));
        AdminNotifier::send(new ReturnRequested($return));

        return $return;
    }

    public function approve(ReturnRequest $return, ?string $note = null): void
    {
        $this->move($return, [ReturnStatus::Requested, ReturnStatus::Rejected], ReturnStatus::Approved, ['seller_note' => $note, 'decided_at' => now()]);
    }

    public function reject(ReturnRequest $return, string $note): void
    {
        $this->move($return, [ReturnStatus::Requested], ReturnStatus::Rejected, ['seller_note' => $note, 'decided_at' => now()]);
    }

    public function markReceived(ReturnRequest $return, bool $restock): void
    {
        DB::transaction(function () use ($return, $restock) {
            $this->move($return, [ReturnStatus::Approved], ReturnStatus::Received, ['received_at' => now(), 'restocked' => $restock], notify: false);

            if ($restock) {
                foreach ($return->items()->with('orderItem.variant')->get() as $line) {
                    $line->orderItem->variant?->increment('stock', $line->quantity);
                }
            }
        });

        $return->buyer->notify(new ReturnUpdated($return));
    }

    /**
     * The seller sends a new unit of the same variant; it comes out of their stock.
     */
    public function shipReplacement(ReturnRequest $return, string $courier, string $tracking): void
    {
        if ($return->type !== ReturnType::Replacement) {
            throw ValidationException::withMessages(['courier' => __('This is a refund request, not a replacement.')]);
        }

        DB::transaction(function () use ($return, $courier, $tracking) {
            foreach ($return->items()->with('orderItem')->get() as $line) {
                $variant = $line->orderItem->variant()->lockForUpdate()->first();

                if (! $variant || $variant->stock < $line->quantity) {
                    throw ValidationException::withMessages(['courier' => __('Not enough stock of :item to send a replacement. Restock it, or ask the marketplace team to refund instead.', ['item' => $line->orderItem->displayName()])]);
                }

                $variant->decrement('stock', $line->quantity);
            }

            $this->move($return, [ReturnStatus::Received], ReturnStatus::Replaced, [
                'replacement_courier' => $courier,
                'replacement_tracking' => $tracking,
                'completed_at' => now(),
            ], notify: false);
        });

        $return->buyer->notify(new ReturnUpdated($return));
    }

    /**
     * An admin records that the buyer was paid back (Razorpay refund or bank transfer). The
     * seller's earlier credit for those items is reversed, and so is the commission on them.
     */
    public function markRefunded(ReturnRequest $return): void
    {
        if ($return->type !== ReturnType::Refund) {
            throw ValidationException::withMessages(['status' => __('This is a replacement request, not a refund.')]);
        }

        DB::transaction(function () use ($return) {
            $this->move($return, [ReturnStatus::Received], ReturnStatus::Refunded, ['completed_at' => now()], notify: false);

            $subOrder = $return->subOrder;
            $itemValue = (int) $return->items()->with('orderItem')->get()->sum(fn ($line) => $line->orderItem->unit_price * $line->quantity);

            $this->ledger->post($subOrder->store, LedgerType::Refund, -$itemValue, $subOrder, "Return {$return->number}");
            $this->ledger->post($subOrder->store, LedgerType::Commission, Pricing::commission($itemValue, $subOrder->commission_bps), $subOrder, "Commission reversed on return {$return->number}");

            $order = Order::whereKey($subOrder->order_id)->lockForUpdate()->first();
            $refunded = $order->refunded_total + $return->refund_amount;
            $order->update([
                'refunded_total' => $refunded,
                'payment_status' => $refunded >= $order->grand_total ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);
        });

        Activity::record('return.refunded', "Refund recorded for return {$return->number}", $return->subOrder->order);
        $return->buyer->notify(new ReturnUpdated($return));
    }

    /**
     * This item value's share of the parcel's coupon discount, which the buyer did not pay.
     */
    private function discountShare(SubOrder $subOrder, int $itemValue): int
    {
        return $subOrder->discount > 0 ? intdiv($subOrder->discount * $itemValue, max(1, $subOrder->items_subtotal)) : 0;
    }

    /**
     * @param  array<int, ReturnStatus>  $from
     * @param  array<string, mixed>  $changes
     */
    private function move(ReturnRequest $return, array $from, ReturnStatus $to, array $changes, bool $notify = true): void
    {
        DB::transaction(function () use ($return, $from, $to, $changes) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->first();

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => __('This return is :status and cannot be changed that way.', ['status' => strtolower($locked->status->label())])]);
            }

            $locked->update(['status' => $to, ...$changes]);
            $return->setRawAttributes($locked->getAttributes(), true);
        });

        if ($notify) {
            $return->buyer->notify(new ReturnUpdated($return));
        }
    }
}

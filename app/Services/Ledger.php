<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Models\SubOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The append-only seller statement. A positive balance is what the platform owes the seller.
 */
class Ledger
{
    public function post(Store $store, LedgerType $type, int $amount, ?SubOrder $subOrder = null, ?string $note = null): LedgerEntry
    {
        return DB::transaction(function () use ($store, $type, $amount, $subOrder, $note) {
            // Lock the store row so concurrent postings compute balances one at a time.
            Store::whereKey($store->id)->lockForUpdate()->first();

            $previous = (int) (LedgerEntry::where('store_id', $store->id)->orderByDesc('id')->value('balance_after') ?? 0);

            return LedgerEntry::create([
                'store_id' => $store->id,
                'sub_order_id' => $subOrder?->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $previous + $amount,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Credits a delivered sub-order. For Cash on Delivery the seller's courier already
     * collected the cash, so a matching debit leaves only the commission owed to the platform.
     */
    public function creditDelivery(SubOrder $subOrder, bool $cashOnDelivery): void
    {
        DB::transaction(function () use ($subOrder, $cashOnDelivery) {
            $store = $subOrder->store;
            $gross = $subOrder->items_subtotal + $subOrder->shipping;

            $this->post($store, LedgerType::Sale, $gross, $subOrder, "Sale {$subOrder->number}");
            $this->post($store, LedgerType::Commission, -$subOrder->commission_amount, $subOrder,
                sprintf('Commission %s%% on %s', rtrim(rtrim(number_format($subOrder->commission_bps / 100, 2), '0'), '.'), $subOrder->number));

            if ($cashOnDelivery) {
                $this->post($store, LedgerType::CodCollected, -$gross, $subOrder, "COD cash collected for {$subOrder->number}");
            }
        });
    }

    /**
     * A payout made to the seller outside the system (bank transfer), recorded by an admin.
     */
    public function recordPayout(Store $store, int $amount, ?string $note): LedgerEntry
    {
        return $this->post($store, LedgerType::Payout, -$amount, null, $note ?: 'Payout');
    }

    public function adjust(Store $store, int $amount, string $note): LedgerEntry
    {
        return $this->post($store, LedgerType::Adjustment, $amount, null, $note);
    }
}

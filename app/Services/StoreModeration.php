<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\StoreStatus;
use App\Models\Activity;
use App\Models\Store;
use App\Notifications\StoreReviewed;
use Illuminate\Support\Facades\DB;

/**
 * Admin actions on a seller's store. Suspending also deactivates the seller's
 * login, so they are logged out on their next click and their products leave the storefront.
 */
class StoreModeration
{
    public function approve(Store $store): void
    {
        $store->status = StoreStatus::Approved;
        $store->rejection_reason = null;
        $store->approved_at ??= now();
        $store->save();

        Activity::record('store.approved', "Store approved: {$store->name}", $store);
        $store->user->notify(new StoreReviewed($store));
    }

    public function reject(Store $store, string $reason): void
    {
        $store->status = StoreStatus::Rejected;
        $store->rejection_reason = $reason;
        $store->save();

        Activity::record('store.rejected', "Store rejected: {$store->name}", $store);
        $store->user->notify(new StoreReviewed($store));
    }

    public function suspend(Store $store, string $reason): void
    {
        DB::transaction(function () use ($store, $reason) {
            $store->status = StoreStatus::Suspended;
            $store->rejection_reason = $reason;
            $store->save();

            $store->user->forceFill(['status' => AccountStatus::Deactivated])->save();
        });

        Activity::record('store.suspended', "Store suspended: {$store->name}", $store);
    }

    public function reactivate(Store $store): void
    {
        DB::transaction(function () use ($store) {
            $store->status = StoreStatus::Approved;
            $store->rejection_reason = null;
            $store->save();

            $store->user->forceFill(['status' => AccountStatus::Active])->save();
        });

        Activity::record('store.reactivated', "Store reactivated: {$store->name}", $store);
    }

    public function setCommission(Store $store, ?int $bps): void
    {
        $store->commission_bps = $bps;
        $store->save();
    }
}

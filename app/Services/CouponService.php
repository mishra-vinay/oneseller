<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\User;
use App\Support\Money;

/**
 * Checks a coupon against a cart and works out the discount and how it is shared
 * between the sellers' parcels. Discounts apply to item prices, not shipping.
 */
class CouponService
{
    public static function normalise(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * @param  array<int, int>  $subtotalsByStore  store id => item subtotal
     * @return array{coupon: ?Coupon, discount: int, allocation: array<int, int>, error: ?string}
     */
    public function evaluate(?string $code, array $subtotalsByStore, ?User $user, bool $lock = false): array
    {
        $none = ['coupon' => null, 'discount' => 0, 'allocation' => [], 'error' => null];
        $code = self::normalise($code);

        if ($code === null) {
            return $none;
        }

        $query = Coupon::where('code', $code);
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();
        $fail = fn (string $message) => [...$none, 'error' => $message];

        if (! $coupon || ! $coupon->is_active) {
            return $fail(__('That coupon code is not valid.'));
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return $fail(__('That coupon is not active yet.'));
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return $fail(__('That coupon has expired.'));
        }

        if ($coupon->usage_limit !== null && $coupon->liveRedemptions()->count() >= $coupon->usage_limit) {
            return $fail(__('That coupon has been fully used.'));
        }

        if ($user && $coupon->per_user_limit !== null && $coupon->liveRedemptions()->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            return $fail(__('You have already used that coupon.'));
        }

        $eligible = $coupon->store_id ? array_intersect_key($subtotalsByStore, [$coupon->store_id => true]) : $subtotalsByStore;
        $eligibleTotal = array_sum($eligible);

        if ($eligibleTotal === 0) {
            return $fail(__('That coupon only applies to products from :store.', ['store' => $coupon->store?->name ?? __('another seller')]));
        }

        if ($coupon->min_order_paise && $eligibleTotal < $coupon->min_order_paise) {
            return $fail(__('Add items worth :amount more to use that coupon.', ['amount' => Money::format($coupon->min_order_paise - $eligibleTotal)]));
        }

        $discount = $coupon->type === CouponType::Percent
            ? intdiv($eligibleTotal * $coupon->value, 10000)
            : $coupon->value;

        if ($coupon->max_discount_paise) {
            $discount = min($discount, $coupon->max_discount_paise);
        }

        $discount = min($discount, $eligibleTotal);

        return ['coupon' => $coupon, 'discount' => $discount, 'allocation' => self::allocate($discount, $eligible), 'error' => null];
    }

    /**
     * Shares a discount across parcels in proportion to their subtotals; the remainder goes to the last.
     *
     * @param  array<int, int>  $subtotals
     * @return array<int, int>
     */
    public static function allocate(int $discount, array $subtotals): array
    {
        $total = array_sum($subtotals);
        $allocation = [];
        $given = 0;
        $keys = array_keys($subtotals);

        foreach ($keys as $i => $key) {
            $share = $i === count($keys) - 1 ? $discount - $given : intdiv($discount * $subtotals[$key], max(1, $total));
            $allocation[$key] = $share;
            $given += $share;
        }

        return $allocation;
    }
}

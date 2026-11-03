<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'min_order_paise' => 'integer',
            'max_discount_paise' => 'integer',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Redemptions that count against limits: those on orders that were not cancelled.
     */
    public function liveRedemptions(): HasMany
    {
        return $this->redemptions()->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled));
    }

    /**
     * "10% off (up to ₹200)" or "₹150 off".
     */
    public function summary(): string
    {
        $off = $this->type === CouponType::Percent
            ? rtrim(rtrim(number_format($this->value / 100, 2), '0'), '.').'% off'
            : Money::format($this->value).' off';

        if ($this->type === CouponType::Percent && $this->max_discount_paise) {
            $off .= ' (up to '.Money::format($this->max_discount_paise).')';
        }

        return $off;
    }
}

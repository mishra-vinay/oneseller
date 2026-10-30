<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One seller's portion of an order, fulfilled and credited separately.
 */
class SubOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'items_subtotal' => 'integer',
            'shipping' => 'integer',
            'commission_bps' => 'integer',
            'commission_amount' => 'integer',
            'net_payout' => 'integer',
            'accepted_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * What the buyer pays for this portion.
     */
    public function total(): int
    {
        return $this->items_subtotal + $this->shipping;
    }
}

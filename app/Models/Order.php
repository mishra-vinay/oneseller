<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The buyer's single order. Each seller's portion is a SubOrder.
 */
class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'items_total' => 'integer',
            'shipping_total' => 'integer',
            'grand_total' => 'integer',
            'refunded_total' => 'integer',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shippingAddressLines(): array
    {
        return array_values(array_filter([
            $this->ship_name.' · '.$this->ship_phone,
            $this->ship_address_line1,
            $this->ship_address_line2,
            "{$this->ship_city}, {$this->ship_state} {$this->ship_pincode}",
        ]));
    }
}

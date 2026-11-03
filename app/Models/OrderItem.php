<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Name, SKU and price are snapshots so later product edits never change past orders.
 */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer'];
    }

    /**
     * "T-Shirt (M / Red)", or just the name for a product without options.
     */
    public function displayName(): string
    {
        return $this->variant_label ? "{$this->product_name} ({$this->variant_label})" : $this->product_name;
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

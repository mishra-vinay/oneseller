<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = ['sku', 'options', 'position', 'price', 'compare_at_price', 'stock', 'weight_grams'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'options' => 'array',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * "M / Red" for a variant with options, null for a product's only variant.
     */
    public function label(): ?string
    {
        return $this->options ? implode(' / ', array_values($this->options)) : null;
    }

    /**
     * Stable key for an option combination, used to match variants when options change.
     *
     * @param  array<string, string>|null  $options
     */
    public static function keyFor(?array $options): string
    {
        return $options ? json_encode(array_values($options)) : '';
    }

    public function isLowStock(): bool
    {
        return $this->stock <= self::LOW_STOCK_THRESHOLD;
    }
}

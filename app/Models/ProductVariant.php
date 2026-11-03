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
     * Values follow the product's option order: MySQL does not keep JSON key order.
     *
     * @param  array<int, string>|null  $optionNames
     */
    public function label(?array $optionNames = null): ?string
    {
        if (! $this->options) {
            return null;
        }

        $optionNames ??= $this->relationLoaded('product') ? $this->product?->option_names : null;
        $values = $optionNames
            ? array_values(array_filter(array_map(fn ($name) => $this->options[$name] ?? null, $optionNames), fn ($v) => $v !== null))
            : array_values($this->options);

        return implode(' / ', $values);
    }

    /**
     * Key for an option combination, independent of key order, used to match variants when options change.
     *
     * @param  array<string, string>|null  $options
     */
    public static function keyFor(?array $options): string
    {
        if (! $options) {
            return '';
        }

        ksort($options);

        return json_encode($options);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= self::LOW_STOCK_THRESHOLD;
    }
}

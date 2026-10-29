<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const MAX_IMAGES = 5;

    protected $fillable = ['category_id', 'name', 'short_description', 'description', 'is_published'];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_published' => 'boolean',
            'admin_disabled' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Products a buyer may see: approved, published, not disabled by an admin,
     * in an active category, from a live store.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('products.status', ProductStatus::Approved)
            ->where('products.is_published', true)
            ->where('products.admin_disabled', false)
            ->whereHas('store', fn ($q) => $q->live())
            ->whereHas('category', fn ($q) => $q->active());
    }

    public function isVisible(): bool
    {
        return static::query()->visible()->whereKey($this->id)->exists();
    }

    public function url(): string
    {
        return route('product.show', [$this->store->slug, $this->slug]);
    }

    public function primaryImageUrl(): ?string
    {
        return $this->images->first()?->url();
    }
}

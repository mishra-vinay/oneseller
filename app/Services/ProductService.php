<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Activity;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Notifications\ProductSubmitted;
use App\Support\AdminNotifier;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seller-side product saving, including the approval rules:
 * - a new product is a draft, or goes for review when submitted;
 * - editing the name, descriptions, price or images of a live product sends it back for review;
 * - stock and publish/unpublish never do.
 */
class ProductService
{
    private const REVIEWED_FIELDS = ['name', 'short_description', 'description'];

    private const REVIEWED_VARIANT_FIELDS = ['price', 'compare_at_price'];

    public function __construct(private Settings $settings, private ImageUploader $images) {}

    /**
     * @param  array<string, mixed>  $data  product + variant fields, money in paise
     * @param  array<int, UploadedFile>  $newImages
     * @param  array<int, int>  $removeImageIds
     */
    public function save(Store $store, ?Product $product, array $data, array $newImages = [], array $removeImageIds = [], bool $submit = false): Product
    {
        $product ??= new Product;
        $isNew = ! $product->exists;

        $saved = DB::transaction(function () use ($store, $product, $data, $newImages, $removeImageIds, $submit, $isNew) {
            $product->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'is_published' => $data['is_published'] ?? true,
            ]);

            $variant = $isNew ? new ProductVariant(['sku' => '']) : $product->defaultVariant;
            $variant->fill([
                'sku' => $data['sku'],
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'stock' => $data['stock'],
                'weight_grams' => $data['weight_grams'] ?? 0,
            ]);

            $needsReview = $product->isDirty(self::REVIEWED_FIELDS)
                || $variant->isDirty(self::REVIEWED_VARIANT_FIELDS)
                || $newImages !== []
                || ($removeImageIds !== [] && $product->images()->whereIn('id', $removeImageIds)->exists());

            if ($isNew) {
                $product->store()->associate($store);
                $product->slug = $this->uniqueSlug($store, $data['name']);
                $product->status = ProductStatus::Draft;
            }

            $this->applyStatus($product, $needsReview && ! $isNew, $submit);
            $product->save();

            $variant->product()->associate($product);
            $variant->is_default = true;
            $variant->save();

            $this->syncImages($product, $newImages, $removeImageIds);

            return $product;
        });

        if ($saved->wasChanged('status') || $isNew) {
            $this->announce($saved);
        }

        return $saved->fresh(['defaultVariant', 'images', 'category']);
    }

    /**
     * Inline edit from the inventory table.
     */
    public function updateInventory(Product $product, int $price, int $stock): void
    {
        $variant = $product->defaultVariant;
        $variant->price = $price;
        $variant->stock = $stock;

        $this->applyStatus($product, $variant->isDirty('price'), false);

        DB::transaction(function () use ($product, $variant) {
            $variant->save();
            $product->save();
        });

        if ($product->wasChanged('status')) {
            $this->announce($product);
        }
    }

    public function delete(Product $product): void
    {
        foreach ($product->images as $image) {
            $this->images->delete($image->path);
        }

        $product->delete();
    }

    private function applyStatus(Product $product, bool $liveContentChanged, bool $submit): void
    {
        $forReview = match ($product->status) {
            ProductStatus::Approved => $liveContentChanged,
            ProductStatus::Draft, ProductStatus::Rejected => $submit,
            ProductStatus::Pending => false,
        };

        if (! $forReview) {
            return;
        }

        if ($this->settings->get('auto_approve_products')) {
            $product->status = ProductStatus::Approved;
            $product->approved_at = now();
        } else {
            $product->status = ProductStatus::Pending;
        }

        $product->rejection_reason = null;
    }

    private function announce(Product $product): void
    {
        if ($product->status === ProductStatus::Pending) {
            Activity::record('product.submitted', "Product submitted for approval: {$product->name}", $product);
            AdminNotifier::send(new ProductSubmitted($product));
        }
    }

    /**
     * @param  array<int, UploadedFile>  $newImages
     * @param  array<int, int>  $removeImageIds
     */
    private function syncImages(Product $product, array $newImages, array $removeImageIds): void
    {
        foreach ($product->images()->whereIn('id', $removeImageIds)->get() as $image) {
            $this->images->delete($image->path);
            $image->delete();
        }

        $next = (int) $product->images()->max('sort_order') + 1;

        foreach ($newImages as $file) {
            $product->images()->create([
                'path' => $this->images->store($file, "products/{$product->id}"),
                'sort_order' => $next++,
            ]);
        }
    }

    private function uniqueSlug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        for ($i = 2; $store->products()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}

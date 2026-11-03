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
 * - editing the name, descriptions, options, any variant's price, or images of a live product
 *   sends it back for review;
 * - stock and publish/unpublish never do.
 */
class ProductService
{
    private const REVIEWED_FIELDS = ['name', 'short_description', 'description'];

    private const REVIEWED_VARIANT_FIELDS = ['price', 'compare_at_price'];

    public function __construct(private Settings $settings, private ImageUploader $images) {}

    public const MAX_OPTIONS = 2;

    public const MAX_VARIANTS = 50;

    /**
     * Saves a product and its variants.
     *
     * $data holds the product fields, plus either:
     * - no options: flat sku/price/compare_at_price/stock/weight_grams for the single variant; or
     * - 'options' => [['name' => 'Size', 'values' => ['S', 'M']], …]: one variant per combination.
     *   Rows for existing variants come in 'variants' => [variantId => [sku, price, …]]. New
     *   combinations copy the flat fields (or the default variant) with zero stock.
     *
     * @param  array<string, mixed>  $data  money in paise
     * @param  array<int, UploadedFile>  $newImages
     * @param  array<int, int>  $removeImageIds
     */
    public function save(Store $store, ?Product $product, array $data, array $newImages = [], array $removeImageIds = [], bool $submit = false): Product
    {
        $product ??= new Product;
        $isNew = ! $product->exists;
        $options = $this->normaliseOptions($data['options'] ?? []);

        $saved = DB::transaction(function () use ($store, $product, $data, $options, $newImages, $removeImageIds, $submit, $isNew) {
            $product->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'is_published' => $data['is_published'] ?? true,
            ]);
            $product->option_names = $options === [] ? null : array_column($options, 'name');

            $needsReview = $product->isDirty([...self::REVIEWED_FIELDS, 'option_names'])
                || $newImages !== []
                || ($removeImageIds !== [] && $product->images()->whereIn('id', $removeImageIds)->exists());

            if ($isNew) {
                $product->store()->associate($store);
                $product->slug = $this->uniqueSlug($store, $data['name']);
                $product->status = ProductStatus::Draft;
            }

            // Status depends on the variant changes too, so save first and settle the status after.
            $product->save();
            $variantsChanged = $this->syncVariants($product, $options, $data, $isNew);

            $this->applyStatus($product, ($needsReview || $variantsChanged) && ! $isNew, $submit);
            $product->save();

            $this->syncImages($product, $newImages, $removeImageIds);

            return $product;
        });

        if ($saved->wasChanged('status') || $isNew) {
            $this->announce($saved);
        }

        return $saved->fresh(['defaultVariant', 'variants', 'images', 'category']);
    }

    /**
     * Inline edit of one variant from the inventory table.
     */
    public function updateInventory(ProductVariant $variant, int $price, int $stock): void
    {
        $product = $variant->product;
        $variant->price = $price;
        $variant->stock = $stock;

        $this->applyStatus($product, $variant->isDirty('price'), false);

        DB::transaction(function () use ($product, $variant) {
            $variant->save();
            $product->save();
            $this->pickDefaultVariant($product);
        });

        if ($product->wasChanged('status')) {
            $this->announce($product);
        }
    }

    /**
     * Option combinations, e.g. Size [S, M] × Colour [Red] → [{Size: S, Colour: Red}, {Size: M, Colour: Red}].
     *
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     * @return array<int, array<string, string>|null>
     */
    public static function combinations(array $options): array
    {
        if ($options === []) {
            return [null];
        }

        $combos = [[]];

        foreach ($options as $option) {
            $next = [];

            foreach ($combos as $combo) {
                foreach ($option['values'] as $value) {
                    $next[] = [...$combo, $option['name'] => $value];
                }
            }

            $combos = $next;
        }

        return $combos;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<int, array{name: string, values: array<int, string>}>
     */
    private function normaliseOptions(array $raw): array
    {
        $options = [];

        foreach (array_slice($raw, 0, self::MAX_OPTIONS) as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $values = is_array($option['values'] ?? null) ? $option['values'] : explode(',', (string) ($option['values'] ?? ''));
            $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $values), 'strlen')));

            if ($name !== '' && $values !== []) {
                $options[] = ['name' => $name, 'values' => $values];
            }
        }

        return $options;
    }

    /**
     * Creates, updates and deletes variants to match the option combinations.
     * Returns true when a buyer-visible change (price, new or removed variant) needs review.
     *
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     * @param  array<string, mixed>  $data
     */
    private function syncVariants(Product $product, array $options, array $data, bool $isNew): bool
    {
        $existing = $isNew ? collect() : $product->variants()->get();
        $rows = $data['variants'] ?? [];
        $template = $existing->firstWhere('is_default', true) ?? $existing->first();
        $changed = false;

        $flat = array_key_exists('price', $data) ? [
            'sku' => $data['sku'],
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'stock' => $data['stock'],
            'weight_grams' => $data['weight_grams'] ?? 0,
        ] : null;

        $byKey = $existing->keyBy(fn (ProductVariant $v) => ProductVariant::keyFor($v->options));
        $kept = [];

        foreach (self::combinations($options) as $position => $combo) {
            $key = ProductVariant::keyFor($combo);
            $variant = $byKey->get($key)
                // Gaining options: the single variant becomes the first combination.
                ?? ($position === 0 && $byKey->count() === 1 && $byKey->has('') ? $byKey->get('') : null)
                // Dropping options: the default variant becomes the only one, keeping its stock.
                ?? ($combo === null ? $template : null);
            $isNewVariant = $variant === null;
            $variant ??= new ProductVariant;

            if ($row = ($variant->exists ? ($rows[$variant->id] ?? null) : null)) {
                $variant->fill(array_intersect_key($row, array_flip(['sku', 'price', 'compare_at_price', 'stock', 'weight_grams'])));
            } elseif ($combo === null && $flat !== null) {
                $variant->fill($flat);
            } elseif ($isNewVariant) {
                $base = $flat ?? $template?->only(['sku', 'price', 'compare_at_price', 'weight_grams']) ?? [];
                $variant->fill([
                    ...$base,
                    'sku' => trim(($base['sku'] ?? 'SKU').'-'.Str::upper(Str::slug(implode('-', $combo ?? []))), '-'),
                    // Only a brand-new product takes the stock from the form; added combinations start empty.
                    'stock' => $isNew && $flat ? $flat['stock'] : 0,
                ]);
            }

            $variant->options = $combo;
            $variant->position = $position;
            $changed = $changed || ($variant->exists && $variant->isDirty(self::REVIEWED_VARIANT_FIELDS)) || ($isNewVariant && ! $isNew);
            $variant->product()->associate($product);
            $variant->is_default = $variant->is_default ?? false;
            $variant->save();
            $kept[] = $variant->id;
        }

        $removed = $existing->whereNotIn('id', $kept);
        $changed = $changed || $removed->isNotEmpty();
        $removed->each->delete();

        $this->pickDefaultVariant($product);

        return $changed;
    }

    /**
     * The cheapest variant is the default: listings show and sort by its price.
     */
    private function pickDefaultVariant(Product $product): void
    {
        $cheapest = $product->variants()->reorder()->orderBy('price')->orderBy('position')->orderBy('id')->first();

        if ($cheapest && ! $cheapest->is_default) {
            $product->variants()->whereKeyNot($cheapest->id)->update(['is_default' => false]);
            $cheapest->forceFill(['is_default' => true])->save();
        } elseif ($cheapest) {
            $product->variants()->whereKeyNot($cheapest->id)->where('is_default', true)->update(['is_default' => false]);
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

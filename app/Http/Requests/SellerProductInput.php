<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Services\ProductService;
use App\Support\Money;
use App\Support\Rules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates a seller's product form and turns it into ProductService::save() data.
 * Shared by the web form (money as rupee strings) and the API (money as integer paise).
 */
class SellerProductInput
{
    /**
     * @return array<string, mixed>
     */
    public static function validate(Request $request, ?Product $product = null, bool $moneyInPaise = false): array
    {
        // Option values may come as "S, M, L" (web) or ["S", "M", "L"] (API).
        $request->merge(['options' => collect($request->input('options', []))
            ->map(fn ($o) => is_array($o) ? [...$o, 'values' => is_array($o['values'] ?? null) ? implode(',', $o['values']) : ($o['values'] ?? null)] : $o)
            ->all()]);

        $existingImages = $product ? $product->images()->whereNotIn('id', $request->input('remove_images', []))->count() : 0;
        $maxNew = Product::MAX_IMAGES - $existingImages;
        $money = $moneyInPaise ? ['integer', 'min:0', 'max:10000000000'] : [Rules::RUPEES];

        // A product with saved options is edited as variant rows; otherwise flat fields.
        $flat = ! $request->has('variants');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'price' => [Rule::requiredIf($flat), ...$money],
            'compare_at_price' => ['nullable', ...$money],
            'stock' => [Rule::requiredIf($flat), 'integer', 'min:0', 'max:1000000'],
            'sku' => [Rule::requiredIf($flat), 'string', 'max:64'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'options' => ['array', 'max:'.ProductService::MAX_OPTIONS],
            'options.*.name' => ['nullable', 'string', 'max:30'],
            'options.*.values' => ['nullable', 'string', 'max:300'],
            'variants' => ['array'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.price' => ['required', ...$money],
            'variants.*.compare_at_price' => ['nullable', ...$money],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'images' => ['array', 'max:'.max(0, $maxNew)],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_images' => ['array'],
            'remove_images.*' => ['integer'],
        ], [
            ...Rules::messages(),
            'variants.*.price.regex' => __('Enter prices in rupees, e.g. 499 or 499.50.'),
            'images.max' => __('A product can have at most :max images.', ['max' => Product::MAX_IMAGES]),
        ]);

        $combinations = count(ProductService::combinations(array_values(array_filter(
            array_map(fn ($o) => ['name' => trim($o['name'] ?? ''), 'values' => array_filter(array_map('trim', explode(',', $o['values'] ?? '')), 'strlen')], $data['options'] ?? []),
            fn ($o) => $o['name'] !== '' && $o['values'] !== [],
        ))));

        if ($combinations > ProductService::MAX_VARIANTS) {
            throw ValidationException::withMessages(['options' => __('That makes :count combinations; the limit is :max.', ['count' => $combinations, 'max' => ProductService::MAX_VARIANTS])]);
        }

        $toPaise = fn ($value) => $moneyInPaise ? (int) $value : Money::fromRupees((string) $value);
        $prices = function ($price, $compareAt) use ($toPaise) {
            $price = $toPaise($price);
            $compareAt = filled($compareAt) ? $toPaise($compareAt) : null;

            // A compare-at price only shows when it is higher than the price.
            return [$price, $compareAt !== null && $compareAt > $price ? $compareAt : null];
        };

        if ($flat) {
            [$data['price'], $data['compare_at_price']] = $prices($data['price'] ?? 0, $data['compare_at_price'] ?? null);
        } else {
            unset($data['price'], $data['compare_at_price'], $data['sku'], $data['stock']);

            foreach ($data['variants'] as $id => $row) {
                [$row['price'], $row['compare_at_price']] = $prices($row['price'], $row['compare_at_price'] ?? null);
                $row['weight_grams'] = (int) ($row['weight_grams'] ?? 0);
                $data['variants'][$id] = $row;
            }
        }

        // An unticked web checkbox sends nothing, meaning "unpublished"; the API may simply leave it out.
        $data['is_published'] = $moneyInPaise && ! $request->has('is_published')
            ? ($product->is_published ?? true)
            : $request->boolean('is_published');

        return $data;
    }
}

<?php

namespace App\Services;

use App\Enums\ShippingMode;
use App\Models\Store;
use App\Support\Money;
use App\Support\Settings;

/**
 * Shipping and commission maths, in paise.
 */
class Pricing
{
    public function __construct(private Settings $settings) {}

    /**
     * Shipping for one seller's parcel. Each store uses the marketplace default, its own flat
     * rate, or a weight rate card, and shipping is free once the parcel's subtotal reaches the
     * store's free-shipping threshold.
     */
    public function shippingFor(Store $store, int $groupSubtotal, int $weightGrams): int
    {
        $threshold = $this->freeAbove($store);

        if ($threshold !== null && $groupSubtotal >= $threshold) {
            return 0;
        }

        return match ($store->shipping_mode) {
            ShippingMode::Flat => (int) $store->shipping_flat_paise,
            ShippingMode::Weight => $this->byWeight($store, $weightGrams),
            default => $this->settings->get('flat_shipping_paise'),
        };
    }

    /**
     * Subtotal from which a store ships for free, or null when it never does.
     */
    public function freeAbove(Store $store): ?int
    {
        return $store->shipping_mode === ShippingMode::Platform
            ? $this->settings->get('free_shipping_threshold_paise')
            : $store->shipping_free_above_paise;
    }

    /**
     * One line for product and store pages, e.g. "Shipping ₹50 · free above ₹999".
     */
    public function describe(Store $store): string
    {
        $free = $this->freeAbove($store);
        $base = match ($store->shipping_mode) {
            ShippingMode::Weight => (int) ($store->shipping_slabs[0]['paise'] ?? 0),
            ShippingMode::Flat => (int) $store->shipping_flat_paise,
            default => $this->settings->get('flat_shipping_paise'),
        };

        if ($free === 0 || $base === 0) {
            return __('Free shipping');
        }

        $line = $store->shipping_mode === ShippingMode::Weight
            ? __('Shipping from :amount, by weight', ['amount' => Money::format($base)])
            : __('Shipping :amount', ['amount' => Money::format($base)]);

        return $free === null ? $line : $line.' · '.__('free above :amount', ['amount' => Money::format($free)]);
    }

    /**
     * Commission on the item subtotal only, rounded to the nearest paisa.
     */
    public static function commission(int $itemsSubtotal, int $bps): int
    {
        return intdiv($itemsSubtotal * $bps + 5000, 10000);
    }

    private function byWeight(Store $store, int $weightGrams): int
    {
        $slabs = $store->shipping_slabs ?? [];

        foreach ($slabs as $slab) {
            if ($weightGrams <= $slab['up_to_grams']) {
                return (int) $slab['paise'];
            }
        }

        $last = end($slabs) ?: ['up_to_grams' => 0, 'paise' => 0];
        $extraKg = (int) ceil(($weightGrams - $last['up_to_grams']) / 1000);

        return (int) $last['paise'] + $extraKg * (int) $store->shipping_extra_kg_paise;
    }
}

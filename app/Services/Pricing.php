<?php

namespace App\Services;

use App\Support\Settings;

/**
 * Shipping and commission maths, in paise.
 */
class Pricing
{
    public function __construct(private Settings $settings) {}

    /**
     * Shipping is charged per seller group, and waived when that group's subtotal reaches the threshold.
     */
    public function shippingFor(int $groupSubtotal): int
    {
        $threshold = $this->settings->get('free_shipping_threshold_paise');

        return $groupSubtotal >= $threshold ? 0 : $this->settings->get('flat_shipping_paise');
    }

    /**
     * Commission on the item subtotal only, rounded to the nearest paisa.
     */
    public static function commission(int $itemsSubtotal, int $bps): int
    {
        return intdiv($itemsSubtotal * $bps + 5000, 10000);
    }
}

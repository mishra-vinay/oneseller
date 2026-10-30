<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace-wide settings managed from /admin/settings.
 *
 * Values are stored as JSON in the `settings` table and cached as one array.
 * Money is in paise and commission in basis points (1000 = 10%).
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Every known setting with its default value.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'site_name' => 'OneSeller',
        'default_commission_bps' => 1000,
        'flat_shipping_paise' => 5000,
        'free_shipping_threshold_paise' => 99900,
        'support_email' => null,
        'order_notification_email' => null,
        'cod_enabled' => true,
        'auto_approve_sellers' => false,
        'auto_approve_products' => false,
    ];

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("Unknown setting [{$key}].");
        }

        return $this->all()[$key];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')
            ->pluck('value', 'key')
            ->map(fn (string $json) => json_decode($json, true))
            ->all());

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $unknown = array_diff_key($values, self::DEFAULTS);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown settings: '.implode(', ', array_keys($unknown)));
        }

        $now = now();

        DB::table('settings')->upsert(
            collect($values)->map(fn ($value, $key) => [
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['key'],
            ['value', 'updated_at'],
        );

        Cache::forget(self::CACHE_KEY);
    }
}

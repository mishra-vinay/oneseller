<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Storefront language and display currency. Prices are always charged in INR; other
 * currencies are shown for reference only, at rates the admin sets.
 */
class Locale
{
    public const LANGUAGES = ['en' => 'English', 'hi' => 'हिन्दी'];

    public const COOKIE_LANGUAGE = 'locale';

    public const COOKIE_CURRENCY = 'currency';

    private static ?string $currency = null;

    /**
     * Website: the visitor's choice, else their browser's preference. API: Accept-Language.
     */
    public static function languageFor(Request $request): string
    {
        $chosen = $request->cookie(self::COOKIE_LANGUAGE);

        if (is_string($chosen) && isset(self::LANGUAGES[$chosen])) {
            return $chosen;
        }

        return $request->getPreferredLanguage(array_keys(self::LANGUAGES)) ?? 'en';
    }

    /**
     * @return array<string, float> currency code => units of that currency per 1 INR
     */
    public static function rates(): array
    {
        return array_map('floatval', app(Settings::class)->get('currency_rates') ?? []);
    }

    public static function setCurrency(?string $code): void
    {
        self::$currency = $code && isset(self::rates()[$code]) ? $code : 'INR';
    }

    public static function currency(): string
    {
        return self::$currency ?? 'INR';
    }

    /**
     * The amount in the display currency, e.g. "≈ US$33.59", or null when showing INR.
     */
    public static function converted(int $paise): ?string
    {
        $code = self::currency();

        if ($code === 'INR') {
            return null;
        }

        $amount = $paise / 100 * self::rates()[$code];
        $formatter = new \NumberFormatter(app()->getLocale() === 'hi' ? 'hi_IN' : 'en_IN', \NumberFormatter::CURRENCY);

        return '≈ '.$formatter->formatCurrency($amount, $code);
    }
}

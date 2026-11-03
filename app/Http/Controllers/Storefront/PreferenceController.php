<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class PreferenceController extends Controller
{
    private const MINUTES = 60 * 24 * 365;

    public function language(string $code): RedirectResponse
    {
        abort_unless(isset(Locale::LANGUAGES[$code]), 404);

        return back()->withCookie(Cookie::make(Locale::COOKIE_LANGUAGE, $code, self::MINUTES));
    }

    public function currency(string $code): RedirectResponse
    {
        abort_unless($code === 'INR' || isset(Locale::rates()[$code]), 404);

        return back()->withCookie(Cookie::make(Locale::COOKIE_CURRENCY, $code, self::MINUTES));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings', ['settings' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $rupees = ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'];

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'default_commission_percent' => ['required', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'flat_shipping_rupees' => $rupees,
            'free_shipping_threshold_rupees' => $rupees,
            'support_email' => ['nullable', 'email', 'max:255'],
            'order_notification_email' => ['nullable', 'email', 'max:255'],
            'return_window_days' => ['required', 'integer', 'min:0', 'max:90'],
            'currency_rates' => ['nullable', 'string', 'max:500', 'regex:/^(\s*[A-Za-z]{3}\s*=\s*\d+(\.\d+)?\s*(\R|$))*$/'],
        ], [
            'currency_rates.regex' => __('One currency per line, like USD=0.012 (units per ₹1).'),
            'default_commission_percent.regex' => __('Enter a percentage between 0 and 99.99.'),
            '*.regex' => __('Enter an amount in rupees, e.g. 49 or 49.50.'),
        ]);

        $settings->set([
            'site_name' => $data['site_name'],
            // Percent with two decimals maps exactly onto basis points, the same way rupees map onto paise.
            'default_commission_bps' => Money::fromRupees($data['default_commission_percent']),
            'flat_shipping_paise' => Money::fromRupees($data['flat_shipping_rupees']),
            'free_shipping_threshold_paise' => Money::fromRupees($data['free_shipping_threshold_rupees']),
            'support_email' => $data['support_email'] ?? null,
            'order_notification_email' => $data['order_notification_email'] ?? null,
            'cod_enabled' => $request->boolean('cod_enabled'),
            'return_window_days' => (int) $data['return_window_days'],
            'currency_rates' => collect(preg_split('/\R/', (string) ($data['currency_rates'] ?? '')))
                ->map(fn ($line) => array_map('trim', explode('=', $line)))
                ->filter(fn ($pair) => count($pair) === 2 && $pair[0] !== '' && strtoupper($pair[0]) !== 'INR' && (float) $pair[1] > 0)
                ->mapWithKeys(fn ($pair) => [strtoupper($pair[0]) => (float) $pair[1]])
                ->all(),
            'auto_approve_sellers' => $request->boolean('auto_approve_sellers'),
            'auto_approve_products' => $request->boolean('auto_approve_products'),
            'route_settlement_enabled' => $request->boolean('route_settlement_enabled'),
        ]);

        return back()->with('status', __('Settings saved.'));
    }
}

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
        ], [
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
            'auto_approve_sellers' => $request->boolean('auto_approve_sellers'),
            'auto_approve_products' => $request->boolean('auto_approve_products'),
        ]);

        return back()->with('status', __('Settings saved.'));
    }
}

<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ShippingMode;
use App\Support\Money;
use App\Support\Rules;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The seller's shipping rules: marketplace default, own flat rate, or a weight rate card.
 */
class ShippingController extends SellerController
{
    public const SLAB_ROWS = 5;

    public function edit(Request $request, Settings $settings): View
    {
        return view('seller.shipping', [
            'store' => $this->currentStore($request),
            'platformFlat' => $settings->get('flat_shipping_paise'),
            'platformFreeAbove' => $settings->get('free_shipping_threshold_paise'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_mode' => ['required', Rule::enum(ShippingMode::class)],
            'flat_rupees' => ['nullable', 'required_if:shipping_mode,flat', Rules::RUPEES],
            'free_above_rupees' => ['nullable', Rules::RUPEES],
            'slabs' => ['array', 'max:'.self::SLAB_ROWS],
            'slabs.*.up_to_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'slabs.*.rupees' => ['nullable', Rules::RUPEES],
            'extra_kg_rupees' => ['nullable', Rules::RUPEES],
        ], [
            '*.regex' => __('Enter an amount in rupees, e.g. 49 or 49.50.'),
            'slabs.*.rupees.regex' => __('Enter an amount in rupees, e.g. 49 or 49.50.'),
            'flat_rupees.required_if' => __('Enter your flat shipping rate.'),
        ]);

        $mode = ShippingMode::from($data['shipping_mode']);
        $slabs = collect($data['slabs'] ?? [])
            ->filter(fn ($row) => filled($row['up_to_grams'] ?? null) && filled($row['rupees'] ?? null))
            ->map(fn ($row) => ['up_to_grams' => (int) $row['up_to_grams'], 'paise' => Money::fromRupees($row['rupees'])])
            ->sortBy('up_to_grams')
            ->values();

        if ($mode === ShippingMode::Weight && $slabs->isEmpty()) {
            throw ValidationException::withMessages(['slabs' => __('Add at least one weight slab.')]);
        }

        if ($slabs->pluck('up_to_grams')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['slabs' => __('Each slab needs a different weight.')]);
        }

        $store = $this->currentStore($request);
        $store->forceFill([
            'shipping_mode' => $mode,
            'shipping_flat_paise' => filled($data['flat_rupees'] ?? null) ? Money::fromRupees($data['flat_rupees']) : null,
            'shipping_slabs' => $slabs->isEmpty() ? null : $slabs->all(),
            'shipping_extra_kg_paise' => filled($data['extra_kg_rupees'] ?? null) ? Money::fromRupees($data['extra_kg_rupees']) : null,
            'shipping_free_above_paise' => filled($data['free_above_rupees'] ?? null) ? Money::fromRupees($data['free_above_rupees']) : null,
        ])->save();

        return back()->with('status', __('Shipping rules saved. They apply to new carts and orders.'));
    }
}

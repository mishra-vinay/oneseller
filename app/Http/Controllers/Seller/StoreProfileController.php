<?php

namespace App\Http\Controllers\Seller;

use App\Services\ImageUploader;
use App\Support\IndianStates;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreProfileController extends SellerController
{
    public function edit(Request $request): View
    {
        return view('seller.store', ['store' => $this->currentStore($request), 'states' => IndianStates::ALL]);
    }

    public function update(Request $request, ImageUploader $images): RedirectResponse
    {
        $store = $this->currentStore($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', Rules::MOBILE],
            ...Rules::address(),
            'gstin' => ['nullable', 'string', 'uppercase', Rules::GSTIN],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], Rules::messages());

        $store->fill($data);

        if ($request->hasFile('logo')) {
            $images->delete($store->logo_path);
            $store->logo_path = $images->store($request->file('logo'), "stores/{$store->id}");
        }

        $store->save();

        return back()->with('status', __('Store profile saved.'));
    }
}

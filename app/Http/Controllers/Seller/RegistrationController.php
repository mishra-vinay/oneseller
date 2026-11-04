<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerRegistration;
use App\Support\IndianStates;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('seller.register', ['states' => IndianStates::ALL]);
    }

    public function store(Request $request, SellerRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', Rules::MOBILE],
            'password' => ['required', 'confirmed', Password::defaults()],
            'store_name' => ['required', 'string', 'max:100'],
            'store_phone' => ['required', Rules::MOBILE],
            ...Rules::address(),
            'gstin' => ['nullable', 'string', 'uppercase', Rules::GSTIN],
        ], Rules::messages());

        $store = $registration->register(
            ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password']],
            [
                'name' => $data['store_name'],
                'phone' => $data['store_phone'],
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode'],
                'gstin' => $data['gstin'] ?? null,
            ],
        );

        Auth::login($store->user);
        $request->session()->regenerate();

        return redirect()->route($store->isApproved() ? 'seller.dashboard' : 'seller.pending');
    }
}

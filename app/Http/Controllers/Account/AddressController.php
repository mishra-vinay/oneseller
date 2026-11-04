<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use App\Support\IndianStates;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses', ['addresses' => $request->user()->addresses, 'states' => IndianStates::ALL, 'editing' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        self::createFrom($request, $request->user());

        return redirect()->route('account.addresses')->with('status', __('Address saved.'));
    }

    public function edit(Request $request, int $address): View
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses,
            'states' => IndianStates::ALL,
            'editing' => $request->user()->addresses()->findOrFail($address),
        ]);
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $model->update(self::validated($request));

        if ($request->boolean('is_default')) {
            self::setDefault($model);
        }

        return redirect()->route('account.addresses')->with('status', __('Address updated.'));
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $wasDefault = $model->is_default;
        $model->delete();

        if ($wasDefault && $next = $request->user()->addresses()->first()) {
            self::setDefault($next);
        }

        return redirect()->route('account.addresses')->with('status', __('Address removed.'));
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        self::setDefault($request->user()->addresses()->findOrFail($address));

        return back()->with('status', __('Default address updated.'));
    }

    /**
     * Shared with checkout, where a new address can be added inline.
     */
    public static function createFrom(Request $request, User $user): Address
    {
        $address = $user->addresses()->create(self::validated($request));

        if ($request->boolean('is_default') || $user->addresses()->count() === 1) {
            self::setDefault($address);
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', Rules::MOBILE],
            ...Rules::address(),
        ], Rules::messages());
    }

    private static function setDefault(Address $address): void
    {
        DB::transaction(function () use ($address) {
            Address::where('user_id', $address->user_id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }
}

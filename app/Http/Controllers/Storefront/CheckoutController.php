<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Exceptions\CartChangedException;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\CartService;
use App\Services\OrderPlacement;
use App\Services\Payments\RazorpayGateway;
use App\Support\IndianStates;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Three steps: shipping address, review grouped by seller, then payment.
 */
class CheckoutController extends Controller
{
    private const SESSION_ADDRESS = 'checkout.address_id';

    public function __construct(private CartService $cart, private Settings $settings, private RazorpayGateway $razorpay) {}

    public function address(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->guardCart()) {
            return $redirect;
        }

        return view('checkout.address', [
            'addresses' => $request->user()->addresses,
            'selected' => $request->session()->get(self::SESSION_ADDRESS),
            'states' => IndianStates::ALL,
        ]);
    }

    public function chooseAddress(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->input('address_id') === 'new') {
            $address = AddressController::createFrom($request, $user);
        } else {
            $id = $request->validate(['address_id' => ['required', Rule::exists('addresses', 'id')->where('user_id', $user->id)]])['address_id'];
            $address = $user->addresses()->findOrFail($id);
        }

        $request->session()->put(self::SESSION_ADDRESS, $address->id);

        return redirect()->route('checkout.review');
    }

    public function review(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->guardCart()) {
            return $redirect;
        }

        $address = $this->chosenAddress($request);

        if (! $address) {
            return redirect()->route('checkout.address');
        }

        return view('checkout.review', [
            'cart' => $this->cart->summary(),
            'address' => $address,
            'methods' => $this->availableMethods(),
        ]);
    }

    public function place(Request $request, OrderPlacement $placement): RedirectResponse
    {
        $methods = $this->availableMethods();

        $method = PaymentMethod::from($request->validate([
            'payment_method' => ['required', Rule::in(array_map(fn ($m) => $m->value, $methods))],
        ], ['payment_method.required' => __('Choose how you would like to pay.')])['payment_method']);

        $address = $this->chosenAddress($request);
        $cart = $this->cart->current();

        if (! $address || ! $cart) {
            return redirect()->route('checkout.address');
        }

        try {
            $order = $placement->place($request->user(), $cart, $address, $method);
        } catch (CartChangedException $e) {
            return redirect()->route('cart')->withErrors(['cart' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_ADDRESS);

        return $method === PaymentMethod::Online
            ? redirect()->route('checkout.pay', $order)
            : redirect()->route('order.show', $order)->with('placed', true);
    }

    private function guardCart(): ?RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['count'] === 0) {
            return redirect()->route('cart');
        }

        if ($summary['has_problems']) {
            return redirect()->route('cart')->withErrors(['cart' => __('Please fix the highlighted items before checking out.')]);
        }

        return null;
    }

    private function chosenAddress(Request $request): ?Address
    {
        $id = $request->session()->get(self::SESSION_ADDRESS);

        return $id ? $request->user()->addresses()->find($id) : null;
    }

    /**
     * @return array<int, PaymentMethod>
     */
    private function availableMethods(): array
    {
        return array_values(array_filter([
            $this->razorpay->isConfigured() ? PaymentMethod::Online : null,
            $this->settings->get('cod_enabled') ? PaymentMethod::Cod : null,
        ]));
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    /** Session key holding the coupon code the buyer applied. */
    public const COUPON = 'cart.coupon';

    public function __construct(private CartService $cart) {}

    public function show(Request $request): View
    {
        return view('storefront.cart', ['cart' => $this->cart->summary(null, $request->session()->get(self::COUPON))]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $code = CouponService::normalise($request->validate(['code' => ['required', 'string', 'max:40']])['code']);
        $summary = $this->cart->summary(null, $code);

        if ($summary['coupon']['error']) {
            return redirect()->route('cart')->withErrors(['code' => $summary['coupon']['error']])->withInput();
        }

        $request->session()->put(self::COUPON, $code);

        return redirect()->route('cart')->with('status', __('Coupon :code applied: :summary.', ['code' => $code, 'summary' => $summary['coupon']['summary']]));
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget(self::COUPON);

        return redirect()->route('cart')->with('status', __('Coupon removed.'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);

        abort_unless(Product::visible()->whereKey($variant->product_id)->exists(), 404);

        if ($variant->stock < 1) {
            return back()->withErrors(['quantity' => __('Sorry, this item is out of stock.')]);
        }

        $this->cart->add($variant, $data['quantity']);

        return redirect()->route('cart')->with('status', __('Added to your cart: :name', ['name' => $variant->product->name]));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']])['quantity'];

        $this->cart->update($this->findItem($item), $quantity);

        return redirect()->route('cart');
    }

    public function remove(int $item): RedirectResponse
    {
        $this->findItem($item)->delete();

        return redirect()->route('cart')->with('status', __('Item removed.'));
    }

    private function findItem(int $id)
    {
        return $this->cart->findItem($id) ?? abort(404);
    }
}

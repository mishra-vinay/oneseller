<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Http\Resources\VariantResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guests keep their cart by sending back the cart_token from the first response in the
 * X-Cart-Token header. Signing in merges it into the buyer's cart.
 * Pass ?coupon=CODE to see the discount.
 */
class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $variant = ProductVariant::findOrFail($data['variant_id']);
        abort_unless(Product::visible()->whereKey($variant->product_id)->exists(), 404);

        if ($variant->stock < 1) {
            throw ValidationException::withMessages(['quantity' => __('Sorry, this item is out of stock.')]);
        }

        $this->cart->add($variant, $data['quantity']);

        return $this->respond($request, 201);
    }

    public function update(Request $request, int $item): JsonResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']])['quantity'];

        $this->cart->update($this->cart->findItem($item) ?? abort(404), $quantity);

        return $this->respond($request);
    }

    public function remove(Request $request, int $item): JsonResponse
    {
        ($this->cart->findItem($item) ?? abort(404))->delete();

        return $this->respond($request);
    }

    private function respond(Request $request, int $status = 200): JsonResponse
    {
        $cart = $this->cart->current();
        $summary = $this->cart->summary($cart, $request->input('coupon'));

        return response()->json([
            // Guests: store this and send it back as X-Cart-Token.
            'cart_token' => $cart && ! $cart->user_id ? $cart->token : null,
            'parcels' => $summary['groups']->map(fn ($group) => [
                'store' => new StoreResource($group['store']),
                'items' => $group['items']->map(fn (CartItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->variant->product_id,
                    'name' => $item->variant->product->name,
                    'image_url' => $item->variant->product->primaryImageUrl(),
                    'variant' => new VariantResource($item->variant),
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                    'problem' => $item->problem,
                ])->values(),
                'subtotal' => $group['subtotal'],
                'shipping' => $group['shipping'],
                'discount' => $group['discount'],
            ])->values(),
            'items_total' => $summary['items_total'],
            'shipping_total' => $summary['shipping_total'],
            'discount_total' => $summary['discount_total'],
            'coupon' => $summary['coupon'],
            'grand_total' => $summary['grand_total'],
            'count' => $summary['count'],
            'has_problems' => $summary['has_problems'],
        ], $status);
    }
}

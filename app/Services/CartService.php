<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * A buyer's cart, or a guest's cart identified by a cookie. A guest cart is merged
 * into the buyer's cart when they log in.
 */
class CartService
{
    public const COOKIE = 'cart_token';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(private Pricing $pricing) {}

    /**
     * Read at call time: this service can outlive a single request (cached controllers, queues).
     */
    private function request(): Request
    {
        return request();
    }

    public function current(): ?Cart
    {
        $user = $this->request()->user();

        if ($user) {
            return Cart::firstWhere('user_id', $user->id);
        }

        $token = $this->request()->cookie(self::COOKIE);

        return $token ? Cart::whereNull('user_id')->firstWhere('token', $token) : null;
    }

    public function currentOrCreate(): Cart
    {
        if ($cart = $this->current()) {
            return $cart;
        }

        if ($user = $this->request()->user()) {
            return Cart::create(['user_id' => $user->id]);
        }

        $token = Str::random(40);
        Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);

        return Cart::create(['token' => $token]);
    }

    public function count(): int
    {
        return (int) $this->current()?->items()->sum('quantity');
    }

    /**
     * Adds to the existing line if present. Quantity is capped at available stock.
     */
    public function add(ProductVariant $variant, int $quantity): CartItem
    {
        $cart = $this->currentOrCreate();
        $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
        $item->quantity = min(max(1, ($item->quantity ?? 0) + $quantity), max(1, $variant->stock));
        $item->save();

        return $item;
    }

    public function update(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $item->quantity = min($quantity, max(1, $item->variant->stock));
        $item->save();
    }

    public function findItem(int $id): ?CartItem
    {
        return $this->current()?->items()->with('variant')->find($id);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Moves a guest's cart into the buyer's cart on login.
     */
    public function mergeGuestCart(User $user, ?string $token): void
    {
        if (! $token) {
            return;
        }

        $guest = Cart::whereNull('user_id')->with('items.variant')->firstWhere('token', $token);

        if ($guest) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            foreach ($guest->items as $guestItem) {
                $item = $cart->items()->firstOrNew(['product_variant_id' => $guestItem->product_variant_id]);
                $item->quantity = min(($item->quantity ?? 0) + $guestItem->quantity, max(1, $guestItem->variant->stock));
                $item->save();
            }

            $guest->delete();
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * The cart grouped by seller with per-group shipping. Items that can no longer
     * be bought (unlisted product, out of stock, too few left) are flagged.
     *
     * @return array{groups: Collection<int, array<string, mixed>>, items_total: int, shipping_total: int, grand_total: int, count: int, has_problems: bool}
     */
    public function summary(?Cart $cart = null): array
    {
        $cart ??= $this->current();

        $items = $cart
            ? $cart->items()->with(['variant.product.store', 'variant.product.images'])->orderBy('id')->get()
            : collect();

        $visibleIds = Product::visible()->whereIn('products.id', $items->pluck('variant.product_id'))->pluck('products.id')->flip();

        $groups = $items
            ->each(function (CartItem $item) use ($visibleIds) {
                $variant = $item->variant;
                $item->setAttribute('problem', match (true) {
                    ! $visibleIds->has($variant->product_id) => __('No longer available'),
                    $variant->stock < 1 => __('Out of stock'),
                    $variant->stock < $item->quantity => __('Only :n left', ['n' => $variant->stock]),
                    default => null,
                });
                $item->setAttribute('line_total', $variant->price * $item->quantity);
            })
            ->groupBy(fn (CartItem $item) => $item->variant->product->store_id)
            ->map(function (Collection $groupItems) {
                $buyable = $groupItems->whereNull('problem');
                $subtotal = (int) $buyable->sum('line_total');

                return [
                    'store' => $groupItems->first()->variant->product->store,
                    'items' => $groupItems,
                    'subtotal' => $subtotal,
                    'shipping' => $subtotal > 0 ? $this->pricing->shippingFor($subtotal) : 0,
                ];
            })
            ->values();

        $itemsTotal = (int) $groups->sum('subtotal');
        $shippingTotal = (int) $groups->sum('shipping');

        return [
            'groups' => $groups,
            'items_total' => $itemsTotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $itemsTotal + $shippingTotal,
            'count' => (int) $items->sum('quantity'),
            'has_problems' => $items->contains(fn ($i) => $i->problem !== null),
        ];
    }
}

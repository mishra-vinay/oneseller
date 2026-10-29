<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\CartChangedException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a cart into one order with one sub-order per seller.
 *
 * Runs in a single transaction: product rows are locked, stock is re-checked and
 * reserved, commission rates are snapshotted, and the cart is emptied.
 */
class OrderPlacement
{
    public function __construct(private Pricing $pricing, private OrderEvents $events) {}

    public function place(User $buyer, Cart $cart, Address $address, PaymentMethod $method): Order
    {
        $order = DB::transaction(function () use ($buyer, $cart, $address, $method) {
            $items = $cart->items()->orderBy('product_variant_id')->get();

            if ($items->isEmpty()) {
                throw new CartChangedException(__('Your cart is empty.'));
            }

            $variants = ProductVariant::whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $products = Product::visible()->with('store')->whereIn('products.id', $variants->pluck('product_id'))->get()->keyBy('id');

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);
                $product = $variant ? $products->get($variant->product_id) : null;

                if (! $product || $variant->stock < $item->quantity) {
                    throw new CartChangedException(__('Some items in your cart are no longer available in the quantity you chose. Please review your cart.'));
                }
            }

            $groups = $items->groupBy(fn ($item) => $products->get($variants->get($item->product_variant_id)->product_id)->store_id);
            $online = $method === PaymentMethod::Online;

            $order = Order::create([
                'number' => OrderNumber::next(),
                'buyer_id' => $buyer->id,
                'ship_name' => $address->name,
                'ship_phone' => $address->phone,
                'ship_address_line1' => $address->address_line1,
                'ship_address_line2' => $address->address_line2,
                'ship_city' => $address->city,
                'ship_state' => $address->state,
                'ship_pincode' => $address->pincode,
                'items_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Pending,
                'status' => $online ? OrderStatus::PendingPayment : OrderStatus::Placed,
                'placed_at' => $online ? null : now(),
            ]);

            $itemsTotal = 0;
            $shippingTotal = 0;
            $suffix = 1;

            foreach ($groups as $storeItems) {
                $store = $products->get($variants->get($storeItems->first()->product_variant_id)->product_id)->store;

                $subtotal = (int) $storeItems->sum(fn ($item) => $variants->get($item->product_variant_id)->price * $item->quantity);
                $shipping = $this->pricing->shippingFor($subtotal);
                $bps = $store->effectiveCommissionBps();
                $commission = Pricing::commission($subtotal, $bps);

                $subOrder = $order->subOrders()->create([
                    'store_id' => $store->id,
                    'number' => $order->number.'-'.$suffix++,
                    'status' => $online ? SubOrderStatus::PendingPayment : SubOrderStatus::Placed,
                    'items_subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'commission_bps' => $bps,
                    'commission_amount' => $commission,
                    'net_payout' => $subtotal - $commission + $shipping,
                ]);

                foreach ($storeItems as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $product = $products->get($variant->product_id);

                    $subOrder->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $product->name,
                        'sku' => $variant->sku,
                        'unit_price' => $variant->price,
                        'quantity' => $item->quantity,
                        'line_total' => $variant->price * $item->quantity,
                    ]);

                    $variant->decrement('stock', $item->quantity);
                }

                $itemsTotal += $subtotal;
                $shippingTotal += $shipping;
            }

            $order->update([
                'items_total' => $itemsTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $itemsTotal + $shippingTotal,
            ]);

            $cart->items()->delete();

            return $order;
        });

        if ($method === PaymentMethod::Cod) {
            $this->events->placed($order);
        }

        return $order;
    }
}

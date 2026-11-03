<?php

namespace Tests\Feature\Api;

use App\Enums\CouponType;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiShoppingTest extends TestCase
{
    use RefreshDatabase, ResetsApiGuards;

    private function buyerToken(?User $buyer = null): array
    {
        $buyer ??= User::factory()->create();
        $buyer->addresses()->create(['name' => 'Asha', 'phone' => '9876543210', 'address_line1' => '4 Lake View', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001', 'is_default' => true]);

        return [$buyer, $buyer->createToken('t')->plainTextToken];
    }

    public function test_catalogue_endpoints(): void
    {
        $parent = Category::factory()->create(['name' => 'Electronics']);
        $child = Category::factory()->create(['name' => 'Audio', 'parent_id' => $parent->id]);
        $speaker = Product::factory()->priced(279900)->create(['category_id' => $child->id, 'name' => 'Bluetooth Speaker']);
        $hidden = Product::factory()->status(ProductStatus::Pending)->create(['category_id' => $child->id]);

        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('money_unit', 'paise')->assertJsonPath('payment_methods', ['cod']);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.name', 'Electronics')->assertJsonPath('data.0.children.0.name', 'Audio');

        $this->getJson('/api/v1/products?category='.$parent->slug)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', 279900)
            ->assertJsonPath('data.0.store.slug', $speaker->store->slug)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
        $this->getJson('/api/v1/products?max=100')->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/products/{$speaker->id}")->assertOk()
            ->assertJsonPath('data.variants.0.price', 279900)
            ->assertJsonPath('data.category.name', 'Audio')
            ->assertJsonStructure(['data' => ['description', 'images', 'option_names']]);
        $this->getJson("/api/v1/products/{$hidden->id}")->assertNotFound();
        $this->getJson("/api/v1/stores/{$speaker->store->slug}")->assertOk()->assertJsonPath('data.name', $speaker->store->name);
        $this->getJson("/api/v1/stores/{$speaker->store->slug}/products")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/products/{$speaker->id}/reviews")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_cart_by_token_merges_on_login(): void
    {
        // ₹800 of items is under the ₹999 free-shipping threshold, so ₹50 shipping applies.
        $product = Product::factory()->priced(40000)->stocked(5)->create();
        $variantId = $product->defaultVariant->id;

        $cartToken = $this->postJson('/api/v1/cart/items', ['variant_id' => $variantId, 'quantity' => 2])
            ->assertCreated()->assertJsonPath('count', 2)->assertJsonPath('parcels.0.shipping', 5000)->json('cart_token');
        $this->assertNotNull($cartToken);

        $this->withHeader('X-Cart-Token', $cartToken)->getJson('/api/v1/cart')->assertJsonPath('grand_total', 85000);

        $buyer = User::factory()->create();
        $login = $this->withHeader('X-Cart-Token', $cartToken)->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'password']);

        $this->withToken($login->json('token'))->getJson('/api/v1/cart')->assertJsonPath('count', 2)->assertJsonPath('cart_token', null);
    }

    public function test_cart_item_updates_and_coupon_preview(): void
    {
        [, $token] = $this->buyerToken();
        $product = Product::factory()->priced(100000)->stocked(3)->create();
        Coupon::create(['code' => 'TEN', 'type' => CouponType::Percent, 'value' => 1000, 'is_active' => true]);
        $this->withToken($token);

        $itemId = $this->postJson('/api/v1/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 9])->json('parcels.0.items.0.id');
        $this->getJson('/api/v1/cart')->assertJsonPath('parcels.0.items.0.quantity', 3);

        $this->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])->assertJsonPath('items_total', 100000);
        $this->getJson('/api/v1/cart?coupon=ten')->assertJsonPath('discount_total', 10000)->assertJsonPath('coupon.code', 'TEN');
        $this->getJson('/api/v1/cart?coupon=nope')->assertJsonPath('coupon.error', 'That coupon code is not valid.');

        $this->deleteJson("/api/v1/cart/items/{$itemId}")->assertJsonPath('count', 0);
        $this->deleteJson('/api/v1/cart/items/'.($itemId + 100))->assertNotFound();
    }

    public function test_cod_checkout_orders_and_isolation(): void
    {
        [$buyer, $token] = $this->buyerToken();
        $a = Product::factory()->priced(60000)->create();
        $b = Product::factory()->priced(40000)->create();
        $this->withToken($token);
        $this->postJson('/api/v1/cart/items', ['variant_id' => $a->defaultVariant->id, 'quantity' => 1]);
        $this->postJson('/api/v1/cart/items', ['variant_id' => $b->defaultVariant->id, 'quantity' => 1]);

        $address = $buyer->addresses()->first()->id;
        $this->postJson('/api/v1/checkout', ['address_id' => $address, 'payment_method' => 'online'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $number = $this->postJson('/api/v1/checkout', ['address_id' => $address, 'payment_method' => 'cod'])
            ->assertCreated()
            ->assertJsonPath('order.status', 'placed')
            ->assertJsonCount(2, 'order.parcels')
            ->assertJsonPath('order.grand_total', 110000)
            ->assertJsonPath('razorpay', null)
            ->json('order.number');

        $this->getJson('/api/v1/orders')->assertJsonPath('data.0.number', $number);
        $this->getJson("/api/v1/orders/{$number}")->assertOk()->assertJsonPath('data.parcels.0.can_return', false);
        $this->getJson('/api/v1/cart')->assertJsonPath('count', 0);
        $this->postJson('/api/v1/checkout', ['address_id' => $address, 'payment_method' => 'cod'])->assertJsonValidationErrors('cart');

        [, $otherToken] = $this->buyerToken(User::factory()->create());
        $this->withToken($otherToken)->getJson("/api/v1/orders/{$number}")->assertNotFound();
        $this->postJson('/api/v1/checkout', ['address_id' => $address, 'payment_method' => 'cod'])->assertJsonValidationErrors('address_id');
    }

    public function test_unverified_buyers_cannot_check_out(): void
    {
        $buyer = User::factory()->unverified()->create();
        [, $token] = $this->buyerToken($buyer);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $buyer->addresses()->first()->id, 'payment_method' => 'cod'])->assertForbidden();
    }

    public function test_online_checkout_and_payment_verification(): void
    {
        config(['services.razorpay' => ['key_id' => 'rzp_test_key', 'key_secret' => 'test_secret', 'webhook_secret' => 'hook']]);
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_APP1'])]);
        [$buyer, $token] = $this->buyerToken();
        $this->withToken($token)->postJson('/api/v1/cart/items', ['variant_id' => Product::factory()->priced(150000)->create()->defaultVariant->id, 'quantity' => 1]);

        $response = $this->postJson('/api/v1/checkout', ['address_id' => $buyer->addresses()->first()->id, 'payment_method' => 'online'])
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending_payment')
            ->assertJsonPath('razorpay.order_id', 'order_APP1')
            ->assertJsonPath('razorpay.key_id', 'rzp_test_key')
            ->assertJsonPath('razorpay.amount', 150000);
        $number = $response->json('order.number');

        $this->postJson("/api/v1/orders/{$number}/payment/retry")->assertJsonPath('razorpay.order_id', 'order_APP1');
        Http::assertSentCount(1);

        $this->postJson("/api/v1/orders/{$number}/payment", ['razorpay_order_id' => 'order_APP1', 'razorpay_payment_id' => 'pay_1', 'razorpay_signature' => 'bad'])
            ->assertJsonValidationErrors('razorpay_signature');
        $this->postJson("/api/v1/orders/{$number}/payment", [
            'razorpay_order_id' => 'order_APP1', 'razorpay_payment_id' => 'pay_1',
            'razorpay_signature' => hash_hmac('sha256', 'order_APP1|pay_1', 'test_secret'),
        ])->assertOk()->assertJsonPath('data.status', 'placed')->assertJsonPath('data.payment_status', 'paid');

        $this->postJson("/api/v1/orders/{$number}/payment/retry")->assertJsonValidationErrors('order');
    }

    public function test_addresses_wishlist_reviews_returns_and_messages(): void
    {
        [$buyer, $token] = $this->buyerToken();
        $store = Store::factory()->create();
        $product = Product::factory()->priced(50000)->create(['store_id' => $store->id]);
        $this->withToken($token);

        $addressId = $this->postJson('/api/v1/addresses', ['name' => 'Office', 'phone' => '9123456789', 'address_line1' => '1 MG Rd', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'pincode' => '560001'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/addresses/{$addressId}", ['is_default' => true])->assertJsonPath('data.is_default', true);
        $this->getJson('/api/v1/addresses')->assertJsonCount(2, 'data');
        $this->deleteJson("/api/v1/addresses/{$addressId}")->assertNoContent();

        $this->postJson("/api/v1/wishlist/{$product->id}")->assertCreated();
        $this->getJson('/api/v1/wishlist')->assertJsonPath('data.0.id', $product->id);
        $this->deleteJson("/api/v1/wishlist/{$product->id}")->assertNoContent();

        $this->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])->assertJsonValidationErrors('rating');

        $this->postJson('/api/v1/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
        $number = $this->postJson('/api/v1/checkout', ['address_id' => $buyer->addresses()->first()->id, 'payment_method' => 'cod'])->json('order.number');
        $order = Order::firstWhere('number', $number);
        $parcel = $order->subOrders->first();
        $parcel->update(['status' => SubOrderStatus::Delivered, 'delivered_at' => now()]);

        $this->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 4, 'body' => 'Nice'])->assertCreated()->assertJsonPath('data.rating', 4);
        $this->getJson("/api/v1/orders/{$number}")->assertJsonPath('data.parcels.0.can_return', true);

        $returnNumber = $this->postJson("/api/v1/orders/{$number}/parcels/{$parcel->id}/returns", [
            'items' => [$parcel->items->first()->id => 1], 'type' => 'refund', 'reason' => 'damaged',
        ])->assertCreated()->assertJsonPath('data.refund_amount', 50000)->json('data.number');
        $this->getJson('/api/v1/returns')->assertJsonPath('data.0.number', $returnNumber);
        $this->getJson("/api/v1/returns/{$returnNumber}")->assertJsonPath('data.items.0.quantity', 1);

        $conversationId = $this->postJson("/api/v1/stores/{$store->slug}/messages", ['body' => 'Where is my parcel?', 'parcel_id' => $parcel->id])
            ->assertCreated()->assertJsonPath('data.with', $store->name)->json('data.id');
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Any update?'])->assertJsonCount(2, 'data.messages');
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.id', $conversationId);

        $sellerToken = $store->user->createToken('t')->plainTextToken;
        $this->withToken($sellerToken)->getJson("/api/v1/conversations/{$conversationId}")->assertOk()->assertJsonPath('data.with', $buyer->name)->assertJsonPath('data.messages.0.mine', false);
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Shipped today'])->assertJsonPath('data.messages.2.mine', true);
    }
}

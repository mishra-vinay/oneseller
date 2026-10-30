<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewOrderAlert;
use App\Notifications\NewSubOrder;
use App\Notifications\OrderConfirmation;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    public function test_checkout_requires_login_and_a_verified_email(): void
    {
        $this->get('/checkout')->assertRedirect('/login');

        $this->actingAs(User::factory()->unverified()->create())->get('/checkout')->assertRedirect('/email/verify');
    }

    public function test_one_payment_creates_one_order_with_a_sub_order_per_seller(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alpha = Store::factory()->create(['name' => 'Alpha']);
        $beta = Store::factory()->create(['name' => 'Beta', 'commission_bps' => 500]);
        $a1 = Product::factory()->priced(60000)->stocked(5)->create(['store_id' => $alpha->id]);
        $a2 = Product::factory()->priced(50000)->stocked(5)->create(['store_id' => $alpha->id]);
        $b1 = Product::factory()->priced(30000)->stocked(5)->create(['store_id' => $beta->id]);
        $buyer = $this->buyerWithAddress();

        $this->actingAs($buyer);
        $this->post('/cart/items', ['variant_id' => $a1->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/items', ['variant_id' => $a2->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/items', ['variant_id' => $b1->defaultVariant->id, 'quantity' => 2]);
        $this->get('/checkout')->assertOk()->assertSee('4 Lake View');
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);
        $this->get('/checkout/review')->assertOk()->assertSee('Alpha')->assertSee('Beta')->assertSee('Cash on Delivery');
        $this->post('/checkout/place', ['payment_method' => 'cod'])->assertRedirect('/order/OS-'.now()->year.'-000001');

        $order = Order::with('subOrders.items')->firstOrFail();
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('Pune', $order->ship_city);
        // Alpha ₹1,100 (free shipping ≥ ₹999), Beta ₹600 + ₹50 shipping.
        $this->assertSame(170000, $order->items_total);
        $this->assertSame(5000, $order->shipping_total);
        $this->assertSame(175000, $order->grand_total);

        [$subA, $subB] = $order->subOrders;
        $this->assertSame($order->number.'-1', $subA->number);
        $this->assertSame($order->number.'-2', $subB->number);
        $this->assertSame(SubOrderStatus::Placed, $subA->status);
        $this->assertSame([110000, 0, 1000, 11000, 99000], [$subA->items_subtotal, $subA->shipping, $subA->commission_bps, $subA->commission_amount, $subA->net_payout]);
        $this->assertSame([60000, 5000, 500, 3000, 62000], [$subB->items_subtotal, $subB->shipping, $subB->commission_bps, $subB->commission_amount, $subB->net_payout]);
        $this->assertCount(2, $subA->items);

        $this->assertSame(4, $a1->defaultVariant->fresh()->stock);
        $this->assertSame(3, $b1->defaultVariant->fresh()->stock);
        $this->assertSame(0, Cart::firstWhere('user_id', $buyer->id)->items()->count());

        $this->get("/order/{$order->number}")->assertOk()->assertSee('Thank you')->assertSee($subA->number)->assertSee($subB->number);

        Notification::assertSentTo($buyer, OrderConfirmation::class);
        Notification::assertSentTo($alpha->user, NewSubOrder::class, fn ($n) => $n->subOrder->is($subA));
        Notification::assertSentTo($beta->user, NewSubOrder::class, fn ($n) => $n->subOrder->is($subB));
        Notification::assertNotSentTo($alpha->user, NewSubOrder::class, fn ($n) => $n->subOrder->is($subB));
        Notification::assertSentTo($admin, NewOrderAlert::class);
    }

    public function test_commission_rate_is_snapshotted_at_order_time(): void
    {
        $product = Product::factory()->priced(100000)->create();
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);

        app(Settings::class)->set(['default_commission_bps' => 2500]);

        $this->assertSame(1000, $order->subOrders->first()->commission_bps);
        $this->assertSame(10000, $order->subOrders->first()->commission_amount);
    }

    public function test_stock_that_ran_out_sends_the_buyer_back_to_the_cart(): void
    {
        $product = Product::factory()->stocked(2)->create();
        $buyer = $this->buyerWithAddress();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 2]);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);

        $product->defaultVariant->update(['stock' => 1]);

        $this->post('/checkout/place', ['payment_method' => 'cod'])->assertRedirect('/cart')->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->defaultVariant->fresh()->stock);
    }

    public function test_new_address_can_be_added_during_checkout(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $this->post('/checkout/address', [
            'address_id' => 'new', 'name' => 'Asha', 'phone' => '9876543210', 'address_line1' => '1 Main Rd',
            'city' => 'Kochi', 'state' => 'Kerala', 'pincode' => '682001',
        ])->assertRedirect('/checkout/review');

        $this->assertTrue($buyer->addresses()->first()->is_default);
    }

    public function test_cod_can_be_switched_off(): void
    {
        app(Settings::class)->set(['cod_enabled' => false]);
        $product = Product::factory()->create();
        $buyer = $this->buyerWithAddress();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);

        $this->post('/checkout/place', ['payment_method' => 'cod'])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_buyers_cannot_see_each_others_orders(): void
    {
        $order = $this->checkout($this->buyerWithAddress(), [[Product::factory()->create(), 1]]);

        $this->actingAs(User::factory()->create())->get("/order/{$order->number}")->assertNotFound();
    }

    public function test_order_history_lists_each_sellers_portion(): void
    {
        $buyer = $this->buyerWithAddress();
        $order = $this->checkout($buyer, [[Product::factory()->create(), 1], [Product::factory()->create(), 1]]);

        $this->get('/account/orders')->assertOk()->assertSee($order->number)
            ->assertSee($order->subOrders[0]->store->name)
            ->assertSee($order->subOrders[1]->store->name);
    }
}

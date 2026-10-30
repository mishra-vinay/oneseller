<?php

namespace Tests\Feature\Seller;

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A seller must never reach another seller's products or orders, even by editing the URL.
 */
class SellerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_cannot_touch_another_sellers_products(): void
    {
        $mine = Store::factory()->create();
        $theirs = Product::factory()->create();

        $this->actingAs($mine->user);

        $this->get('/seller/products')->assertOk()->assertDontSee($theirs->name);
        $this->get('/seller/inventory')->assertOk()->assertDontSee($theirs->name);
        $this->get("/seller/products/{$theirs->id}/edit")->assertNotFound();
        $this->put("/seller/products/{$theirs->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->delete("/seller/products/{$theirs->id}")->assertNotFound();
        $this->put('/seller/inventory', ['rows' => [$theirs->id => ['price' => '1', 'stock' => '0']]]);

        $this->assertModelExists($theirs);
        $this->assertNotSame('Hacked', $theirs->fresh()->name);
        $this->assertSame(10, $theirs->defaultVariant->fresh()->stock);
    }

    public function test_seller_cannot_see_or_act_on_another_sellers_orders(): void
    {
        $mine = Store::factory()->create();
        $other = Store::factory()->create();
        $order = Order::create([
            'number' => 'OS-2026-000001', 'buyer_id' => User::factory()->create()->id,
            'ship_name' => 'B', 'ship_phone' => '9876543210', 'ship_address_line1' => 'x', 'ship_city' => 'c', 'ship_state' => 'Goa', 'ship_pincode' => '403001',
            'items_total' => 100, 'shipping_total' => 0, 'grand_total' => 100, 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'placed',
        ]);
        $theirs = SubOrder::create([
            'order_id' => $order->id, 'store_id' => $other->id, 'number' => 'OS-2026-000001-1', 'status' => SubOrderStatus::Placed,
            'items_subtotal' => 100, 'shipping' => 0, 'commission_bps' => 1000, 'commission_amount' => 10, 'net_payout' => 90,
        ]);

        $this->actingAs($mine->user);

        $this->get('/seller/orders')->assertOk()->assertDontSee('OS-2026-000001-1');
        $this->get("/seller/orders/{$theirs->id}")->assertNotFound();
        $this->post("/seller/orders/{$theirs->id}/accept")->assertNotFound();
        $this->post("/seller/orders/{$theirs->id}/cancel", ['reason' => 'x'])->assertNotFound();
        $this->get('/seller/customers')->assertOk()->assertDontSee($order->buyer->name);

        $this->assertSame(SubOrderStatus::Placed, $theirs->fresh()->status);
    }
}

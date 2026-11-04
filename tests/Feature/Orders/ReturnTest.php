<?php

namespace Tests\Feature\Orders;

use App\Enums\CouponType;
use App\Enums\LedgerType;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\ReturnRequested;
use App\Notifications\ReturnUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private Store $store;

    private Product $product;

    private Order $order;

    private SubOrder $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->product = Product::factory()->priced(50000)->stocked(10)->create(['store_id' => $this->store->id]);
        $this->order = $this->checkout($this->buyerWithAddress(), [[$this->product, 3]]);
        $this->sub = $this->order->subOrders->first();

        // Deliver it through the normal flow so the ledger is credited.
        $this->actingAs($this->store->user);
        $this->post("/seller/orders/{$this->sub->id}/accept");
        $this->post("/seller/orders/{$this->sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);
        $this->post("/seller/orders/{$this->sub->id}/deliver");
        $this->sub->refresh();
    }

    private function requestReturn(int $quantity, string $type = 'refund')
    {
        $item = $this->sub->items->first();

        return $this->actingAs($this->order->buyer)->post("/order/{$this->order->number}/parcels/{$this->sub->id}/return", [
            'items' => [$item->id => $quantity], 'type' => $type, 'reason' => 'damaged', 'details' => 'Cracked lid',
        ]);
    }

    public function test_full_refund_flow_adjusts_stock_ledger_and_order(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->order->buyer)->get("/order/{$this->order->number}")->assertSee('Return or replace');
        $this->get("/order/{$this->order->number}/parcels/{$this->sub->id}/return")->assertOk()->assertSee('Which items?');

        $this->requestReturn(2)->assertRedirect();
        $return = ReturnRequest::firstOrFail();
        $this->assertSame($this->sub->number.'-R1', $return->number);
        $this->assertSame(100000, $return->refund_amount);
        $this->assertSame(ReturnStatus::Requested, $return->status);
        Notification::assertSentTo([$this->store->user, $admin], ReturnRequested::class);

        $this->actingAs($this->store->user)->get('/seller/returns')->assertOk()->assertSee($return->number);
        $this->get("/seller/returns/{$return->number}")->assertOk()->assertSee('Cracked lid');
        $this->post("/seller/returns/{$return->number}/approve", ['note' => 'Ship to our Pune warehouse']);
        $this->post("/seller/returns/{$return->number}/receive", ['restock' => '1']);
        $this->assertSame(9, $this->product->defaultVariant->fresh()->stock, '7 left after the sale, 2 back');

        $balanceBefore = $this->store->balance();
        $this->actingAs($admin)->get("/admin/returns/{$return->number}")->assertOk();
        $this->post("/admin/returns/{$return->number}/refund")->assertSessionHas('status');

        $return->refresh();
        $this->assertSame(ReturnStatus::Refunded, $return->status);
        $entries = $this->store->ledgerEntries()->orderByDesc('id')->limit(2)->get()->reverse()->values();
        $this->assertSame([LedgerType::Refund, LedgerType::Commission], $entries->pluck('type')->all());
        $this->assertSame([-100000, 10000], $entries->pluck('amount')->all());
        $this->assertSame($balanceBefore - 90000, $this->store->balance());

        $order = $this->order->fresh();
        $this->assertSame(100000, $order->refunded_total);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        Notification::assertSentTo($this->order->buyer, ReturnUpdated::class, fn ($n) => $n->return->status === ReturnStatus::Refunded);

        $this->actingAs($this->order->buyer)->get("/account/returns/{$return->number}")->assertOk()->assertSee('Refunded');
    }

    public function test_cannot_return_more_than_bought_or_twice(): void
    {
        $this->requestReturn(4)->assertSessionHasErrors('items');
        $this->requestReturn(2);
        $this->requestReturn(2)->assertSessionHasErrors('items');
        $this->requestReturn(1)->assertSessionHasNoErrors();

        $this->requestReturn(0)->assertSessionHasErrors('items');
    }

    public function test_rejected_items_can_be_requested_again_and_admin_can_overrule(): void
    {
        $this->requestReturn(3);
        $return = ReturnRequest::firstOrFail();

        $this->actingAs($this->store->user)->post("/seller/returns/{$return->number}/reject", ['note' => 'Used item'])->assertSessionHasNoErrors();
        $this->assertSame(ReturnStatus::Rejected, $return->fresh()->status);

        $this->actingAs(User::factory()->admin()->create())->post("/admin/returns/{$return->number}/approve");
        $this->assertSame(ReturnStatus::Approved, $return->fresh()->status);
    }

    public function test_window_closes(): void
    {
        $this->travel(8)->days();

        $this->requestReturn(1)->assertSessionHasErrors('items');
        $this->actingAs($this->order->buyer)->get("/order/{$this->order->number}")->assertDontSee('Return or replace');
    }

    public function test_replacement_ships_from_stock(): void
    {
        $this->requestReturn(1, 'replacement');
        $return = ReturnRequest::firstOrFail();

        $this->actingAs($this->store->user);
        $this->post("/seller/returns/{$return->number}/approve");
        $this->post("/seller/returns/{$return->number}/receive");
        $this->assertSame(7, $this->product->defaultVariant->fresh()->stock, 'not restocked');

        $this->post("/seller/returns/{$return->number}/replace", ['courier' => 'BlueDart', 'tracking_number' => 'BD99']);

        $this->assertSame(ReturnStatus::Replaced, $return->fresh()->status);
        $this->assertSame(6, $this->product->defaultVariant->fresh()->stock);
        $this->actingAs($this->order->buyer)->get("/account/returns/{$return->number}")->assertSee('BD99');
    }

    public function test_steps_must_happen_in_order_and_sellers_only_see_their_own(): void
    {
        $this->requestReturn(1);
        $return = ReturnRequest::firstOrFail();

        $this->actingAs($this->store->user)->post("/seller/returns/{$return->number}/receive")->assertSessionHasErrors('status');
        $this->actingAs(User::factory()->admin()->create())->post("/admin/returns/{$return->number}/refund")->assertSessionHasErrors('status');

        $other = Store::factory()->create();
        $this->actingAs($other->user)->get("/seller/returns/{$return->number}")->assertNotFound();
        $this->post("/seller/returns/{$return->number}/approve")->assertNotFound();

        $this->actingAs(User::factory()->create())->get("/account/returns/{$return->number}")->assertNotFound();
    }

    public function test_refund_excludes_the_coupon_share(): void
    {
        Coupon::create(['code' => 'FLAT', 'type' => CouponType::Fixed, 'value' => 30000, 'is_active' => true]);
        $buyer = User::factory()->create(['email' => 'c@example.com']);
        $buyer->addresses()->create(['name' => 'C', 'phone' => '9876543210', 'address_line1' => 'x', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $this->product->defaultVariant->id, 'quantity' => 3]);
        $this->post('/cart/coupon', ['code' => 'FLAT']);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);
        $this->post('/checkout/place', ['payment_method' => 'cod']);
        $sub = $buyer->orders()->first()->subOrders->first();
        $sub->update(['status' => 'delivered', 'delivered_at' => now()]);

        $this->post("/order/{$sub->order->number}/parcels/{$sub->id}/return", ['items' => [$sub->items->first()->id => 1], 'type' => 'refund', 'reason' => 'size_fit']);

        // ₹500 item, ₹300 coupon over ₹1,500 of items → ₹100 share not refunded.
        $this->assertSame(40000, ReturnRequest::firstOrFail()->refund_amount);
    }
}

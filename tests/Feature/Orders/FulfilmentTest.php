<?php

namespace Tests\Feature\Orders;

use App\Enums\LedgerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\SubOrderCancelled;
use App\Notifications\SubOrderDelivered;
use App\Notifications\SubOrderShipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private Store $alpha;

    private Store $beta;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Store::factory()->create();
        $this->beta = Store::factory()->create();
        $this->order = $this->checkout($this->buyerWithAddress(), [
            [Product::factory()->priced(100000)->stocked(5)->create(['store_id' => $this->alpha->id]), 1],
            [Product::factory()->priced(40000)->stocked(5)->create(['store_id' => $this->beta->id]), 2],
        ]);
    }

    private function portion(Store $store)
    {
        return $this->order->subOrders()->where('store_id', $store->id)->firstOrFail();
    }

    public function test_seller_fulfils_their_portion_at_their_own_pace(): void
    {
        Notification::fake();
        $sub = $this->portion($this->alpha);

        $this->actingAs($this->alpha->user)->get('/seller/orders')->assertOk()->assertSee($sub->number);
        $this->get("/seller/orders/{$sub->id}")->assertOk()->assertSee('4 Lake View')->assertSee('9876543210')->assertDontSee('asha@example.com');

        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'Delhivery', 'tracking_number' => 'DL1'])->assertSessionHasErrors('status');
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => '', 'tracking_number' => ''])->assertSessionHasErrors(['courier', 'tracking_number']);
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'Delhivery', 'tracking_number' => 'DL123']);
        Notification::assertSentTo($this->order->buyer, SubOrderShipped::class);

        $this->post("/seller/orders/{$sub->id}/deliver");
        Notification::assertSentTo($this->order->buyer, SubOrderDelivered::class);

        $this->assertSame(SubOrderStatus::Delivered, $sub->fresh()->status);
        $this->assertSame(SubOrderStatus::Placed, $this->portion($this->beta)->status, 'the other seller is unaffected');
        $this->assertSame(OrderStatus::Placed, $this->order->fresh()->status, 'not complete until every seller delivers');

        $this->actingAs($this->order->buyer)->get("/order/{$this->order->number}")->assertSee('DL123')->assertSee('Delhivery');
    }

    public function test_cod_delivery_posts_sale_commission_and_cash_collected_to_the_ledger(): void
    {
        $sub = $this->portion($this->alpha);
        $this->actingAs($this->alpha->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'BlueDart', 'tracking_number' => 'BD1']);
        $this->post("/seller/orders/{$sub->id}/deliver");

        $entries = $this->alpha->ledgerEntries()->orderBy('id')->get();
        $this->assertSame([LedgerType::Sale, LedgerType::Commission, LedgerType::CodCollected], $entries->pluck('type')->all());
        // ₹1,000 sale with free shipping, 10% commission; the courier collected the ₹1,000 cash.
        $this->assertSame([100000, -10000, -100000], $entries->pluck('amount')->all());
        $this->assertSame(-10000, $this->alpha->balance(), 'seller owes the commission');

        $this->get('/seller/earnings')->assertOk()->assertSee('COD collected by seller');
    }

    public function test_order_completes_when_every_seller_has_delivered_and_cod_is_then_paid(): void
    {
        foreach ([$this->alpha, $this->beta] as $store) {
            $sub = $this->portion($store);
            $this->actingAs($store->user);
            $this->post("/seller/orders/{$sub->id}/accept");
            $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T'.$store->id]);
            $this->post("/seller/orders/{$sub->id}/deliver");
        }

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_seller_cancelling_their_portion_returns_stock_and_leaves_the_rest(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sub = $this->portion($this->beta);
        $variant = $sub->items->first()->variant;
        $this->assertSame(3, $variant->stock);

        $this->actingAs($this->beta->user)->post("/seller/orders/{$sub->id}/cancel", ['reason' => 'Out of stock at warehouse']);

        $this->assertSame(SubOrderStatus::Cancelled, $sub->fresh()->status);
        $this->assertSame('Out of stock at warehouse', $sub->fresh()->cancel_reason);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(SubOrderStatus::Placed, $this->portion($this->alpha)->status);
        $this->assertSame(OrderStatus::Placed, $this->order->fresh()->status);
        Notification::assertSentTo([$this->order->buyer, $this->beta->user, $admin], SubOrderCancelled::class);

        $this->post("/seller/orders/{$sub->id}/cancel", ['reason' => 'again'])->assertSessionHasErrors('reason');
    }

    public function test_shipped_portions_cannot_be_cancelled(): void
    {
        $sub = $this->portion($this->alpha);
        $this->actingAs($this->alpha->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);

        $this->post("/seller/orders/{$sub->id}/cancel", ['reason' => 'Changed mind'])->assertSessionHasErrors('reason');
        $this->assertSame(SubOrderStatus::Shipped, $sub->fresh()->status);
    }

    public function test_order_is_cancelled_when_every_portion_is(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->order->subOrders as $sub) {
            $this->actingAs($admin)->post("/admin/orders/{$this->order->number}/portions/{$sub->id}/cancel", ['reason' => 'Fraud check failed']);
        }

        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
    }

    public function test_admin_records_a_refund_for_a_cancelled_paid_portion(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order->update(['payment_method' => 'online', 'payment_status' => PaymentStatus::Paid]);
        $sub = $this->portion($this->beta);

        $this->actingAs($admin)->get("/admin/orders/{$this->order->number}")->assertOk()->assertSee($sub->number);
        $this->post("/admin/orders/{$this->order->number}/portions/{$sub->id}/refund")->assertSessionHasErrors('reason');

        $this->post("/admin/orders/{$this->order->number}/portions/{$sub->id}/cancel", ['reason' => 'Seller unresponsive']);
        $this->post("/admin/orders/{$this->order->number}/portions/{$sub->id}/refund")->assertSessionHas('status');

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(85000, $order->refunded_total);
        $this->assertNotNull($sub->fresh()->refunded_at);
        $this->assertSame('admin', $sub->fresh()->cancelled_by);
    }

    public function test_admin_records_payouts_against_the_ledger(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order->update(['payment_method' => 'online', 'payment_status' => PaymentStatus::Paid]);
        $sub = $this->portion($this->alpha);
        $this->actingAs($this->alpha->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);
        $this->post("/seller/orders/{$sub->id}/deliver");
        $this->assertSame(90000, $this->alpha->balance());

        $this->actingAs($admin)->get("/admin/ledger?store={$this->alpha->slug}")->assertOk()->assertSee('Sale credited');
        $this->post("/admin/ledger/{$this->alpha->slug}", ['type' => 'payout', 'amount' => '900', 'note' => 'NEFT UTR123'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->alpha->balance());

        $this->post("/admin/ledger/{$this->alpha->slug}", ['type' => 'adjustment', 'direction' => 'credit', 'amount' => '25', 'note' => 'Goodwill']);
        $this->assertSame(2500, $this->alpha->balance());
    }
}

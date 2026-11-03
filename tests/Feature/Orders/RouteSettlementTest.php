<?php

namespace Tests\Feature\Orders;

use App\Enums\LedgerType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteSettlementTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private Store $linked;

    private Store $manual;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay' => ['key_id' => 'rzp_test', 'key_secret' => 'secret', 'webhook_secret' => 'hook']]);
        app(Settings::class)->set(['route_settlement_enabled' => true]);

        $this->linked = Store::factory()->create();
        $this->linked->forceFill(['razorpay_account_id' => 'acc_LinkedSeller01'])->save();
        $this->manual = Store::factory()->create();

        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_R1']),
            'api.razorpay.com/v1/payments/pay_R1/transfers' => fn (Request $r) => Http::response(['items' => collect($r['transfers'])->map(fn ($t, $i) => [
                'id' => 'trf_'.$i, 'notes' => $t['notes'],
            ])->all()]),
            'api.razorpay.com/v1/transfers/*/reversals' => Http::response(['id' => 'rvrsl_1']),
            'api.razorpay.com/v1/transfers/*' => Http::response(['id' => 'trf_0', 'on_hold' => 0]),
        ]);
    }

    private function paidOnlineOrder(): Order
    {
        $order = $this->checkout($this->buyerWithAddress(), [
            [Product::factory()->priced(100000)->create(['store_id' => $this->linked->id]), 1],
            [Product::factory()->priced(50000)->create(['store_id' => $this->manual->id]), 1],
        ], 'online');

        $this->get("/checkout/pay/{$order->number}");
        $this->post("/checkout/pay/{$order->number}", [
            'razorpay_order_id' => 'order_R1', 'razorpay_payment_id' => 'pay_R1',
            'razorpay_signature' => hash_hmac('sha256', 'order_R1|pay_R1', 'secret'),
        ]);

        return $order->fresh('subOrders');
    }

    public function test_payment_creates_held_transfers_for_linked_sellers_only(): void
    {
        $order = $this->paidOnlineOrder();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/payments/pay_R1/transfers')
            && count($r['transfers']) === 1
            && $r['transfers'][0]['account'] === 'acc_LinkedSeller01'
            && $r['transfers'][0]['amount'] === 90000
            && $r['transfers'][0]['on_hold'] === 1);

        $linkedSub = $order->subOrders->firstWhere('store_id', $this->linked->id);
        $this->assertSame('trf_0', $linkedSub->razorpay_transfer_id);
        $this->assertSame('on_hold', $linkedSub->transfer_status);
        $this->assertNull($order->subOrders->firstWhere('store_id', $this->manual->id)->transfer_status);
    }

    public function test_delivery_releases_the_transfer_and_records_the_payout(): void
    {
        $sub = $this->paidOnlineOrder()->subOrders->firstWhere('store_id', $this->linked->id);

        $this->actingAs($this->linked->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);
        $this->post("/seller/orders/{$sub->id}/deliver");

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/transfers/trf_0') && $r['on_hold'] === 0);
        $this->assertSame('released', $sub->fresh()->transfer_status);
        $this->assertSame([LedgerType::Sale, LedgerType::Commission, LedgerType::Payout], $this->linked->ledgerEntries()->orderBy('id')->pluck('type')->all());
        $this->assertSame(0, $this->linked->balance(), 'paid out in full automatically');
    }

    public function test_cancellation_reverses_the_held_transfer(): void
    {
        $sub = $this->paidOnlineOrder()->subOrders->firstWhere('store_id', $this->linked->id);

        $this->actingAs($this->linked->user)->post("/seller/orders/{$sub->id}/cancel", ['reason' => 'Out of stock']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/transfers/trf_0/reversals') && $r['amount'] === 90000);
        $this->assertSame('reversed', $sub->fresh()->transfer_status);
    }

    public function test_disabled_setting_and_cod_orders_skip_route(): void
    {
        app(Settings::class)->set(['route_settlement_enabled' => false]);
        $this->paidOnlineOrder();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/transfers'));

        app(Settings::class)->set(['route_settlement_enabled' => true]);
        $codBuyer = User::factory()->create(['email' => 'cod@example.com']);
        $codBuyer->addresses()->create(['name' => 'C', 'phone' => '9876543210', 'address_line1' => 'x', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $this->checkout($codBuyer, [[Product::factory()->create(['store_id' => $this->linked->id]), 1]]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/transfers'));
    }

    public function test_a_failed_transfer_never_blocks_the_order(): void
    {
        // A fresh fake: the first matching stub wins, so setUp's success stub must go.
        Http::swap(new Factory);
        Http::fake(['api.razorpay.com/v1/payments/*/transfers' => Http::response(['error' => ['description' => 'Route not enabled']], 400), '*' => Http::response(['id' => 'order_R1'])]);

        $order = $this->paidOnlineOrder();

        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('failed', $order->subOrders->firstWhere('store_id', $this->linked->id)->transfer_status);
        $this->assertDatabaseHas('activity_log', ['event' => 'route.failed']);
    }

    public function test_admin_sets_the_linked_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put("/admin/sellers/{$this->manual->slug}/razorpay-account", ['razorpay_account_id' => 'bad'])->assertSessionHasErrors('razorpay_account_id');
        $this->put("/admin/sellers/{$this->manual->slug}/razorpay-account", ['razorpay_account_id' => 'acc_Abc123Def456'])->assertSessionHasNoErrors();
        $this->assertSame('acc_Abc123Def456', $this->manual->fresh()->razorpay_account_id);
    }
}

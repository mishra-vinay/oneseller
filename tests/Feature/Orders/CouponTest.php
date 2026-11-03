<?php

namespace Tests\Feature\Orders;

use App\Enums\CouponType;
use App\Enums\LedgerType;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create([
            'code' => 'SAVE10', 'type' => CouponType::Percent, 'value' => 1000, 'per_user_limit' => 1, 'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     */
    private function checkoutWithCoupon(User $buyer, array $lines, string $code, string $method = 'cod')
    {
        $this->actingAs($buyer);

        foreach ($lines as [$product, $quantity]) {
            $this->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => $quantity]);
        }

        $this->post('/cart/coupon', ['code' => $code]);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);

        return $this->post('/checkout/place', ['payment_method' => $method]);
    }

    public function test_admin_creates_and_edits_coupons(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/coupons/create')->assertOk();
        $this->post('/admin/coupons', [
            'code' => 'diwali-20', 'type' => 'percent', 'value' => '20', 'max_discount_rupees' => '500',
            'min_order_rupees' => '999', 'usage_limit' => '100', 'per_user_limit' => '1', 'ends_at' => '2030-11-15', 'is_active' => '1',
        ])->assertRedirect('/admin/coupons');

        $coupon = Coupon::firstOrFail();
        $this->assertSame('DIWALI-20', $coupon->code);
        $this->assertSame(2000, $coupon->value);
        $this->assertSame(50000, $coupon->max_discount_paise);
        $this->assertSame('2030-11-15 23:59:59', $coupon->ends_at->format('Y-m-d H:i:s'));
        $this->get('/admin/coupons')->assertOk()->assertSee('DIWALI-20')->assertSee('20% off (up to ₹500.00)');

        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'DIWALI-20', 'type' => 'fixed', 'value' => '150'])->assertRedirect();
        $this->assertSame(15000, $coupon->fresh()->value);
        $this->assertFalse($coupon->fresh()->is_active);

        $this->post('/admin/coupons', ['code' => 'DIWALI-20', 'type' => 'fixed', 'value' => '1'])->assertSessionHasErrors('code');
        $this->post('/admin/coupons', ['code' => 'ZERO', 'type' => 'fixed', 'value' => '0'])->assertSessionHasErrors('value');
    }

    public function test_discount_rules(): void
    {
        $service = app(CouponService::class);
        $subtotals = [1 => 300000, 2 => 100000];

        $this->coupon(['code' => 'PCT', 'value' => 1000, 'max_discount_paise' => 25000]);
        $this->coupon(['code' => 'FIX', 'type' => CouponType::Fixed, 'value' => 999999]);
        $this->coupon(['code' => 'MIN', 'type' => CouponType::Fixed, 'value' => 5000, 'min_order_paise' => 500000]);
        $this->coupon(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        $this->coupon(['code' => 'SOON', 'starts_at' => now()->addDay()]);
        $this->coupon(['code' => 'OFF', 'is_active' => false]);

        $this->assertSame(25000, $service->evaluate('pct', $subtotals, null)['discount'], 'capped at the maximum');
        $this->assertSame(400000, $service->evaluate('FIX', $subtotals, null)['discount'], 'never more than the items');
        $this->assertStringContainsString('₹1,000.00 more', $service->evaluate('MIN', $subtotals, null)['error']);
        $this->assertSame('That coupon has expired.', $service->evaluate('OLD', $subtotals, null)['error']);
        $this->assertSame('That coupon is not active yet.', $service->evaluate('SOON', $subtotals, null)['error']);
        $this->assertSame('That coupon code is not valid.', $service->evaluate('OFF', $subtotals, null)['error']);
        $this->assertSame('That coupon code is not valid.', $service->evaluate('NOPE', $subtotals, null)['error']);
        $this->assertSame([1 => 18750, 2 => 6250], $service->evaluate('PCT', $subtotals, null)['allocation'], 'shared by subtotal');
    }

    public function test_coupon_applies_at_checkout_and_is_shared_across_parcels(): void
    {
        $alpha = Store::factory()->create();
        $beta = Store::factory()->create();
        $a = Product::factory()->priced(150000)->create(['store_id' => $alpha->id]);
        $b = Product::factory()->priced(50000)->create(['store_id' => $beta->id]);
        $buyer = $this->buyerWithAddress();
        $this->coupon();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $a->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/items', ['variant_id' => $b->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'save10'])->assertSessionHas('status', 'Coupon SAVE10 applied: 10% off.');
        $this->get('/cart')->assertSee('Coupon SAVE10')->assertSeeInOrder(['Coupon SAVE10', '₹200.00']);

        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);
        $this->get('/checkout/review')->assertSee('Coupon SAVE10');
        $this->post('/checkout/place', ['payment_method' => 'cod']);

        $order = Order::with('subOrders')->firstOrFail();
        // Items ₹2,000; Alpha ships free (≥ ₹999), Beta pays ₹50; 10% off items = ₹200.
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(20000, $order->discount_total);
        $this->assertSame(200000 + 5000 - 20000, $order->grand_total);
        $this->assertSame([15000, 5000], $order->subOrders->pluck('discount')->all());
        $this->assertSame([135000, 50000], [$order->subOrders[0]->net_payout, $order->subOrders[1]->net_payout], 'payout ignores the platform-funded discount');
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => 1, 'order_id' => $order->id, 'discount' => 20000]);
        $this->assertNull(session('cart.coupon'), 'cleared after the order');

        $this->get("/order/{$order->number}")->assertSee('Coupon SAVE10');
    }

    public function test_store_coupon_only_discounts_that_sellers_items(): void
    {
        $alpha = Store::factory()->create(['name' => 'Alpha']);
        $a = Product::factory()->priced(100000)->create(['store_id' => $alpha->id]);
        $b = Product::factory()->priced(100000)->create();
        $this->coupon(['code' => 'ALPHA', 'store_id' => $alpha->id]);

        $this->checkoutWithCoupon($this->buyerWithAddress(), [[$a, 1], [$b, 1]], 'ALPHA');

        $order = Order::with('subOrders')->firstOrFail();
        $this->assertSame(10000, $order->discount_total);
        $this->assertSame(10000, $order->subOrders->firstWhere('store_id', $alpha->id)->discount);
        $this->assertSame(0, $order->subOrders->firstWhere('store_id', '!=', $alpha->id)->discount);

        $this->actingAs(User::factory()->create())->post('/cart/items', ['variant_id' => $b->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'ALPHA'])->assertSessionHasErrors('code');
    }

    public function test_usage_limits_and_cancelled_orders_free_a_use(): void
    {
        $this->coupon(['usage_limit' => 1]);
        $product = Product::factory()->priced(100000)->stocked(10)->create();

        $this->checkoutWithCoupon($this->buyerWithAddress(), [[$product, 1]], 'SAVE10');
        $first = Order::firstOrFail();
        $this->assertSame(10000, $first->discount_total);

        $other = User::factory()->create(['email' => 'second@example.com']);
        $other->addresses()->create(['name' => 'B', 'phone' => '9876543210', 'address_line1' => 'x', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $this->actingAs($other)->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'SAVE10'])->assertSessionHasErrors(['code' => 'That coupon has been fully used.']);

        $first->update(['status' => OrderStatus::Cancelled]);
        $this->post('/cart/coupon', ['code' => 'SAVE10'])->assertSessionHasNoErrors();
    }

    public function test_one_use_per_buyer(): void
    {
        $this->coupon();
        $product = Product::factory()->priced(100000)->stocked(10)->create();
        $buyer = $this->buyerWithAddress();

        $this->checkoutWithCoupon($buyer, [[$product, 1]], 'SAVE10');

        $this->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'SAVE10'])->assertSessionHasErrors(['code' => 'You have already used that coupon.']);
    }

    public function test_coupon_that_stops_working_before_placing_sends_buyer_back_to_cart(): void
    {
        $coupon = $this->coupon();
        $buyer = $this->buyerWithAddress();
        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => Product::factory()->priced(100000)->create()->defaultVariant->id, 'quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'SAVE10']);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);

        $coupon->update(['is_active' => false]);

        $this->post('/checkout/place', ['payment_method' => 'cod'])->assertRedirect('/cart')->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
    }

    public function test_cod_ledger_reflects_the_discounted_cash_collected(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->priced(100000)->create(['store_id' => $store->id]);
        $this->coupon(['type' => CouponType::Fixed, 'value' => 20000]);

        $this->checkoutWithCoupon($this->buyerWithAddress(), [[$product, 1]], 'SAVE10');
        $sub = Order::firstOrFail()->subOrders->first();

        $this->actingAs($store->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);
        $this->post("/seller/orders/{$sub->id}/deliver");

        $entries = $store->ledgerEntries()->orderBy('id')->get();
        $this->assertSame([LedgerType::Sale, LedgerType::Commission, LedgerType::CodCollected], $entries->pluck('type')->all());
        // Sale ₹1,000 credited in full; courier collected ₹800 cash; commission ₹100.
        $this->assertSame([100000, -10000, -80000], $entries->pluck('amount')->all());
        $this->assertSame(10000, $store->balance(), 'platform owes the seller the ₹200 discount less ₹100 commission');
    }

    public function test_refund_of_a_cancelled_portion_uses_what_the_buyer_paid(): void
    {
        $product = Product::factory()->priced(100000)->create();
        $this->coupon(['type' => CouponType::Fixed, 'value' => 20000]);
        $this->checkoutWithCoupon($this->buyerWithAddress(), [[$product, 1]], 'SAVE10');
        $order = Order::firstOrFail();
        $order->update(['payment_method' => 'online', 'payment_status' => 'paid']);
        $sub = $order->subOrders->first();

        $this->actingAs(User::factory()->admin()->create());
        $this->post("/admin/orders/{$order->number}/portions/{$sub->id}/cancel", ['reason' => 'Out of stock']);
        $this->post("/admin/orders/{$order->number}/portions/{$sub->id}/refund");

        $this->assertSame(80000, $order->fresh()->refunded_total);
        $this->assertSame('refunded', $order->fresh()->payment_status->value);
    }
}

<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Store;
use App\Notifications\LowStockDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class SellerPagesTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    public function test_dashboard_customers_and_earnings(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->priced(20000)->stocked(4)->create(['store_id' => $store->id, 'name' => 'Low Stock Lamp']);
        $buyer = $this->buyerWithAddress();
        $this->checkout($buyer, [[$product, 2]]);

        $this->actingAs($store->user)->get('/seller')->assertOk()
            ->assertSee('Orders today')
            ->assertSee('Low Stock Lamp')
            ->assertSee('₹400.00');

        $this->get('/seller/customers')->assertOk()->assertSee('Asha Verma')->assertSee('Pune')->assertDontSee('asha@example.com');
        $this->get('/seller/earnings')->assertOk();
        $this->get('/seller/notifications')->assertOk()->assertSee('New order');
    }

    public function test_low_stock_digest_goes_to_sellers_with_low_stock_only(): void
    {
        Notification::fake();
        $low = Product::factory()->stocked(2)->create();
        $fine = Product::factory()->stocked(50)->create();

        $this->artisan('oneseller:low-stock-digest')->assertSuccessful();

        Notification::assertSentTo($low->store->user, LowStockDigest::class, fn ($n) => $n->items->first()['stock'] === 2);
        Notification::assertNotSentTo($fine->store->user, LowStockDigest::class);
    }
}

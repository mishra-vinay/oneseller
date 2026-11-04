<?php

namespace Tests\Feature\Seller;

use App\Enums\ShippingMode;
use App\Models\Product;
use App\Models\Store;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class ShippingRulesTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private function pricing(): Pricing
    {
        return app(Pricing::class);
    }

    public function test_platform_default_uses_marketplace_settings(): void
    {
        $store = Store::factory()->create();

        $this->assertSame(5000, $this->pricing()->shippingFor($store, 50000, 300));
        $this->assertSame(0, $this->pricing()->shippingFor($store, 99900, 300));
        $this->assertSame('Shipping ₹50.00 · free above ₹999.00', $this->pricing()->describe($store));
    }

    public function test_own_flat_rate_with_and_without_free_threshold(): void
    {
        $store = Store::factory()->create();
        $store->forceFill(['shipping_mode' => ShippingMode::Flat, 'shipping_flat_paise' => 7900])->save();

        $this->assertSame(7900, $this->pricing()->shippingFor($store, 500000, 100), 'no threshold: always charged');

        $store->forceFill(['shipping_free_above_paise' => 200000])->save();
        $this->assertSame(0, $this->pricing()->shippingFor($store, 200000, 100));
        $this->assertSame(7900, $this->pricing()->shippingFor($store, 199999, 100));

        $store->forceFill(['shipping_flat_paise' => 0])->save();
        $this->assertSame('Free shipping', $this->pricing()->describe($store));
    }

    public function test_weight_rate_card_with_extra_kilograms(): void
    {
        $store = Store::factory()->create();
        $store->forceFill([
            'shipping_mode' => ShippingMode::Weight,
            'shipping_slabs' => [['up_to_grams' => 500, 'paise' => 4000], ['up_to_grams' => 2000, 'paise' => 9000]],
            'shipping_extra_kg_paise' => 3000,
        ])->save();

        $this->assertSame(4000, $this->pricing()->shippingFor($store, 1000, 500));
        $this->assertSame(9000, $this->pricing()->shippingFor($store, 1000, 501));
        $this->assertSame(9000 + 3000, $this->pricing()->shippingFor($store, 1000, 2001));
        $this->assertSame(9000 + 2 * 3000, $this->pricing()->shippingFor($store, 1000, 4000));
        $this->assertSame('Shipping from ₹40.00, by weight', $this->pricing()->describe($store));
    }

    public function test_seller_saves_a_rate_card_and_checkout_uses_it(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($store->user)->get('/seller/shipping')->assertOk()->assertSee('Rate card by weight');
        $this->put('/seller/shipping', [
            'shipping_mode' => 'weight',
            'slabs' => [['up_to_grams' => '2000', 'rupees' => '90'], ['up_to_grams' => '500', 'rupees' => '40'], ['up_to_grams' => '', 'rupees' => '']],
            'extra_kg_rupees' => '30',
            'free_above_rupees' => '5000',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame(ShippingMode::Weight, $store->shipping_mode);
        // assertEquals: MySQL 8 does not keep JSON object key order.
        $this->assertEquals([['up_to_grams' => 500, 'paise' => 4000], ['up_to_grams' => 2000, 'paise' => 9000]], $store->shipping_slabs, 'sorted by weight, blanks dropped');
        $this->assertSame(500000, $store->shipping_free_above_paise);

        // 3 × 500 g = 1.5 kg → ₹90 slab.
        $product = Product::factory()->priced(30000)->create(['store_id' => $store->id]);
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 3]]);

        $this->assertSame(9000, $order->subOrders->first()->shipping);
        $this->assertSame(90000 + 9000, $order->grand_total);
        $this->assertSame(90000 - 9000 + 9000, $order->subOrders->first()->net_payout, 'seller keeps the shipping charge');

        $this->get($product->url())->assertSee('Shipping from ₹40.00, by weight · free above ₹5,000.00');
    }

    public function test_validation(): void
    {
        $store = Store::factory()->create();
        $this->actingAs($store->user);

        $this->put('/seller/shipping', ['shipping_mode' => 'weight', 'slabs' => []])->assertSessionHasErrors('slabs');
        $this->put('/seller/shipping', ['shipping_mode' => 'flat'])->assertSessionHasErrors('flat_rupees');
        $this->put('/seller/shipping', ['shipping_mode' => 'weight', 'slabs' => [['up_to_grams' => '500', 'rupees' => '40'], ['up_to_grams' => '500', 'rupees' => '50']]])->assertSessionHasErrors('slabs');
        $this->put('/seller/shipping', ['shipping_mode' => 'nonsense'])->assertSessionHasErrors('shipping_mode');
    }
}

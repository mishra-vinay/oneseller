<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fill_a_cart_capped_at_stock(): void
    {
        $product = Product::factory()->stocked(3)->create();
        $variant = $product->defaultVariant;

        $response = $this->post('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect('/cart');
        $token = $response->getCookie(CartService::COOKIE)?->getValue();
        $this->assertNotNull($token);

        $this->withCookie(CartService::COOKIE, $token)->post('/cart/items', ['variant_id' => $variant->id, 'quantity' => 5]);

        $this->assertSame(3, Cart::firstWhere('token', $token)->items()->first()->quantity);
        $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk()->assertSee($product->name);
    }

    public function test_invisible_or_out_of_stock_products_cannot_be_added(): void
    {
        $hidden = Product::factory()->create(['is_published' => false]);
        $soldOut = Product::factory()->stocked(0)->create();

        $this->post('/cart/items', ['variant_id' => $hidden->defaultVariant->id, 'quantity' => 1])->assertNotFound();
        $this->post('/cart/items', ['variant_id' => $soldOut->defaultVariant->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
    }

    public function test_cart_groups_by_seller_with_shipping_per_group(): void
    {
        // Defaults: ₹50 flat shipping per seller, free from ₹999 per seller.
        $storeA = Store::factory()->create(['name' => 'Alpha Store']);
        $storeB = Store::factory()->create(['name' => 'Beta Store']);
        $a = Product::factory()->priced(60000)->create(['store_id' => $storeA->id]);
        $b = Product::factory()->priced(30000)->create(['store_id' => $storeB->id]);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $a->defaultVariant->id, 'quantity' => 2]);
        $this->post('/cart/items', ['variant_id' => $b->defaultVariant->id, 'quantity' => 1]);

        $summary = app(CartService::class)->summary(Cart::firstWhere('user_id', $buyer->id));

        $this->assertCount(2, $summary['groups']);
        $this->assertSame(120000, $summary['groups']->firstWhere('store.id', $storeA->id)['subtotal']);
        $this->assertSame(0, $summary['groups']->firstWhere('store.id', $storeA->id)['shipping']);
        $this->assertSame(5000, $summary['groups']->firstWhere('store.id', $storeB->id)['shipping']);
        $this->assertSame(155000, $summary['grand_total']);

        $this->get('/cart')->assertSee('Alpha Store')->assertSee('Beta Store')->assertSee('2 separate parcels');
    }

    public function test_update_and_remove_items(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $item = Cart::firstWhere('user_id', $buyer->id)->items()->first();

        $this->patch("/cart/items/{$item->id}", ['quantity' => 4])->assertRedirect('/cart');
        $this->assertSame(4, $item->fresh()->quantity);

        $this->delete("/cart/items/{$item->id}");
        $this->assertModelMissing($item);
    }

    public function test_someone_elses_cart_item_cannot_be_changed(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create())->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $item = Cart::first()->items()->first();

        $this->actingAs(User::factory()->create())->delete("/cart/items/{$item->id}")->assertNotFound();
        $this->assertModelExists($item);
    }

    public function test_guest_cart_carries_over_on_login(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();

        $token = $this->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 2])
            ->getCookie(CartService::COOKIE)->getValue();

        $this->withCookie(CartService::COOKIE, $token)
            ->post('/login', ['email' => $buyer->email, 'password' => 'password']);

        $this->assertSame(2, Cart::firstWhere('user_id', $buyer->id)->items()->first()->quantity);
        $this->assertNull(Cart::firstWhere('token', $token));
    }
}

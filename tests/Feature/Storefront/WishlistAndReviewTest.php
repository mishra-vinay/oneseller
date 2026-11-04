<?php

namespace Tests\Feature\Storefront;

use App\Enums\SubOrderStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class WishlistAndReviewTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    public function test_buyer_saves_and_removes_wishlist_items(): void
    {
        $product = Product::factory()->create(['name' => 'Brass Lamp']);
        $buyer = User::factory()->create();

        $this->post("/wishlist/{$product->id}")->assertRedirect('/login');

        $this->actingAs($buyer)->post("/wishlist/{$product->id}")->assertSessionHas('status', 'Saved to your wishlist.');
        $this->get($product->url())->assertSee('Saved');
        $this->get('/account/wishlist')->assertOk()->assertSee('Brass Lamp');

        $product->update(['is_published' => false]);
        $this->get('/account/wishlist')->assertSee('no longer available');

        $this->post("/wishlist/{$product->id}")->assertSessionHas('status', 'Removed from your wishlist.');
        $this->assertSame(0, $buyer->wishlist()->count());

        $this->post('/wishlist/'.Product::factory()->create(['is_published' => false])->id)->assertNotFound();
    }

    private function deliveredOrderFor(Product $product): User
    {
        $buyer = $this->buyerWithAddress();
        $order = $this->checkout($buyer, [[$product, 1]]);
        $order->subOrders()->update(['status' => SubOrderStatus::Delivered, 'delivered_at' => now()]);

        return $buyer;
    }

    public function test_only_buyers_with_a_delivered_order_can_review_once(): void
    {
        $product = Product::factory()->create();
        $url = "/product/{$product->store->slug}/{$product->slug}/review";

        $this->actingAs(User::factory()->create())->post($url, ['rating' => 5])->assertSessionHasErrors('rating');
        $this->assertSame(0, Review::count());

        $buyer = $this->deliveredOrderFor($product);
        $page = $this->actingAs($buyer)->get($product->url())->assertSee('Rate this product');
        // The review form and the "ask the seller" form must not share field ids.
        $this->assertSame(1, substr_count($page->getContent(), 'id="review-body"'));
        $this->assertSame(1, substr_count($page->getContent(), 'id="question-body"'));
        $this->assertSame(0, substr_count($page->getContent(), 'id="body"'));

        $this->post($url, ['rating' => 4, 'title' => 'Good', 'body' => 'Works well'])->assertRedirect($product->url().'#reviews');
        $this->post($url, ['rating' => 2, 'title' => 'Changed my mind']);

        $this->assertSame(1, Review::count(), 'one review per buyer, updated in place');
        $this->assertSame(2, Review::first()->rating);
        $this->assertSame(1, $product->fresh()->rating_count);
        $this->assertSame(2.0, $product->fresh()->rating_avg);

        $this->post($url, ['rating' => 9])->assertSessionHasErrors('rating');
    }

    public function test_average_rating_shows_on_listing_and_product_page_without_buyer_details(): void
    {
        $product = Product::factory()->create();
        $first = $this->deliveredOrderFor($product);
        $first->update(['name' => 'Asha Verma']);
        $this->actingAs($first)->post("/product/{$product->store->slug}/{$product->slug}/review", ['rating' => 5, 'body' => 'Lovely']);

        $second = User::factory()->create(['email' => 'two@example.com']);
        $second->addresses()->create(['name' => 'Two', 'phone' => '9876543210', 'address_line1' => 'x', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $order = $this->checkout($second, [[$product, 1]]);
        $order->subOrders()->update(['status' => SubOrderStatus::Delivered]);
        $this->actingAs($second)->post("/product/{$product->store->slug}/{$product->slug}/review", ['rating' => 4]);

        $this->assertSame(4.5, $product->fresh()->rating_avg);

        $this->get($product->url())->assertSee('4.5 out of 5')->assertSee('Asha V.')->assertSee('Lovely')->assertDontSee('asha@example.com')->assertDontSee('Asha Verma');
        $this->get('/')->assertSee('(2)');
    }

    public function test_seller_replies_and_admin_hides(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $buyer = $this->deliveredOrderFor($product);
        $this->actingAs($buyer)->post("/product/{$store->slug}/{$product->slug}/review", ['rating' => 1, 'body' => 'Arrived broken']);
        $review = Review::firstOrFail();

        $this->actingAs($store->user)->get('/seller/reviews')->assertOk()->assertSee('Arrived broken');
        $this->post("/seller/reviews/{$review->id}/reply", ['reply' => 'Sorry! We have sent a replacement.']);
        $this->get($product->url())->assertSee('We have sent a replacement');

        $other = Store::factory()->create();
        $this->actingAs($other->user)->post("/seller/reviews/{$review->id}/reply", ['reply' => 'Hijack'])->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/reviews')->assertOk()->assertSee('Arrived broken');
        $this->post("/admin/reviews/{$review->id}/toggle");
        $this->assertTrue($review->fresh()->is_hidden);
        $this->assertSame(0, $product->fresh()->rating_count);
        $this->get($product->url())->assertDontSee('Arrived broken');
    }
}

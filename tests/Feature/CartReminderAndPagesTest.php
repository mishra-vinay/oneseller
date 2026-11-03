<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AbandonedCartReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class CartReminderAndPagesTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private function cartFor(User $buyer): Cart
    {
        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => Product::factory()->create()->defaultVariant->id, 'quantity' => 1]);

        return Cart::firstWhere('user_id', $buyer->id);
    }

    public function test_reminder_after_a_day_once_per_change(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $cart = $this->cartFor($buyer);

        $this->artisan('oneseller:cart-reminders')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(25)->hours();
        $this->artisan('oneseller:cart-reminders');
        Notification::assertSentToTimes($buyer, AbandonedCartReminder::class, 1);

        $this->travel(2)->hours();
        $this->artisan('oneseller:cart-reminders');
        Notification::assertSentToTimes($buyer, AbandonedCartReminder::class, 1);

        // Touching the cart re-arms it.
        $this->actingAs($buyer)->patch('/cart/items/'.$cart->items()->first()->id, ['quantity' => 2]);
        $this->travel(25)->hours();
        $this->artisan('oneseller:cart-reminders');
        Notification::assertSentToTimes($buyer, AbandonedCartReminder::class, 2);
    }

    public function test_no_reminder_for_opted_out_old_or_empty_carts(): void
    {
        Notification::fake();
        $optedOut = User::factory()->create(['cart_reminders' => false]);
        $this->cartFor($optedOut);
        $stale = User::factory()->create();
        $this->cartFor($stale);
        $ordered = $this->buyerWithAddress();
        $this->checkout($ordered, [[Product::factory()->create(), 1]]);

        $this->travel(8)->days();
        $this->artisan('oneseller:cart-reminders');

        Notification::assertNotSentTo([$optedOut, $stale, $ordered], AbandonedCartReminder::class);
    }

    public function test_unsubscribe_link_and_profile_toggle(): void
    {
        $buyer = User::factory()->create();

        $this->get("/unsubscribe/cart-reminders/{$buyer->id}")->assertForbidden();
        $this->get(URL::signedRoute('cart-reminders.unsubscribe', ['user' => $buyer->id]))->assertOk()->assertSee('unsubscribed');
        $this->assertFalse($buyer->fresh()->cart_reminders);

        $this->actingAs($buyer)->put('/account/profile', ['name' => $buyer->name, 'email' => $buyer->email, 'phone' => $buyer->phone, 'cart_reminders' => '1']);
        $this->assertTrue($buyer->fresh()->cart_reminders);
    }

    public function test_admin_publishes_pages_and_blog_posts_safely(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/pages/create')->assertOk();
        $this->post('/admin/pages', [
            'type' => 'page', 'title' => 'About Us', 'body' => "# Hello\n\nWe **love** sellers. <script>alert(1)</script> [x](javascript:alert(1))",
            'is_published' => '1', 'show_in_footer' => '1',
        ])->assertRedirect('/admin/pages');
        $this->post('/admin/pages', ['type' => 'post', 'title' => 'Diwali gift guide', 'excerpt' => 'Ideas', 'body' => 'Lamps and sweets.', 'is_published' => '1']);
        $this->post('/admin/pages', ['type' => 'post', 'title' => 'Draft post', 'body' => 'Secret']);
        $this->get('/admin/pages')->assertOk()->assertSee('About Us');

        $this->get('/p/about-us')->assertOk()->assertSee('<strong>love</strong>', false)->assertDontSee('<script>', false)->assertDontSee('javascript:', false);
        $this->get('/')->assertSee('About Us')->assertSee('Blog');
        $this->get('/blog')->assertOk()->assertSee('Diwali gift guide')->assertDontSee('Draft post');
        $this->get('/blog/diwali-gift-guide')->assertOk()->assertSee('Lamps and sweets.');
        $this->get('/blog/draft-post')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/p/about-us', false);

        $this->getJson('/api/v1/pages/about-us')->assertOk()->assertJsonPath('data.title', 'About Us');
        $this->getJson('/api/v1/blog')->assertJsonCount(1, 'data');

        $page = Page::firstWhere('slug', 'about-us');
        $this->delete("/admin/pages/{$page->id}")->assertRedirect();
        $this->get('/p/about-us')->assertNotFound();
    }
}

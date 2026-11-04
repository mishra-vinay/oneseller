<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    public function test_buyer_asks_about_a_product_and_seller_replies(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['name' => 'Silk Saree']);
        $store = $product->store;
        $buyer = User::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);

        $this->post("/store/{$store->slug}/message", ['body' => 'Hi'])->assertRedirect('/login');

        $this->actingAs($buyer)->get($product->url())->assertSee('Ask '.$store->name.' a question');
        $this->post("/store/{$store->slug}/message", ['body' => 'Is this pure silk?', 'product_id' => $product->id])->assertRedirect();

        $conversation = Conversation::firstOrFail();
        Notification::assertSentTo($store->user, NewMessage::class);

        $this->actingAs($store->user)->get('/seller/messages')->assertOk()->assertSee('Asha Verma');
        $this->get("/seller/messages/{$conversation->id}")->assertOk()->assertSee('Is this pure silk?')->assertSee('About Silk Saree')->assertDontSee('asha@example.com');
        $this->post("/seller/messages/{$conversation->id}", ['body' => 'Yes, 100% Kanchipuram silk.']);
        Notification::assertSentTo($buyer, NewMessage::class);

        $this->actingAs($buyer)->get('/account/messages')->assertSee($store->name);
        $this->get("/account/messages/{$conversation->id}")->assertSee('100% Kanchipuram silk');

        // A second question goes into the same conversation.
        $this->post("/store/{$store->slug}/message", ['body' => 'And the blouse piece?']);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(3, $conversation->messages()->count());
    }

    public function test_seller_is_emailed_once_until_they_read(): void
    {
        Notification::fake();
        $store = Store::factory()->create();
        $buyer = User::factory()->create();

        $this->actingAs($buyer);
        $this->post("/store/{$store->slug}/message", ['body' => 'One']);
        $this->post("/store/{$store->slug}/message", ['body' => 'Two']);
        Notification::assertSentToTimes($store->user, NewMessage::class, 1);

        $this->actingAs($store->user)->get('/seller/messages/'.Conversation::first()->id);
        $this->actingAs($buyer)->post("/store/{$store->slug}/message", ['body' => 'Three']);
        Notification::assertSentToTimes($store->user, NewMessage::class, 2);
    }

    public function test_message_about_a_parcel_needs_to_be_your_own(): void
    {
        $product = Product::factory()->create();
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);
        $sub = $order->subOrders->first();

        $this->actingAs($order->buyer)->get("/store/{$product->store->slug}/message?sub_order_id={$sub->id}")->assertOk()->assertSee($sub->number);
        $this->post("/store/{$product->store->slug}/message", ['body' => 'Where is it?', 'sub_order_id' => $sub->id])->assertRedirect();

        $this->actingAs(User::factory()->create())->post("/store/{$product->store->slug}/message", ['body' => 'Snoop', 'sub_order_id' => $sub->id])->assertNotFound();
    }

    public function test_conversations_are_private(): void
    {
        $store = Store::factory()->create();
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post("/store/{$store->slug}/message", ['body' => 'Private']);
        $conversation = Conversation::firstOrFail();

        $this->actingAs(User::factory()->create())->get("/account/messages/{$conversation->id}")->assertNotFound();
        $this->actingAs(Store::factory()->create()->user)->get("/seller/messages/{$conversation->id}")->assertNotFound();
        $this->post("/seller/messages/{$conversation->id}", ['body' => 'x'])->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/messages/{$conversation->id}")->assertOk()->assertSee('Private');
        $this->get('/admin/messages')->assertOk();
    }

    public function test_cannot_message_a_store_that_is_not_live(): void
    {
        $store = Store::factory()->pending()->create();

        $this->actingAs(User::factory()->create())->post("/store/{$store->slug}/message", ['body' => 'Hi'])->assertNotFound();
    }
}

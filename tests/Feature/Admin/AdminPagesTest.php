<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    public function test_every_admin_page_renders_with_demo_data(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $admin = User::firstWhere('email', 'admin@example.com');
        $order = $this->checkout($this->buyerWithAddress(), [[Product::visible()->first(), 1]]);

        $this->actingAs($admin);
        foreach (['/admin', '/admin/sellers', '/admin/sellers/ravi-electronics', '/admin/buyers', '/admin/products', '/admin/products?status=all',
            '/admin/categories', '/admin/orders', "/admin/orders/{$order->number}", '/admin/ledger', '/admin/ledger?store=ravi-electronics',
            '/admin/activity', '/admin/notifications', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')->assertSee('Orders, last 7 days')->assertSee('Products to approve');
        $this->get('/admin/notifications')->assertSee("New order {$order->number}");
    }

    public function test_admin_deactivates_and_reactivates_a_buyer(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create();

        $this->actingAs($admin)->get("/admin/buyers/{$buyer->id}")->assertOk()->assertSee($buyer->email);
        $this->post("/admin/buyers/{$buyer->id}/deactivate");
        $this->assertSame(AccountStatus::Deactivated, $buyer->fresh()->status);

        $this->post("/admin/buyers/{$buyer->id}/reactivate");
        $this->assertSame(AccountStatus::Active, $buyer->fresh()->status);

        $this->get("/admin/buyers/{$admin->id}")->assertNotFound();
    }

    public function test_panel_notifications_can_be_opened_and_marked_read(): void
    {
        $admin = User::factory()->admin()->create();
        $this->checkout($this->buyerWithAddress(), [[Product::factory()->create(), 1]]);
        $notification = $admin->notifications()->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertSee('Notifications');
        $this->get("/admin/notifications/{$notification->id}")->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->post('/admin/notifications/read')->assertSessionHas('status');
    }
}

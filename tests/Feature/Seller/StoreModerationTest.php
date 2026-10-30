<?php

namespace Tests\Feature\Seller;

use App\Enums\StoreStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_approves_a_pending_store(): void
    {
        Notification::fake();
        $store = Store::factory()->pending()->create();

        $this->actingAs($this->admin)->get('/admin/sellers')->assertOk()->assertSee($store->name);
        $this->get("/admin/sellers/{$store->slug}")->assertOk();
        $this->post("/admin/sellers/{$store->slug}/approve")->assertSessionHas('status');

        $this->assertSame(StoreStatus::Approved, $store->fresh()->status);
        Notification::assertSentTo($store->user, StoreReviewed::class);

        $this->actingAs($store->user)->get('/seller')->assertOk();
    }

    public function test_admin_rejects_with_a_reason_shown_to_the_seller(): void
    {
        $store = Store::factory()->pending()->create();

        $this->actingAs($this->admin)->post("/admin/sellers/{$store->slug}/reject", ['reason' => 'GST certificate missing']);

        $this->assertSame(StoreStatus::Rejected, $store->fresh()->status);
        $this->actingAs($store->user)->get('/seller/pending')->assertSee('GST certificate missing');
    }

    public function test_suspending_logs_the_seller_out_and_hides_products(): void
    {
        $product = Product::factory()->create();
        $store = $product->store;

        $this->get($product->url())->assertOk();

        $this->actingAs($this->admin)->post("/admin/sellers/{$store->slug}/suspend", ['reason' => 'Policy breach']);

        $this->get($product->url())->assertNotFound();
        $this->actingAs($store->user->fresh())->get('/seller')->assertRedirect('/seller/login');
        $this->assertGuest();

        $this->actingAs($this->admin)->post("/admin/sellers/{$store->slug}/reactivate");
        $this->assertSame(StoreStatus::Approved, $store->fresh()->status);
        $this->assertTrue($store->user->fresh()->isActive());
        $this->get($product->url())->assertOk();
    }

    public function test_commission_override(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->admin)->put("/admin/sellers/{$store->slug}/commission", ['commission_percent' => '7.5']);
        $this->assertSame(750, $store->fresh()->effectiveCommissionBps());

        $this->put("/admin/sellers/{$store->slug}/commission", ['commission_percent' => '']);
        $this->assertSame(1000, $store->fresh()->effectiveCommissionBps());
    }
}

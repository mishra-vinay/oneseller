<?php

namespace Tests\Feature\Seller;

use App\Enums\Role;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewSellerAlert;
use App\Notifications\SellerRegistrationReceived;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'phone' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'store_name' => 'Ravi Electronics',
            'store_phone' => '9876500000',
            'address_line1' => '12 MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'gstin' => '29ABCDE1234F1Z5',
            ...$overrides,
        ];
    }

    public function test_seller_registers_and_waits_for_approval(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->get('/seller/register')->assertOk();
        $this->post('/seller/register', $this->form())->assertRedirect('/seller/pending');

        $store = Store::firstOrFail();
        $this->assertSame('ravi-electronics', $store->slug);
        $this->assertSame(StoreStatus::Pending, $store->status);
        $this->assertSame(Role::Seller, $store->user->role);
        $this->assertAuthenticatedAs($store->user);

        $this->get('/seller/pending')->assertOk()->assertSee('reviewing your store');
        $this->get('/seller')->assertRedirect('/seller/pending');
        $this->get('/seller/products')->assertRedirect('/seller/pending');

        Notification::assertSentTo($store->user, SellerRegistrationReceived::class);
        Notification::assertSentTo($admin, NewSellerAlert::class);
        $this->assertDatabaseHas('activity_log', ['event' => 'seller.registered']);
    }

    public function test_store_slugs_are_unique(): void
    {
        Store::factory()->create(['slug' => 'ravi-electronics']);

        $this->post('/seller/register', $this->form());

        $this->assertDatabaseHas('stores', ['slug' => 'ravi-electronics-2']);
    }

    public function test_auto_approve_setting_skips_the_holding_page(): void
    {
        app(Settings::class)->set(['auto_approve_sellers' => true]);

        $this->post('/seller/register', $this->form())->assertRedirect('/seller');

        $this->assertSame(StoreStatus::Approved, Store::first()->status);
    }

    public function test_invalid_gstin_and_pincode_are_rejected(): void
    {
        $this->post('/seller/register', $this->form(['gstin' => 'NOTAGSTIN', 'pincode' => '12']))
            ->assertSessionHasErrors(['gstin', 'pincode']);
    }

    public function test_seller_can_edit_store_profile_but_not_slug(): void
    {
        $store = Store::factory()->create(['slug' => 'fixed-slug']);

        $this->actingAs($store->user)->get('/seller/store')->assertOk();
        $this->put('/seller/store', [
            'name' => 'Renamed Store',
            'slug' => 'hacked',
            'phone' => '9876543210',
            'address_line1' => 'New address',
            'city' => 'Mysuru',
            'state' => 'Karnataka',
            'pincode' => '570001',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('Renamed Store', $store->name);
        $this->assertSame('fixed-slug', $store->slug);
    }
}

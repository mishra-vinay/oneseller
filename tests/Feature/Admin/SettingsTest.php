<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_apply_before_anything_is_saved(): void
    {
        $settings = app(Settings::class);

        $this->assertSame('OneSeller', $settings->get('site_name'));
        $this->assertSame(1000, $settings->get('default_commission_bps'));
        $this->assertTrue($settings->get('cod_enabled'));
    }

    public function test_admin_can_save_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Default commission');

        $this->put('/admin/settings', [
            'site_name' => 'TalkForWeb Shop',
            'default_commission_percent' => '12.5',
            'flat_shipping_rupees' => '49',
            'free_shipping_threshold_rupees' => '1499.99',
            'support_email' => 'help@example.com',
            'order_notification_email' => '',
            'auto_approve_products' => '1',
            // cod_enabled and auto_approve_sellers left unchecked
        ])->assertSessionHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame('TalkForWeb Shop', $settings->get('site_name'));
        $this->assertSame(1250, $settings->get('default_commission_bps'));
        $this->assertSame(4900, $settings->get('flat_shipping_paise'));
        $this->assertSame(149999, $settings->get('free_shipping_threshold_paise'));
        $this->assertSame('help@example.com', $settings->get('support_email'));
        $this->assertNull($settings->get('order_notification_email'));
        $this->assertFalse($settings->get('cod_enabled'));
        $this->assertFalse($settings->get('auto_approve_sellers'));
        $this->assertTrue($settings->get('auto_approve_products'));

        $this->get('/')->assertOk()->assertSee('TalkForWeb Shop');
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put('/admin/settings', [
            'site_name' => 'X',
            'default_commission_percent' => '150',
            'flat_shipping_rupees' => '-5',
            'free_shipping_threshold_rupees' => 'abc',
        ])->assertSessionHasErrors(['default_commission_percent', 'flat_shipping_rupees', 'free_shipping_threshold_rupees']);
    }

    public function test_sellers_cannot_change_settings(): void
    {
        $this->actingAs(User::factory()->seller()->create())
            ->put('/admin/settings', ['site_name' => 'Hacked'])
            ->assertRedirect('/seller');

        $this->assertSame('OneSeller', app(Settings::class)->get('site_name'));
    }
}

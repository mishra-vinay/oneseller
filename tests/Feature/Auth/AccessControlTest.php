<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_their_areas_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->get('/seller')->assertRedirect('/seller/login');
        $this->get('/account/profile')->assertRedirect('/login');
    }

    public function test_users_are_kept_out_of_other_roles_areas(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($buyer)->get('/admin')->assertRedirect('/');
        $this->actingAs($buyer)->get('/seller')->assertRedirect('/');
        $this->actingAs($seller)->get('/admin/settings')->assertRedirect('/seller');
        $this->actingAs($seller)->get('/account/profile')->assertRedirect('/seller');
        $this->actingAs($admin)->get('/seller')->assertRedirect('/admin');
    }

    public function test_signed_in_users_skip_login_pages(): void
    {
        $this->actingAs(User::factory()->seller()->create())
            ->get('/login')
            ->assertRedirect('/seller');
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get('/seller')->assertOk();

        $seller->forceFill(['status' => AccountStatus::Deactivated])->save();

        $this->get('/seller')->assertRedirect('/seller/login');
        $this->assertGuest();
    }
}

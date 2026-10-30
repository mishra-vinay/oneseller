<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_admin(): void
    {
        $this->artisan('oneseller:create-admin', ['--name' => 'Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'secret123')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->hasVerifiedEmail());
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('oneseller:create-admin', ['--name' => 'Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }
}

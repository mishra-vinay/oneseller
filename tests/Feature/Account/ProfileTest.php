<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account/profile')->assertOk();

        $this->put('/account/profile', [
            'name' => 'New Name',
            'email' => $user->email,
            'phone' => '9123456789',
        ])->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('9123456789', $user->phone);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_changing_email_requires_re_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/profile', [
            'name' => $user->name,
            'email' => 'changed@example.com',
            'phone' => $user->phone,
        ]);

        $user->refresh();
        $this->assertSame('changed@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->put('/account/password', [
            'current_password' => 'password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }
}

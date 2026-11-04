<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BuyerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create an account');
    }

    public function test_buyer_can_register_and_is_active_immediately(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('home'));

        $user = User::where('email', 'asha@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Buyer, $user->role);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_role_cannot_be_injected_through_registration(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'phone' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ]);

        $this->assertSame(Role::Buyer, User::where('email', 'sneaky@example.com')->first()->role);
    }

    public function test_phone_must_be_an_indian_mobile_number(): void
    {
        $this->post('/register', [
            'name' => 'Asha',
            'email' => 'asha@example.com',
            'phone' => '12345',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('phone');
    }
}

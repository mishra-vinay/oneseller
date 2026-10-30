<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function areas(): array
    {
        return [
            'buyer' => ['buyer', '/login', '/'],
            'seller' => ['seller', '/seller/login', '/seller'],
            'admin' => ['admin', '/admin/login', '/admin'],
        ];
    }

    #[DataProvider('areas')]
    public function test_each_role_logs_in_on_its_own_page(string $role, string $loginUrl, string $home): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->get($loginUrl)->assertOk();

        $this->post($loginUrl, ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($home);

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_cannot_log_in_on_another_roles_page(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->post('/admin/login', ['email' => $buyer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post('/seller/login', ['email' => $buyer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $user = User::factory()->seller()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/seller/login');

        $this->assertGuest();
    }
}

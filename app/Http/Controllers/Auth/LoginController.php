<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One login controller serves the buyer, seller and admin login pages.
 * The route supplies the role, and an account can only sign in on its own role's page.
 */
class LoginController extends Controller
{
    public function create(string $role): View
    {
        return view('auth.login', ['role' => Role::from($role)]);
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $role = Role::from($role);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey), 'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60)]),
            ]);
        }

        $attempt = [
            ...$credentials,
            fn ($query) => $query->where('role', $role->value),
        ];

        if (! Auth::attempt($attempt, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);

        if (! $request->user()->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated. Please contact support.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route($role->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $loginRoute = $request->user()->role->loginRoute();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute === 'login' ? 'home' : $loginRoute);
    }
}

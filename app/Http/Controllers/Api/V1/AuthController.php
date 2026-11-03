<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use App\Support\Rules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Token sign-in for the mobile apps. Buyers and sellers sign in; admins use the website.
 */
class AuthController extends Controller
{
    public function register(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', Rules::MOBILE],
            'password' => ['required', PasswordRule::defaults()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], Rules::messages());

        $user = new User($data);
        $user->role = Role::Buyer;
        $user->save();

        event(new Registered($user));
        $cart->mergeGuestCart($user, $request->header(CartService::HEADER));

        return $this->tokenResponse($user, $data['device_name'] ?? null, 201);
    }

    public function login(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in([Role::Buyer->value, Role::Seller->value])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $role = Role::from($data['role'] ?? Role::Buyer->value);
        $key = 'api-login|'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)])]);
        }

        $user = User::where('email', $data['email'])->where('role', $role)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('This account has been deactivated. Please contact support.')]);
        }

        if ($role === Role::Buyer) {
            $cart->mergeGuestCart($user, $request->header(CartService::HEADER));
        }

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Signed out.')]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('If an account exists for that email, a reset link is on its way.')]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateMe(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'required', Rules::MOBILE],
        ], Rules::messages());

        $user->fill($data);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', PasswordRule::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        // Other devices must sign in again with the new password.
        $request->user()->tokens()->whereKeyNot($request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => __('Password updated.')]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => __('A new verification link has been sent to your email address.')]);
    }

    private function tokenResponse(User $user, ?string $device, int $status = 200): JsonResponse
    {
        $token = $user->createToken($device ?: 'mobile', [$user->role->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], $status);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out a deactivated account on its next request, as the scope requires.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            if (! $request->hasSession()) {
                // API: revoke the token so the app has to sign in again.
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => __('This account has been deactivated. Please contact support.')], 403);
            }

            $loginRoute = $user->role->loginRoute();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute)
                ->withErrors(['email' => __('This account has been deactivated. Please contact support.')]);
        }

        return $next($request);
    }
}

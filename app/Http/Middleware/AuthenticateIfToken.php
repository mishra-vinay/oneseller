<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For endpoints open to guests (catalogue, cart): signs the user in when a valid
 * bearer token is sent, and rejects an invalid one instead of silently ignoring it.
 */
class AuthenticateIfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            if (! Auth::guard('sanctum')->user()) {
                return response()->json(['message' => __('Unauthenticated.')], 401);
            }

            Auth::shouldUse('sanctum');
        }

        return $next($request);
    }
}

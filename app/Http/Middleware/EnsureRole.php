<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to one role. A signed-in user of another role is
 * sent to their own area rather than shown an error.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $required = Role::from($role);
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route($required->loginRoute()));
        }

        if (! $user->hasRole($required)) {
            return redirect()->route($user->role->homeRoute());
        }

        return $next($request);
    }
}

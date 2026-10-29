<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sellers cannot use the dashboard until an admin approves their store.
 */
class EnsureStoreApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        abort_if($store === null, 403);

        if (! $store->isApproved()) {
            return redirect()->route('seller.pending');
        }

        return $next($request);
    }
}

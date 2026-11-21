<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Content Security Policy for production. Scripts and styles come from this site,
     * plus Razorpay's checkout, which loads its own script, frames and API calls.
     */
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://checkout.razorpay.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data:",
        "connect-src 'self' https://*.razorpay.com",
        'frame-src https://api.razorpay.com https://checkout.razorpay.com',
        "form-action 'self' https://api.razorpay.com",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        'upgrade-insecure-requests',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com" "https://api.razorpay.com")');
        // Razorpay checkout opens popups for some banks and UPI apps.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::CSP));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

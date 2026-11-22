<?php

use App\Enums\Role;
use App\Http\Middleware\AuthenticateIfToken;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreApproved;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->prefix('seller')->name('seller.')->group(base_path('routes/seller.php'));
            Route::middleware('web')->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare: trust its edge servers so the visitor's real IP and HTTPS are seen.
        // Ranges from https://www.cloudflare.com/ips/.
        $middleware->trustProxies(at: [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ]);
        $middleware->web(append: [SetLocale::class, EnsureAccountActive::class, SecurityHeaders::class]);
        // Language and currency choices are plain values, not secrets; keep them readable.
        $middleware->encryptCookies(except: ['locale', 'currency']);
        // Razorpay signs webhooks itself; they cannot carry a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->alias([
            'role' => EnsureRole::class,
            'store.approved' => EnsureStoreApproved::class,
            'active' => EnsureAccountActive::class,
            'auth.optional' => AuthenticateIfToken::class,
        ]);
        $middleware->api(prepend: [ForceJsonResponse::class, SetLocale::class]);
        $middleware->throttleApi();

        $middleware->redirectGuestsTo(fn (Request $request) => route(Role::forPath($request->path())->loginRoute()));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API 404s say "Not found." rather than naming internal model classes.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('Not found.')], 404);
            }
        });
    })->create();

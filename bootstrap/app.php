<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreApproved;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->prefix('seller')->name('seller.')->group(base_path('routes/seller.php'));
            Route::middleware('web')->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureAccountActive::class, SecurityHeaders::class]);
        // Razorpay signs webhooks itself; they cannot carry a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->alias(['role' => EnsureRole::class, 'store.approved' => EnsureStoreApproved::class]);

        $middleware->redirectGuestsTo(fn (Request $request) => route(Role::forPath($request->path())->loginRoute()));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

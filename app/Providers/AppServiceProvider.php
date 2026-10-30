<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Category;
use App\Services\CartService;
use App\Support\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
        Paginator::defaultView('pagination::tailwind');

        // A guest's cart carries over when they log in or register as a buyer.
        Event::listen(function (Login $event) {
            if ($event->user->role === Role::Buyer) {
                app(CartService::class)->mergeGuestCart($event->user, request()->cookie(CartService::COOKIE));
            }
        });

        View::composer('*', function ($view) {
            $view->with('siteName', app(Settings::class)->get('site_name'));
        });

        View::composer('components.layouts.store', function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
            $view->with('navCategories', Category::topLevel()->active()->ordered()->limit(8)->get(['id', 'name', 'slug']));
        });

        View::composer('components.layouts.panel', function ($view) {
            $view->with('unreadCount', request()->user()?->unreadNotifications()->count() ?? 0);
        });
    }
}

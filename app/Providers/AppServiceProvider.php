<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Page;
use App\Services\CartService;
use App\Support\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
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
            $view->with('footerPages', Page::published()->where('type', Page::TYPE_PAGE)->where('show_in_footer', true)->orderBy('title')->get(['id', 'type', 'title', 'slug']));
            $view->with('hasBlog', Page::published()->where('type', Page::TYPE_POST)->exists());
        });

        View::composer('components.layouts.panel', function ($view) {
            $view->with('unreadCount', request()->user()?->unreadNotifications()->count() ?? 0);
        });
    }
}

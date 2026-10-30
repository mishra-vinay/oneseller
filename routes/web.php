<?php

use App\Enums\Role;
use App\Http\Controllers\Account;
use App\Http\Controllers\Auth\BuyerRegistrationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Storefront;
use Illuminate\Support\Facades\Route;

// Public storefront: browsing needs no account.
Route::get('/', Storefront\HomeController::class)->name('home');
Route::get('/c/{category}', Storefront\CategoryController::class)->name('category.show');
Route::get('/search', Storefront\SearchController::class)->name('search');
Route::get('/product/{store}/{product}', Storefront\ProductPageController::class)->name('product.show');
Route::get('/store/{store}', Storefront\StorePageController::class)->name('store.show');
Route::get('/sitemap.xml', Storefront\SitemapController::class)->name('sitemap');
Route::view('/terms', 'pages.terms')->name('pages.terms');
Route::view('/privacy', 'pages.privacy')->name('pages.privacy');
Route::view('/refund-policy', 'pages.refund')->name('pages.refund');

// Guests can fill a cart; it carries over on login.
Route::get('/cart', [Storefront\CartController::class, 'show'])->name('cart');
Route::post('/cart/items', [Storefront\CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{item}', [Storefront\CartController::class, 'update'])->whereNumber('item')->name('cart.update');
Route::delete('/cart/items/{item}', [Storefront\CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');

Route::post('/webhooks/razorpay', Storefront\RazorpayWebhookController::class)->name('webhooks.razorpay');

Route::middleware('guest')->group(function () {
    Route::get('/register', [BuyerRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [BuyerRegistrationController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->defaults('role', Role::Buyer->value)->name('login');
    Route::post('/login', [LoginController::class, 'store'])->defaults('role', Role::Buyer->value);

    // One password reset flow for every role.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'role:buyer'])->group(function () {
    // Email verification is required before the first checkout, not before browsing.
    Route::middleware('verified')->prefix('checkout')->name('checkout.')->group(function () {
        Route::get('/', [Storefront\CheckoutController::class, 'address'])->name('address');
        Route::post('/address', [Storefront\CheckoutController::class, 'chooseAddress'])->name('address.choose');
        Route::get('/review', [Storefront\CheckoutController::class, 'review'])->name('review');
        Route::post('/place', [Storefront\CheckoutController::class, 'place'])->name('place');
        Route::get('/pay/{order}', [Storefront\PaymentController::class, 'show'])->name('pay');
        Route::post('/pay/{order}', [Storefront\PaymentController::class, 'callback'])->name('pay.callback');
    });

    Route::get('/order/{order}', [Account\OrderController::class, 'show'])->name('order.show');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/orders', [Account\OrderController::class, 'index'])->name('orders');
        Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/password', [Account\ProfileController::class, 'updatePassword'])->name('password.update');

        Route::get('/addresses', [Account\AddressController::class, 'index'])->name('addresses');
        Route::post('/addresses', [Account\AddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{address}/edit', [Account\AddressController::class, 'edit'])->whereNumber('address')->name('addresses.edit');
        Route::put('/addresses/{address}', [Account\AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
        Route::delete('/addresses/{address}', [Account\AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
        Route::post('/addresses/{address}/default', [Account\AddressController::class, 'makeDefault'])->whereNumber('address')->name('addresses.default');
    });
});

<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V1\Seller;
use Illuminate\Support\Facades\Route;

/*
| Mobile API, served under /api/v1. All money is integer paise. See docs/API.md.
*/

Route::name('api.')->group(function () {
    Route::get('/config', [V1\CatalogController::class, 'config'])->name('config');
    Route::get('/pages/{slug}', [V1\CatalogController::class, 'page'])->name('pages.show');
    Route::get('/blog', [V1\CatalogController::class, 'blog'])->name('blog');

    Route::post('/auth/register', [V1\AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('/auth/login', [V1\AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');
    Route::post('/auth/forgot-password', [V1\AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password');

    // Open to guests; a bearer token is used when sent.
    Route::middleware('auth.optional')->group(function () {
        Route::get('/categories', [V1\CatalogController::class, 'categories'])->name('categories');
        Route::get('/products', [V1\CatalogController::class, 'products'])->name('products');
        Route::get('/products/{product}', [V1\CatalogController::class, 'product'])->whereNumber('product')->name('products.show');
        Route::get('/products/{product}/reviews', [V1\CatalogController::class, 'reviews'])->whereNumber('product')->name('products.reviews');
        Route::get('/stores/{store}', [V1\CatalogController::class, 'store'])->name('stores.show');
        Route::get('/stores/{store}/products', [V1\CatalogController::class, 'storeProducts'])->name('stores.products');

        Route::get('/cart', [V1\CartController::class, 'show'])->name('cart');
        Route::post('/cart/items', [V1\CartController::class, 'add'])->name('cart.add');
        Route::patch('/cart/items/{item}', [V1\CartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('/cart/items/{item}', [V1\CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [V1\AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [V1\AuthController::class, 'me'])->name('me');
        Route::patch('/me', [V1\AuthController::class, 'updateMe'])->name('me.update');
        Route::put('/me/password', [V1\AuthController::class, 'updatePassword'])->name('me.password');
        Route::post('/me/email/verification', [V1\AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('me.verification');
        Route::post('/devices', [V1\DeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices/{token}', [V1\DeviceController::class, 'destroy'])->where('token', '.*')->name('devices.destroy');

        // Messages work for both roles: buyers see their stores, sellers their buyers.
        Route::get('/conversations', [V1\MessageController::class, 'index'])->name('conversations');
        Route::get('/conversations/{conversation}', [V1\MessageController::class, 'show'])->whereNumber('conversation')->name('conversations.show');
        Route::post('/conversations/{conversation}/messages', [V1\MessageController::class, 'reply'])->whereNumber('conversation')->middleware('throttle:30,1')->name('conversations.reply');

        Route::middleware('role:buyer')->group(function () {
            Route::get('/addresses', [V1\AddressController::class, 'index'])->name('addresses');
            Route::post('/addresses', [V1\AddressController::class, 'store'])->name('addresses.store');
            Route::patch('/addresses/{address}', [V1\AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
            Route::delete('/addresses/{address}', [V1\AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

            Route::post('/checkout', [V1\CheckoutController::class, 'place'])->middleware('verified')->name('checkout');
            Route::get('/orders', [V1\OrderController::class, 'index'])->name('orders');
            Route::get('/orders/{order}', [V1\OrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/payment/retry', [V1\CheckoutController::class, 'payment'])->name('orders.payment.retry');
            Route::post('/orders/{order}/payment', [V1\CheckoutController::class, 'verify'])->name('orders.payment');
            Route::post('/orders/{order}/parcels/{parcel}/returns', [V1\ReturnController::class, 'store'])->whereNumber('parcel')->name('returns.store');

            Route::get('/returns', [V1\ReturnController::class, 'index'])->name('returns');
            Route::get('/returns/{return}', [V1\ReturnController::class, 'show'])->name('returns.show');

            Route::get('/wishlist', [V1\WishlistController::class, 'index'])->name('wishlist');
            Route::post('/wishlist/{product}', [V1\WishlistController::class, 'add'])->whereNumber('product')->name('wishlist.add');
            Route::delete('/wishlist/{product}', [V1\WishlistController::class, 'remove'])->whereNumber('product')->name('wishlist.remove');

            Route::post('/products/{product}/reviews', [V1\ReviewController::class, 'store'])->whereNumber('product')->middleware('throttle:10,1')->name('reviews.store');
            Route::post('/stores/{store}/messages', [V1\MessageController::class, 'start'])->middleware('throttle:20,1')->name('messages.start');
        });

        Route::middleware(['role:seller', 'store.approved'])->prefix('seller')->name('seller.')->group(function () {
            Route::get('/dashboard', Seller\DashboardController::class)->name('dashboard');

            Route::get('/products', [Seller\ProductController::class, 'index'])->name('products');
            Route::post('/products', [Seller\ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}', [Seller\ProductController::class, 'show'])->whereNumber('product')->name('products.show');
            Route::post('/products/{product}', [Seller\ProductController::class, 'update'])->whereNumber('product')->name('products.update');
            Route::delete('/products/{product}', [Seller\ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');

            Route::get('/inventory', [Seller\InventoryController::class, 'index'])->name('inventory');
            Route::patch('/inventory', [Seller\InventoryController::class, 'update'])->name('inventory.update');

            Route::get('/orders', [Seller\OrderController::class, 'index'])->name('orders');
            Route::get('/orders/{subOrder}', [Seller\OrderController::class, 'show'])->whereNumber('subOrder')->name('orders.show');
            Route::post('/orders/{subOrder}/accept', [Seller\OrderController::class, 'accept'])->whereNumber('subOrder')->name('orders.accept');
            Route::post('/orders/{subOrder}/ship', [Seller\OrderController::class, 'ship'])->whereNumber('subOrder')->name('orders.ship');
            Route::post('/orders/{subOrder}/deliver', [Seller\OrderController::class, 'deliver'])->whereNumber('subOrder')->name('orders.deliver');
            Route::post('/orders/{subOrder}/cancel', [Seller\OrderController::class, 'cancel'])->whereNumber('subOrder')->name('orders.cancel');

            Route::get('/returns', [Seller\ReturnController::class, 'index'])->name('returns');
            Route::get('/returns/{return}', [Seller\ReturnController::class, 'show'])->name('returns.show');
            Route::post('/returns/{return}/approve', [Seller\ReturnController::class, 'approve'])->name('returns.approve');
            Route::post('/returns/{return}/reject', [Seller\ReturnController::class, 'reject'])->name('returns.reject');
            Route::post('/returns/{return}/receive', [Seller\ReturnController::class, 'receive'])->name('returns.receive');
            Route::post('/returns/{return}/replace', [Seller\ReturnController::class, 'replace'])->name('returns.replace');

            Route::get('/earnings', [Seller\AccountController::class, 'earnings'])->name('earnings');
            Route::get('/reviews', [Seller\AccountController::class, 'reviews'])->name('reviews');
            Route::post('/reviews/{review}/reply', [Seller\AccountController::class, 'reply'])->whereNumber('review')->name('reviews.reply');
            Route::get('/notifications', [Seller\AccountController::class, 'notifications'])->name('notifications');
            Route::post('/notifications/read', [Seller\AccountController::class, 'readNotifications'])->name('notifications.read');
        });
    });
});

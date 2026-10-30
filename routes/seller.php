<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Seller;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', [Seller\RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [Seller\RegistrationController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->defaults('role', Role::Seller->value)->name('login');
    Route::post('/login', [LoginController::class, 'store'])->defaults('role', Role::Seller->value);
});

Route::middleware(['auth', 'role:seller'])->group(function () {
    Route::get('/pending', Seller\PendingController::class)->name('pending');

    // Everything below is limited to the signed-in seller's own store.
    Route::middleware('store.approved')->group(function () {
        Route::get('/', Seller\DashboardController::class)->name('dashboard');

        Route::get('/products', [Seller\ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [Seller\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [Seller\ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [Seller\ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
        Route::put('/products/{product}', [Seller\ProductController::class, 'update'])->whereNumber('product')->name('products.update');
        Route::delete('/products/{product}', [Seller\ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');

        Route::get('/inventory', [Seller\InventoryController::class, 'index'])->name('inventory');
        Route::put('/inventory', [Seller\InventoryController::class, 'update'])->name('inventory.update');

        Route::get('/orders', [Seller\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{subOrder}', [Seller\OrderController::class, 'show'])->whereNumber('subOrder')->name('orders.show');
        Route::post('/orders/{subOrder}/accept', [Seller\OrderController::class, 'accept'])->whereNumber('subOrder')->name('orders.accept');
        Route::post('/orders/{subOrder}/ship', [Seller\OrderController::class, 'ship'])->whereNumber('subOrder')->name('orders.ship');
        Route::post('/orders/{subOrder}/deliver', [Seller\OrderController::class, 'deliver'])->whereNumber('subOrder')->name('orders.deliver');
        Route::post('/orders/{subOrder}/cancel', [Seller\OrderController::class, 'cancel'])->whereNumber('subOrder')->name('orders.cancel');

        Route::get('/customers', Seller\CustomerController::class)->name('customers');
        Route::get('/earnings', Seller\EarningsController::class)->name('earnings');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

        Route::get('/store', [Seller\StoreProfileController::class, 'edit'])->name('store');
        Route::put('/store', [Seller\StoreProfileController::class, 'update'])->name('store.update');
    });
});

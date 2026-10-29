<?php

use App\Enums\Role;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->defaults('role', Role::Admin->value)->name('login');
    Route::post('/login', [LoginController::class, 'store'])->defaults('role', Role::Admin->value);
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/sellers', [Admin\SellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/{store}', [Admin\SellerController::class, 'show'])->name('sellers.show');
    Route::post('/sellers/{store}/approve', [Admin\SellerController::class, 'approve'])->name('sellers.approve');
    Route::post('/sellers/{store}/reject', [Admin\SellerController::class, 'reject'])->name('sellers.reject');
    Route::post('/sellers/{store}/suspend', [Admin\SellerController::class, 'suspend'])->name('sellers.suspend');
    Route::post('/sellers/{store}/reactivate', [Admin\SellerController::class, 'reactivate'])->name('sellers.reactivate');
    Route::put('/sellers/{store}/commission', [Admin\SellerController::class, 'commission'])->name('sellers.commission');

    Route::get('/products', [Admin\ProductController::class, 'index'])->name('products.index');
    Route::post('/products/bulk-approve', [Admin\ProductController::class, 'bulkApprove'])->name('products.bulk-approve');
    Route::get('/products/{product}', [Admin\ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product}/approve', [Admin\ProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [Admin\ProductController::class, 'reject'])->name('products.reject');
    Route::post('/products/{product}/disable', [Admin\ProductController::class, 'disable'])->name('products.disable');
    Route::post('/products/{product}/enable', [Admin\ProductController::class, 'enable'])->name('products.enable');

    Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [Admin\CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category:id}/edit', [Admin\CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category:id}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::get('/categories/{category:id}/delete', [Admin\CategoryController::class, 'confirmDelete'])->name('categories.delete');
    Route::delete('/categories/{category:id}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/buyers', [Admin\BuyerController::class, 'index'])->name('buyers.index');
    Route::get('/buyers/{buyer}', [Admin\BuyerController::class, 'show'])->name('buyers.show');
    Route::post('/buyers/{buyer}/deactivate', [Admin\BuyerController::class, 'deactivate'])->name('buyers.deactivate');
    Route::post('/buyers/{buyer}/reactivate', [Admin\BuyerController::class, 'reactivate'])->name('buyers.reactivate');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/portions/{subOrder}/cancel', [Admin\OrderController::class, 'cancelPortion'])->whereNumber('subOrder')->name('orders.cancel-portion');
    Route::post('/orders/{order}/portions/{subOrder}/refund', [Admin\OrderController::class, 'refundPortion'])->whereNumber('subOrder')->name('orders.refund-portion');

    Route::get('/ledger', [Admin\LedgerController::class, 'index'])->name('ledger.index');
    Route::post('/ledger/{store}', [Admin\LedgerController::class, 'payout'])->name('ledger.payout');

    Route::get('/activity', Admin\ActivityController::class)->name('activity');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});

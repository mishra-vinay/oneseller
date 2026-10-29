<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Seller\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->defaults('role', Role::Seller->value)->name('login');
    Route::post('/login', [LoginController::class, 'store'])->defaults('role', Role::Seller->value);
});

Route::middleware(['auth', 'role:seller'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});

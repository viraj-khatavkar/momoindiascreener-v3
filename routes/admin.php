<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NseFilesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('admin.dashboard');

Route::get('/users', [UsersController::class, 'index'])->name('admin.users.index');
Route::get('/users/download', [UsersController::class, 'download'])->name('admin.users.download');

Route::get('/orders', [OrdersController::class, 'index'])->name('admin.orders.index');
Route::get('/orders/download', [OrdersController::class, 'download'])->name('admin.orders.download');

Route::resource('/nse-files', NseFilesController::class)->only(['index', 'create', 'store']);

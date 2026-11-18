<?php

use App\Http\Controllers\Admin\DiscountAdminController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup `admin.` (auth). Nama route: admin.discounts, admin.discounts.create, dst.
Route::get('discounts', [DiscountAdminController::class, 'index'])->name('discounts');
Route::get('discounts/create', [DiscountAdminController::class, 'create'])->name('discounts.create');
Route::post('discounts', [DiscountAdminController::class, 'store'])->name('discounts.store');
Route::get('discounts/{discount}/edit', [DiscountAdminController::class, 'edit'])->name('discounts.edit');
Route::put('discounts/{discount}', [DiscountAdminController::class, 'update'])->name('discounts.update');
Route::post('discounts/{discount}/toggle', [DiscountAdminController::class, 'toggle'])->name('discounts.toggle');
Route::delete('discounts/{discount}', [DiscountAdminController::class, 'destroy'])->name('discounts.destroy');

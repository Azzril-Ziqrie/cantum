<?php

use App\Http\Controllers\Admin\UsersController;
use App\Http\Middleware\EnsureCanManageSettings;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup admin (prefix `admin`, nama `admin.`). Hanya owner.
Route::middleware(EnsureCanManageSettings::class)->prefix('users')->group(function () {
    Route::get('/', [UsersController::class, 'index'])->name('users');
    Route::post('/', [UsersController::class, 'store'])->name('users.store');
    Route::put('{user}', [UsersController::class, 'update'])->name('users.update');
    Route::post('{user}/toggle', [UsersController::class, 'toggle'])->name('users.toggle');
});

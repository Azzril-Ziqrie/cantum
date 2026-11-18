<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Middleware\EnsureCanManageSettings;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup admin (prefix `admin`, nama `admin.`). Hanya owner.
Route::middleware(EnsureCanManageSettings::class)->prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('settings');
    Route::put('brand', [SettingsController::class, 'updateBrand'])->name('settings.brand');
    Route::post('branches', [SettingsController::class, 'storeBranch'])->name('settings.branches.store');
    Route::put('branches/{branch}', [SettingsController::class, 'updateBranch'])->name('settings.branches.update');
    Route::put('gateway', [SettingsController::class, 'updateGateway'])->name('settings.gateway');
    Route::post('gateway/test', [SettingsController::class, 'testGateway'])->middleware('throttle:10,1')->name('settings.gateway.test');
    Route::post('backup', [SettingsController::class, 'recordBackup'])->name('settings.backup');

    Route::get('audit', [LogsController::class, 'audit'])->name('settings.audit');
    Route::get('webhooks', [LogsController::class, 'webhooks'])->name('settings.webhooks');

    Route::get('export/menu', [ExportController::class, 'menu'])->name('settings.export.menu');
    Route::get('export/orders', [ExportController::class, 'orders'])->name('settings.export.orders');
});

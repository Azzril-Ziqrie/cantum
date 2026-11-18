<?php

use App\Http\Controllers\KaseraWebhookController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\LoginController;
use App\Http\Controllers\Staff\StaffApiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/staff');

// Pelanggan
Route::get('/t/{token}', [MenuController::class, 'show'])->name('menu.show');
Route::post('/order', [OrderController::class, 'store'])->middleware('throttle:20,1')->name('order.store');
Route::post('/promo/check', [PromoController::class, 'check'])->middleware('throttle:30,1')->name('promo.check');
Route::get('/pay/{order}', [PayController::class, 'show'])->middleware('throttle:60,1')->name('pay.show');
Route::get('/api/order-status/{order}', [PayController::class, 'status'])->middleware('throttle:60,1')->name('order.status');

// Gateway (tanpa CSRF, diamankan signature)
Route::post('/kasera-webhook', KaseraWebhookController::class)->middleware('throttle:120,1')->name('kasera.webhook');

// Staf
Route::prefix('staff')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('staff.login');

    Route::middleware('auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('staff.dashboard');
        Route::post('logout', [LoginController::class, 'destroy'])->name('staff.logout');

        Route::prefix('api')->name('staff.api.')->group(function () {
            Route::get('snapshot', [StaffApiController::class, 'snapshot'])->name('snapshot');
            Route::post('orders/{order}/advance', [StaffApiController::class, 'advance'])->name('advance');
            Route::post('orders/{order}/cancel', [StaffApiController::class, 'cancel'])->name('cancel');
            Route::post('branches/{branch}/open', [StaffApiController::class, 'setOpen'])->name('open');
            Route::post('branches/{branch}/items/{item}/availability', [StaffApiController::class, 'setAvailability'])->name('availability');
        });
    });
});

// Panel admin: setiap modul punya berkas sendiri di routes/admin/*.php. Hak akses per aksi dicek di service.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    foreach (glob(base_path('routes/admin/*.php')) as $moduleRoutes) {
        require $moduleRoutes;
    }
});

// Halaman staf tambahan (mis. layar dapur/bar): routes/staff/*.php, nama route berawalan `staff.`.
Route::middleware('auth')->prefix('staff')->name('staff.')->group(function () {
    foreach (glob(base_path('routes/staff/*.php')) as $moduleRoutes) {
        require $moduleRoutes;
    }
});

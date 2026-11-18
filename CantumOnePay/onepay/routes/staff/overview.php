<?php

use App\Http\Controllers\Staff\OverviewController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup auth + prefix `staff` + nama `staff.` (lihat routes/web.php).

Route::get('overview', [OverviewController::class, 'index'])->name('overview');
Route::get('overview/data', [OverviewController::class, 'data'])->middleware('throttle:60,1')->name('overview.data');

<?php

use App\Http\Controllers\Staff\WarehouseController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup auth + prefix `staff` + nama `staff.` (lihat routes/web.php).

Route::get('warehouse', WarehouseController::class)->name('warehouse');

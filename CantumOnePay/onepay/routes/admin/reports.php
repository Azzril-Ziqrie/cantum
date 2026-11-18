<?php

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup `admin.` (lihat routes/web.php). Nama: admin.reports, admin.reports.csv, dst.
Route::get('reports', [ReportController::class, 'index'])->name('reports');
Route::get('reports/print', [ReportController::class, 'print'])->name('reports.print');
Route::get('reports/export.csv', [ReportController::class, 'csv'])->name('reports.csv');
Route::get('reports/close', [ReportController::class, 'close'])->name('reports.close');
Route::get('reports/close.csv', [ReportController::class, 'closeCsv'])->name('reports.close.csv');

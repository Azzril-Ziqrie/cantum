<?php

use App\Http\Controllers\Staff\KitchenController;
use App\Http\Controllers\Staff\RefundController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup auth + prefix `staff` + nama `staff.` (lihat routes/web.php).

Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen');
Route::get('kitchen/snapshot', [KitchenController::class, 'snapshot'])->middleware('throttle:120,1')->name('kitchen.snapshot');
Route::post('kitchen/items/{orderItem}/prep', [KitchenController::class, 'prep'])->name('kitchen.prep');

Route::get('refunds', [RefundController::class, 'index'])->name('refunds');
Route::get('refunds/data', [RefundController::class, 'data'])->name('refunds.data');
Route::post('refunds/{refund}/complete', [RefundController::class, 'complete'])->name('refunds.complete');
Route::post('refunds/review/{order}/resolve', [RefundController::class, 'resolveReview'])->name('refunds.review');

Route::post('orders/{order}/cancel-request', [RefundController::class, 'requestCancellation'])->name('cancellations.request');
Route::post('cancellations/{cancellation}/decide', [RefundController::class, 'decide'])->name('cancellations.decide');

<?php

use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\Admin\TablesController;
use Illuminate\Support\Facades\Route;

// Dimuat di dalam grup `admin` (auth, awalan URL admin/, awalan nama admin.).
Route::get('tables', [TablesController::class, 'index'])->name('tables');

Route::prefix('tables')->name('tables.')->controller(TablesController::class)->group(function () {
    Route::get('layout', 'layout')->name('layout');
    Route::post('copy-layout', 'copyLayout')->name('copy');

    Route::post('branches/{branch}/tables', 'storeTable')->name('store');
    Route::post('branches/{branch}/tables/bulk', 'storeTables')->name('bulk');
    Route::post('branches/{branch}/elements', 'storeElement')->name('elements.store');

    Route::patch('table/{table}', 'updateTable')->name('update');
    Route::delete('table/{table}', 'destroyTable')->name('destroy');
    Route::post('table/{table}/duplicate', 'duplicateTable')->name('duplicate');
    Route::post('table/{table}/token', 'regenerateToken')->name('token');

    Route::patch('element/{element}', 'updateElement')->name('elements.update');
    Route::delete('element/{element}', 'destroyElement')->name('elements.destroy');
});

Route::prefix('tables')->name('tables.')->controller(TableQrController::class)->group(function () {
    Route::get('sheet', 'sheet')->name('sheet');
    Route::get('table/{table}/qr.svg', 'code')->name('qr');
    Route::get('table/{table}/sticker.svg', 'sticker')->name('sticker');
});

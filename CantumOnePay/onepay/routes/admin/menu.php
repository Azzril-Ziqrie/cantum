<?php

use App\Http\Controllers\Admin\MenuAdminController;
use App\Http\Middleware\EnsureCanManageMenu;
use Illuminate\Support\Facades\Route;

// Grup induk memberi prefix URL `admin` dan awalan nama `admin.`. Hak akses dicek di MenuAdminService.
Route::middleware(EnsureCanManageMenu::class)->prefix('menu')->controller(MenuAdminController::class)->group(function () {
    Route::get('/', 'index')->name('menu');

    Route::post('categories', 'storeCategory')->name('menu.categories.store');
    Route::put('categories/{category}', 'updateCategory')->name('menu.categories.update');
    Route::delete('categories/{category}', 'destroyCategory')->name('menu.categories.destroy');

    Route::get('items/create', 'newItem')->name('menu.items.create');
    Route::post('items', 'storeItem')->name('menu.items.store');
    Route::get('items/{item}/edit', 'editItem')->name('menu.items.edit');
    Route::put('items/{item}', 'updateItem')->name('menu.items.update');
    Route::delete('items/{item}', 'destroyItem')->name('menu.items.destroy');

    Route::post('items/{item}/groups', 'storeGroup')->name('menu.groups.store');
    Route::put('groups/{group}', 'updateGroup')->name('menu.groups.update');
    Route::delete('groups/{group}', 'destroyGroup')->name('menu.groups.destroy');
    Route::post('groups/{group}/modifiers', 'storeModifier')->name('menu.modifiers.store');
    Route::put('modifiers/{modifier}', 'updateModifier')->name('menu.modifiers.update');
    Route::delete('modifiers/{modifier}', 'destroyModifier')->name('menu.modifiers.destroy');

    Route::put('branches/{branch}/items/{item}', 'updateBranchItem')->name('menu.branch-items.update');
    Route::post('branches/{branch}/copy', 'copyBranch')->name('menu.branches.copy');

    Route::post('bulk-price/preview', 'previewBulk')->name('menu.bulk.preview');
    Route::post('bulk-price/apply', 'applyBulk')->name('menu.bulk.apply');

    Route::get('export', 'export')->name('menu.export');
    Route::post('import', 'import')->name('menu.import');
});

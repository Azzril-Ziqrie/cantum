<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Halaman penyambut untuk role gudang, sampai modul stok bahan dirilis. */
class WarehouseController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role->canViewWarehouse(), 403);

        return view('staff.warehouse', [
            'brand' => Brand::current(),
            'user' => $user,
            'branches' => $user->accessibleBranches(),
        ]);
    }
}

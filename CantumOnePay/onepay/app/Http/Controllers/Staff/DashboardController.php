<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user->role->canViewFloor()) {
            return redirect()->route($user->role->landingRoute());
        }
        $branches = $user->accessibleBranches();

        return view('staff.dashboard', [
            'brand' => Brand::current(),
            'branches' => $branches,
            'clientConfig' => [
                'snapshotUrl' => route('staff.api.snapshot'),
                'actionBase' => url('staff/api'),
                'canCancel' => $user->role->canCancelOrders(),
                'canRequestCancel' => $user->role->canRequestCancellation(),
                'canToggle' => $user->role->canToggleAvailability(),
                'canOpenClose' => $user->role->canOpenCloseBranch(),
                'staffBase' => url('staff'),
                'refundsUrl' => $user->role->canCancelOrders() ? route('staff.refunds') : null,
                'branch' => $this->initialBranch($request, $branches)?->id,
            ],
        ]);
    }

    /** Cabang awal: dari ?branch=ID bila boleh diakses (mis. dari ringkasan semua cabang), kalau tidak yang pertama. */
    private function initialBranch(Request $request, Collection $branches): ?Branch
    {
        return $branches->firstWhere('id', (int) $request->query('branch')) ?? $branches->first();
    }
}

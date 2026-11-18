<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BranchOverviewService;
use App\Services\OrderRejected;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ringkasan semua cabang untuk owner. Buka/tutup cabang memakai API staf yang sudah ada. */
class OverviewController extends Controller
{
    public function __construct(private readonly BranchOverviewService $overview)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->role->canViewOverview(), 403);

        return view('staff.overview', [
            'brand' => Brand::current(),
            'config' => [
                'dataUrl' => route('staff.overview.data'),
                'dashboardUrl' => route('staff.dashboard'),
                'openUrl' => url('staff/api/branches/:id/open'),
                'initial' => $this->overview->overview($request->user()),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            return response()->json(['branches' => $this->overview->overview($request->user())]);
        } catch (OrderRejected) {
            return response()->json(['error' => 'Tidak punya akses.'], 403);
        }
    }
}

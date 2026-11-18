<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PrepStatus;
use App\Http\Controllers\Concerns\RespondsToRejections;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\OrderItem;
use App\Services\KitchenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Layar dapur dan bar per stasiun. Aksi "habis" memakai API dashboard yang sudah ada. */
class KitchenController extends Controller
{
    use RespondsToRejections;

    public function __construct(private readonly KitchenService $kitchen)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role->canManageOrders(), 403);
        $branches = $user->accessibleBranches();
        $station = array_key_exists((string) $request->query('station'), KitchenService::STATIONS) ? $request->query('station') : 'kitchen';

        return view('staff.kitchen', [
            'brand' => Brand::current(),
            'branches' => $branches,
            'station' => $station,
            'stations' => KitchenService::STATIONS,
            'clientConfig' => [
                'snapshotUrl' => route('staff.kitchen.snapshot'),
                'prepBase' => url('staff/kitchen/items'),
                'apiBase' => url('staff/api'),
                'station' => $station,
                'branch' => $branches->first()?->id,
                'canToggle' => $user->role->canToggleAvailability(),
            ],
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $branch = Branch::query()->findOrFail((int) $request->query('branch'));
        return $this->guarded(fn () => $this->kitchen->snapshot($request->user(), $branch->id, (string) $request->query('station')));
    }

    public function prep(Request $request, OrderItem $orderItem): JsonResponse
    {
        $status = PrepStatus::tryFrom((string) $request->input('status'));
        abort_if($status === null, 422, 'Status penyiapan tidak dikenal.');

        return $this->guarded(fn () => $this->kitchen->setPrep($request->user(), $orderItem, $status));
    }
}

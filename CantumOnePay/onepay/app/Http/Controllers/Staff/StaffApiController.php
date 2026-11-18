<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Order;
use App\Services\OrderRejected;
use App\Services\PaymentService;
use App\Services\StaffOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API dashboard staf. Pelanggaran aturan dibalas 422 dengan pesan yang aman ditampilkan. */
class StaffApiController extends Controller
{
    public function __construct(private readonly StaffOrderService $staff)
    {
    }

    public function snapshot(Request $request, PaymentService $payments): JsonResponse
    {
        $branch = Branch::query()->findOrFail((int) $request->query('branch'));
        abort_unless($request->user()->role->canManageOrders() && $request->user()->canAccessBranch($branch->id), 403);

        $payments->expireStaleOrders();

        return response()->json([
            'floor' => $this->staff->floorPlan($branch->id),
            'orders' => $this->staff->activeOrders($branch->id),
            'review' => $this->staff->ordersNeedingReview($branch->id),
            'menu' => $this->staff->menuAvailability($branch->id),
            'is_open' => $branch->is_open,
            'rules' => $branch->ruleChips(),
        ]);
    }

    public function advance(Request $request, Order $order): JsonResponse
    {
        return $this->guarded(fn () => ['status' => $this->staff->advance($request->user(), $order)->value]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        return $this->guarded(function () use ($request, $order) {
            $this->staff->cancelWithRefund($request->user(), $order, (string) $request->input('reason'));
            return ['status' => 'cancelled'];
        });
    }

    public function setOpen(Request $request, Branch $branch): JsonResponse
    {
        return $this->guarded(function () use ($request, $branch) {
            $this->staff->setBranchOpen($request->user(), $branch, $request->boolean('value'));
            return ['ok' => true];
        });
    }

    public function setAvailability(Request $request, Branch $branch, Item $item): JsonResponse
    {
        return $this->guarded(function () use ($request, $branch, $item) {
            $this->staff->setItemAvailability($request->user(), $branch->id, $item->id, $request->boolean('available'));
            return ['ok' => true];
        });
    }

    private function guarded(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (OrderRejected $rejection) {
            return response()->json(['error' => $rejection->getMessage()], 422);
        }
    }
}

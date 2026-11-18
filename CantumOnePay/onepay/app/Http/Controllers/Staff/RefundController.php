<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\RespondsToRejections;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pembatalan dengan persetujuan, antrian pesanan yang perlu ditinjau, dan penyelesaian refund. */
class RefundController extends Controller
{
    use RespondsToRejections;

    public function __construct(private readonly RefundService $refunds)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role->canCancelOrders(), 403);
        $branches = $user->accessibleBranches();

        return view('staff.refunds', [
            'brand' => Brand::current(),
            'branches' => $branches,
            'clientConfig' => [
                'dataUrl' => route('staff.refunds.data'),
                'base' => url('staff'),
                'branch' => $branches->first()?->id,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return $this->guarded(fn () => $this->refunds->overview($request->user(), (int) $request->query('branch')));
    }

    public function requestCancellation(Request $request, Order $order): JsonResponse
    {
        return $this->guarded(function () use ($request, $order) {
            $this->refunds->requestCancellation($request->user(), $order, (string) $request->input('reason'));
            return ['status' => 'requested'];
        });
    }

    public function decide(Request $request, CancellationRequest $cancellation): JsonResponse
    {
        return $this->guarded(function () use ($request, $cancellation) {
            $this->refunds->decide($request->user(), $cancellation, $request->boolean('approve'), (string) $request->input('note'));
            return ['status' => $request->boolean('approve') ? 'approved' : 'rejected'];
        });
    }

    public function complete(Request $request, Refund $refund): JsonResponse
    {
        return $this->guarded(function () use ($request, $refund) {
            $this->refunds->completeRefund($request->user(), $refund, (string) $request->input('note'));
            return ['status' => 'done'];
        });
    }

    public function resolveReview(Request $request, Order $order): JsonResponse
    {
        return $this->guarded(function () use ($request, $order) {
            $this->refunds->resolveReview($request->user(), $order, (string) $request->input('note'));
            return ['status' => 'resolved'];
        });
    }
}

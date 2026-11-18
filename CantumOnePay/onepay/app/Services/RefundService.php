<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan dan refund: kasir mengajukan, manager/owner memutuskan. Pembayaran yang masuk setelah
 * pesanan expired/cancelled (needs_review) diselesaikan di sini setelah dana dikembalikan lewat Kasera.
 */
class RefundService
{
    public function __construct(private readonly StaffOrderService $staff)
    {
    }

    public function requestCancellation(User $user, Order $order, string $reason): CancellationRequest
    {
        $this->staff->authorizeBranch($user, $order->branch_id, $user->role->canRequestCancellation());
        $reason = $this->requireText($reason, 'Alasan wajib diisi.');
        if (!$order->status->isActive()) {
            throw new OrderRejected('Hanya pesanan aktif yang bisa dibatalkan.');
        }
        if ($order->cancellationRequests()->where('status', 'pending')->exists()) {
            throw new OrderRejected('Pengajuan pembatalan untuk pesanan ini sudah menunggu persetujuan.');
        }

        $request = $order->cancellationRequests()->create(['requested_by' => $user->id, 'reason' => $reason]);
        AuditLog::record($user, 'order.cancel_request', 'order', $order->id, null, $reason);
        return $request;
    }

    public function decide(User $user, CancellationRequest $request, bool $approve, string $note): void
    {
        $order = $request->order;
        $this->staff->authorizeBranch($user, $order->branch_id, $user->role->canCancelOrders());
        if (!$request->isPending()) {
            throw new OrderRejected('Pengajuan ini sudah diputuskan.');
        }
        $note = $approve ? trim($note) : $this->requireText($note, 'Alasan penolakan wajib diisi.');

        DB::transaction(function () use ($user, $request, $order, $approve, $note) {
            if ($approve) {
                $this->staff->cancelWithRefund($user, $order, $request->reason . ' (diajukan ' . $request->requester->name . ')');
            }
            $request->update([
                'status' => $approve ? 'approved' : 'rejected', 'decided_by' => $user->id,
                'decision_note' => $note !== '' ? $note : null, 'decided_at' => now(),
            ]);
            AuditLog::record($user, $approve ? 'order.cancel_approved' : 'order.cancel_rejected', 'order', $order->id, 'pending', $note !== '' ? $note : null);
        });
    }

    /** Tandai refund yang tertunda sudah dikembalikan ke pelanggan. */
    public function completeRefund(User $user, Refund $refund, string $note): void
    {
        $this->staff->authorizeBranch($user, $refund->order->branch_id, $user->role->canCancelOrders());
        if ($refund->status === 'done') {
            throw new OrderRejected('Refund ini sudah selesai.');
        }
        $note = $this->requireText($note, 'Catatan wajib diisi (mis. nomor referensi pengembalian dana).');

        $refund->update(['status' => 'done', 'note' => $note, 'resolved_by' => $user->id, 'done_at' => now()]);
        AuditLog::record($user, 'refund.done', 'refund', $refund->id, 'pending', $note);
    }

    /** Pembayaran masuk setelah expired/cancelled: catat refund selesai dan angkat tanda review. */
    public function resolveReview(User $user, Order $order, string $note): void
    {
        $this->staff->authorizeBranch($user, $order->branch_id, $user->role->canCancelOrders());
        if (!$order->needs_review) {
            throw new OrderRejected('Pesanan ini tidak perlu ditinjau.');
        }
        $note = $this->requireText($note, 'Catatan wajib diisi (mis. nomor referensi pengembalian dana).');

        DB::transaction(function () use ($user, $order, $note) {
            Refund::create([
                'order_id' => $order->id, 'amount' => $order->total, 'status' => 'done', 'created_by' => $user->id,
                'reason' => 'Pembayaran masuk setelah pesanan ' . $order->status->value,
                'note' => $note, 'resolved_by' => $user->id, 'done_at' => now(),
            ]);
            $order->update(['needs_review' => false]);
            $order->logStatus($order->status, $order->status, 'user:' . $user->id, 'Ditinjau, dana dikembalikan: ' . $note);
            AuditLog::record($user, 'order.review_resolved', 'order', $order->id, 'needs_review', $note);
        });
    }

    /** Data halaman refund untuk satu cabang. */
    public function overview(User $user, int $branchId): array
    {
        $this->staff->authorizeBranch($user, $branchId, $user->role->canCancelOrders());

        $refunds = Refund::query()->with('order:id,code,table_label,branch_id')
            ->whereHas('order', fn ($q) => $q->where('branch_id', $branchId));

        return [
            'requests' => CancellationRequest::query()->with(['order', 'requester:id,name'])->where('status', 'pending')
                ->whereHas('order', fn ($q) => $q->where('branch_id', $branchId))->oldest()->get()
                ->map(fn (CancellationRequest $r) => [
                    'id' => $r->id, 'code' => $r->order->code, 'table' => $r->order->table_label, 'total' => $r->order->total,
                    'reason' => $r->reason, 'by' => $r->requester->name, 'at' => $r->created_at->format('d M H:i'),
                ])->all(),
            'review' => Order::query()->where('branch_id', $branchId)->where('needs_review', true)->orderBy('paid_at')->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id, 'code' => $o->code, 'table' => $o->table_label, 'total' => $o->total,
                    'status' => $o->status->value, 'paid_at' => $o->paid_at?->format('d M H:i'),
                ])->all(),
            'pending' => (clone $refunds)->where('status', 'pending')->oldest()->get()->map(fn (Refund $r) => $this->presentRefund($r))->all(),
            'done' => (clone $refunds)->where('status', 'done')->latest('done_at')->limit(20)->get()->map(fn (Refund $r) => $this->presentRefund($r))->all(),
        ];
    }

    private function presentRefund(Refund $refund): array
    {
        return [
            'id' => $refund->id, 'code' => $refund->order->code, 'table' => $refund->order->table_label,
            'amount' => $refund->amount, 'reason' => $refund->reason, 'note' => $refund->note,
            'status' => $refund->status, 'at' => ($refund->done_at ?? $refund->created_at)->format('d M H:i'),
        ];
    }

    private function requireText(string $text, string $message): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new OrderRejected($message);
        }
        return $text;
    }
}

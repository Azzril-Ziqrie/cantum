<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PrepStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CancellationRequest;
use App\Models\FloorArea;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Aksi staf pada pesanan dan cabang. Hak akses dicek di sini, di sisi server, per cabang. */
class StaffOrderService
{
    /** Pesanan aktif cabang (dibayar, diproses, disajikan), diurutkan dari yang paling lama menunggu. */
    public function activeOrders(int $branchId): array
    {
        $orders = Order::query()
            ->with('items.modifiers')
            ->where('branch_id', $branchId)
            ->whereIn('status', OrderStatus::active())
            ->orderBy('paid_at')->orderBy('id')
            ->get();
        $requests = $this->pendingRequestsByOrder($orders->pluck('id')->all());

        return $orders->map(fn (Order $order) => $this->present($order, $requests[$order->id] ?? null))->all();
    }

    /**
     * Denah cabang per area, dengan status tiap meja turunan dari pesanannya.
     * Prioritas: baru dibayar > diproses > siap disajikan > disajikan > menunggu bayar > kosong.
     */
    public function floorPlan(int $branchId): array
    {
        $activeByTable = Order::query()->with('items:id,order_id,prep_status')
            ->where('branch_id', $branchId)->whereIn('status', OrderStatus::active())
            ->get(['id', 'dining_table_id', 'status', 'paid_at'])->groupBy('dining_table_id');
        $pendingTables = Order::query()->where('branch_id', $branchId)->where('status', OrderStatus::PendingPayment)
            ->pluck('dining_table_id')->flip();

        return FloorArea::query()->with(['tables' => fn ($q) => $q->where('is_active', true), 'elements'])
            ->where('branch_id', $branchId)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (FloorArea $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'elements' => $area->elements->map->only('id', 'type', 'label', 'pos_x', 'pos_y', 'width', 'height', 'rotation')->all(),
                'tables' => $area->tables->map(fn ($table) => $table->only('id', 'label', 'seats', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation') + [
                    'status' => $this->tableStatus($activeByTable[$table->id] ?? collect(), isset($pendingTables[$table->id])),
                    'waiting_since' => ($activeByTable[$table->id] ?? collect())->min('paid_at')?->timestamp,
                ])->all(),
            ])->all();
    }

    /** @param Collection<int, Order> $activeOrders */
    private function tableStatus(Collection $activeOrders, bool $hasPending): string
    {
        $preparing = $activeOrders->where('status', OrderStatus::Preparing);

        return match (true) {
            $activeOrders->contains('status', OrderStatus::Paid) => 'new',
            $preparing->contains(fn (Order $order) => !$this->allItemsReady($order)) => 'preparing',
            $preparing->isNotEmpty() => 'ready',
            $activeOrders->contains('status', OrderStatus::Served) => 'served',
            $hasPending => 'pending',
            default => 'empty',
        };
    }

    private function allItemsReady(Order $order): bool
    {
        return $order->items->isNotEmpty() && $order->items->every(fn ($item) => $item->prep_status === PrepStatus::Ready);
    }

    /** Pesanan terbayar yang butuh keputusan manual (masuk setelah expired atau cancelled). */
    public function ordersNeedingReview(int $branchId): array
    {
        return Order::query()->where('branch_id', $branchId)->where('needs_review', true)->orderBy('paid_at')
            ->get(['id', 'code', 'table_label', 'status', 'total', 'paid_at'])
            ->map(fn (Order $o) => ['id' => $o->id, 'code' => $o->code, 'table' => $o->table_label, 'status' => $o->status->value, 'total' => $o->total])
            ->all();
    }

    /** Semua menu aktif dengan status tersedia/habis di cabang, untuk tombol "habis" satu klik. */
    public function menuAvailability(int $branchId): array
    {
        return DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('branch_items as bi', fn ($join) => $join->on('bi.item_id', '=', 'i.id')->where('bi.branch_id', $branchId))
            ->where('i.is_active', true)->where('c.is_active', true)
            ->orderBy('c.sort_order')->orderBy('i.sort_order')->orderBy('i.id')
            ->get(['i.id', 'i.name', 'i.station', 'c.name as category', DB::raw('COALESCE(bi.is_available, 1) as is_available')])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'station' => $row->station, 'category' => $row->category, 'is_available' => (bool) $row->is_available])
            ->all();
    }

    public function advance(User $user, Order $order): OrderStatus
    {
        $this->authorizeBranch($user, $order->branch_id, $user->role->canManageOrders());

        $next = $order->status->next() ?? throw new OrderRejected('Status tidak bisa dimajukan.');
        $moved = Order::query()->whereKey($order->id)->where('status', $order->status)->update(['status' => $next]);
        if ($moved === 0) {
            throw new OrderRejected('Pesanan sudah diubah oleh orang lain.');
        }
        $order->logStatus($order->status, $next, 'user:' . $user->id);
        return $next;
    }

    /** Batalkan pesanan terbayar dengan alasan; dana dicatat sebagai refund yang diproses di dashboard Kasera. */
    public function cancelWithRefund(User $user, Order $order, string $reason): void
    {
        $this->authorizeBranch($user, $order->branch_id, $user->role->canCancelOrders());
        $reason = trim($reason);
        if ($reason === '') {
            throw new OrderRejected('Alasan wajib diisi.');
        }
        if (!$order->status->isActive()) {
            throw new OrderRejected('Hanya pesanan aktif yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($user, $order, $reason) {
            $previous = $order->status;
            $order->update(['status' => OrderStatus::Cancelled]);
            $order->logStatus($previous, OrderStatus::Cancelled, 'user:' . $user->id, $reason);
            DB::table('refunds')->insert(['order_id' => $order->id, 'amount' => $order->total, 'reason' => $reason, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::record($user, 'order.cancel_refund', 'order', $order->id, $previous->value, 'cancelled: ' . $reason);
        });
    }

    public function setItemAvailability(User $user, int $branchId, int $itemId, bool $available): void
    {
        $this->authorizeBranch($user, $branchId, $user->role->canToggleAvailability());
        DB::table('branch_items')->upsert([['branch_id' => $branchId, 'item_id' => $itemId, 'is_available' => $available]], ['branch_id', 'item_id'], ['is_available']);
        AuditLog::record($user, 'item.availability', 'item', $itemId, null, $available ? 'tersedia' : 'habis');
    }

    public function setBranchOpen(User $user, Branch $branch, bool $open): void
    {
        $this->authorizeBranch($user, $branch->id, $user->role->canOpenCloseBranch());
        $branch->update(['is_open' => $open]);
        AuditLog::record($user, 'branch.open', 'branch', $branch->id, null, $open ? 'buka' : 'tutup');
    }

    public function authorizeBranch(User $user, int $branchId, bool $roleAllowed): void
    {
        if (!$roleAllowed || !$user->canAccessBranch($branchId)) {
            throw new OrderRejected('Tidak punya akses.');
        }
    }

    /** @return array<int, CancellationRequest> pengajuan pembatalan yang menunggu keputusan, per id pesanan */
    private function pendingRequestsByOrder(array $orderIds): array
    {
        return CancellationRequest::query()->with('requester:id,name')->whereIn('order_id', $orderIds)
            ->where('status', 'pending')->get()->keyBy('order_id')->all();
    }

    private function present(Order $order, ?CancellationRequest $request): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'table_id' => $order->dining_table_id,
            'table' => $order->table_label,
            'status' => $order->status->value,
            'total' => $order->total,
            'waiting_seconds' => max(0, (int) now()->diffInSeconds($order->paid_at, true)),
            'customer' => $order->customer_name,
            'cancel_request' => $request === null ? null : [
                'id' => $request->id, 'reason' => $request->reason, 'by' => $request->requester->name,
            ],
            'lines' => $order->items->map(fn ($line) => [
                'id' => $line->id,
                'name' => $line->name,
                'qty' => $line->qty,
                'note' => $line->note,
                'station' => $line->station,
                'prep_status' => $line->prep_status->value,
                'modifiers' => $line->modifiers->pluck('name')->all(),
            ])->all(),
        ];
    }
}

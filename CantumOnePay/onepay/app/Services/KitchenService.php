<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PrepStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/** Layar dapur dan bar: hanya pesanan yang sudah dibayar, dikelompokkan per stasiun. */
class KitchenService
{
    public const STATIONS = ['kitchen' => 'Dapur', 'bar' => 'Bar'];

    public function __construct(private readonly StaffOrderService $staff)
    {
    }

    public function snapshot(User $user, int $branchId, string $station): array
    {
        $this->staff->authorizeBranch($user, $branchId, $user->role->canManageOrders());
        $station = $this->normalizeStation($station);

        $orders = Order::query()->with('items.modifiers')
            ->where('branch_id', $branchId)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])
            ->whereHas('items', fn ($q) => $q->where('station', $station))
            ->orderBy('paid_at')->orderBy('id')->get();

        return [
            'station' => $station,
            'orders' => $orders->map(fn (Order $order) => $this->ticket($order, $station))->all(),
            'menu' => $this->staff->menuAvailability($branchId),
        ];
    }

    /** Ubah tahap satu item. Item pertama yang mulai dibuat memajukan pesanan dari `paid` ke `preparing`. */
    public function setPrep(User $user, OrderItem $item, PrepStatus $status): array
    {
        $order = $item->order;
        $this->staff->authorizeBranch($user, $order->branch_id, $user->role->canManageOrders());
        if (!in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true)) {
            throw new OrderRejected('Pesanan ini sudah tidak ada di antrian dapur.');
        }

        $item->update(['prep_status' => $status]);
        $this->startPreparingOnFirstItem($order, $status, $user);

        return ['prep_status' => $status->value, 'order_status' => $order->refresh()->status->value];
    }

    private function startPreparingOnFirstItem(Order $order, PrepStatus $status, User $user): void
    {
        if ($status === PrepStatus::Queued || $order->status !== OrderStatus::Paid) {
            return;
        }
        $moved = Order::query()->whereKey($order->id)->where('status', OrderStatus::Paid)->update(['status' => OrderStatus::Preparing]);
        if ($moved > 0) {
            $order->logStatus(OrderStatus::Paid, OrderStatus::Preparing, 'user:' . $user->id, 'Mulai dibuat di dapur/bar');
        }
    }

    private function normalizeStation(string $station): string
    {
        return array_key_exists($station, self::STATIONS) ? $station : 'kitchen';
    }

    private function ticket(Order $order, string $station): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'table' => $order->table_label,
            'status' => $order->status->value,
            'waiting_seconds' => max(0, (int) now()->diffInSeconds($order->paid_at, true)),
            'lines' => $order->items->where('station', $station)->values()->map(fn (OrderItem $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'qty' => $line->qty,
                'note' => $line->note,
                'prep_status' => $line->prep_status->value,
                'modifiers' => $line->modifiers->pluck('name')->all(),
            ])->all(),
        ];
    }
}

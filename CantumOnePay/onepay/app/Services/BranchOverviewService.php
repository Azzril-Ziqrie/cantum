<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PrepStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Support\Collection;

/** Ringkasan satu layar untuk semua cabang aktif. Hanya owner. */
class BranchOverviewService
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /** @return list<array<string, mixed>> */
    public function overview(User $user): array
    {
        if (!$user->role->canViewOverview()) {
            throw new OrderRejected('Tidak punya akses.');
        }

        return $user->accessibleBranches()->map(fn (Branch $branch) => $this->summarize($user, $branch))->values()->all();
    }

    private function summarize(User $user, Branch $branch): array
    {
        $active = Order::query()->with('items')->where('branch_id', $branch->id)->whereIn('status', OrderStatus::active())->get();
        $tables = $branch->tables()->where('is_active', true);

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'is_open' => $branch->is_open,
            'counts' => $this->countByStage($active),
            'active_total' => $active->count(),
            'review' => Order::query()->where('branch_id', $branch->id)->where('needs_review', true)->count(),
            'revenue_today' => $this->revenueToday($user, $branch),
            'tables_total' => (clone $tables)->count(),
            'tables_occupied' => $active->pluck('dining_table_id')->unique()->count(),
        ];
    }

    /**
     * Tahap pesanan aktif: baru dibayar, diproses (ada item belum siap), siap disajikan (semua item siap), disajikan.
     *
     * @param Collection<int, Order> $orders
     */
    private function countByStage(Collection $orders): array
    {
        $counts = ['new' => 0, 'preparing' => 0, 'ready' => 0, 'served' => 0];
        foreach ($orders as $order) {
            $counts[$this->stage($order)]++;
        }
        return $counts;
    }

    private function stage(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Paid => 'new',
            OrderStatus::Served => 'served',
            default => $this->allItemsReady($order) ? 'ready' : 'preparing',
        };
    }

    private function allItemsReady(Order $order): bool
    {
        return $order->items->isNotEmpty() && $order->items->every(fn ($item) => $item->prep_status === PrepStatus::Ready);
    }

    /** Omzet bersih hari ini, dihitung oleh ReportService yang sama dengan halaman Laporan. */
    private function revenueToday(User $user, Branch $branch): int
    {
        $today = now()->toDateString();
        $filter = $this->reports->filterFor($user, (string) $branch->id, $today, $today);

        return $this->reports->summary($filter)['net'];
    }
}

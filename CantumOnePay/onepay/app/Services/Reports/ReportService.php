<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Laporan penjualan. Semua angka dihitung dari pesanan yang benar-benar terbayar:
 * `paid_at` terisi dan status bukan pending_payment/expired (pembayaran yang masuk setelah expired
 * ditangani lewat antrian review, bukan omzet).
 *
 * - "dibayar" = semua pesanan terbayar, termasuk yang kemudian dibatalkan.
 * - "terjual" = dibayar dan tidak dibatalkan. Rincian (jam, hari, kategori, menu, meja) memakai ini.
 * - Omzet bersih = omzet bruto semua pesanan dibayar dikurangi refund pesanan tersebut.
 */
class ReportService
{
    public const TOP_LIMIT = 10;

    /** Hanya owner dan manager. Manager hanya cabangnya; cabang di luar akses ditolak. */
    public function filterFor(User $user, ?string $branch, ?string $from, ?string $to): ReportFilter
    {
        if (!$user->role->canViewReports()) {
            throw new AuthorizationException('Tidak punya akses ke laporan.');
        }

        $accessible = $user->accessibleBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($branch === null || $branch === '' || $branch === 'all') ? null : (int) $branch;
        if ($branchId !== null && !in_array($branchId, $accessible, true)) {
            throw new AuthorizationException('Tidak punya akses ke cabang ini.');
        }

        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        return new ReportFilter($branchId === null ? $accessible : [$branchId], $fromDate, $toDate, $branchId);
    }

    /** Owner boleh membandingkan cabang; manager tidak. */
    public function canCompareBranches(User $user, ReportFilter $filter): bool
    {
        return $user->role === Role::Owner && $filter->branchId === null && $filter->isMultiBranch();
    }

    public function build(User $user, ReportFilter $filter): array
    {
        $summary = $this->summary($filter);

        return [
            'summary' => $summary,
            'peak_hour' => $this->peakHour($hours = $this->byHour($filter)),
            'by_hour' => $hours,
            'by_day' => $this->byDay($filter),
            'by_category' => $this->byCategory($filter),
            'top_items' => $this->topItems($filter),
            'low_items' => $this->lowItems($filter),
            'by_method' => $this->byMethod($filter),
            'tables' => $tables = $this->tables($filter),
            'areas' => $this->areas($tables),
            'funnel' => $this->funnel($filter, $summary['paid_orders']),
            'branches' => $this->canCompareBranches($user, $filter) ? $this->compareBranches($filter) : [],
        ];
    }

    /* ---------- ringkasan ---------- */

    public function summary(ReportFilter $filter): array
    {
        $row = $this->paidOrders($filter)->selectRaw(
            "COUNT(*) AS paid_orders,
             COALESCE(SUM(orders.total), 0) AS gross,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.total END), 0) AS sold_total,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.subtotal END), 0) AS subtotal,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.discount END), 0) AS discount,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.service END), 0) AS service,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.tax END), 0) AS tax,
             COALESCE(SUM(CASE WHEN orders.status = 'cancelled' THEN 0 ELSE orders.rounding END), 0) AS rounding"
        )->first();

        $paid = (int) $row->paid_orders;
        $cancelled = (int) $row->cancelled_orders;
        $sold = $paid - $cancelled;
        $gross = (int) $row->gross;
        $refunds = $this->refundTotal($filter);

        return [
            'paid_orders' => $paid,
            'sold_orders' => $sold,
            'cancelled_orders' => $cancelled,
            'gross' => $gross,
            'refunds' => $refunds,
            'net' => $gross - $refunds,
            'average_order' => $sold > 0 ? (int) round($row->sold_total / $sold) : 0,
            'cancel_rate' => $paid > 0 ? $cancelled / $paid : 0.0,
            'refund_rate' => $gross > 0 ? $refunds / $gross : 0.0,
            'subtotal' => (int) $row->subtotal,
            'discount' => (int) $row->discount,
            'service' => (int) $row->service,
            'tax' => (int) $row->tax,
            'rounding' => (int) $row->rounding,
        ];
    }

    private function refundTotal(ReportFilter $filter, ?int $branchId = null): int
    {
        $orders = $this->paidOrders($filter, $branchId)->select('orders.id');

        return (int) DB::table('refunds')->joinSub($orders, 'paid', 'paid.id', '=', 'refunds.order_id')->sum('refunds.amount');
    }

    /* ---------- rincian waktu ---------- */

    /** @return list<array{hour:int,orders:int,revenue:int}> 24 jam penuh */
    public function byHour(ReportFilter $filter): array
    {
        $rows = $this->soldOrders($filter)
            ->selectRaw($this->hourExpression() . ' AS h, COUNT(*) AS orders, SUM(orders.total) AS revenue')
            ->groupBy('h')->get()->keyBy('h');

        return array_map(fn (int $hour) => [
            'hour' => $hour,
            'orders' => (int) ($rows[$hour]->orders ?? 0),
            'revenue' => (int) ($rows[$hour]->revenue ?? 0),
        ], range(0, 23));
    }

    /** Jam dengan omzet tertinggi, atau null bila belum ada penjualan. */
    public function peakHour(array $hours): ?int
    {
        $best = collect($hours)->sortByDesc('revenue')->first();
        return ($best['revenue'] ?? 0) > 0 ? $best['hour'] : null;
    }

    /** @return list<array{date:string,orders:int,revenue:int}> setiap tanggal dalam rentang */
    public function byDay(ReportFilter $filter): array
    {
        $rows = $this->soldOrders($filter)
            ->selectRaw($this->dateExpression() . ' AS d, COUNT(*) AS orders, SUM(orders.total) AS revenue')
            ->groupBy('d')->get()->keyBy('d');

        $days = [];
        for ($i = 0; $i < $filter->dayCount(); $i++) {
            $date = $filter->from->addDays($i)->toDateString();
            $days[] = ['date' => $date, 'orders' => (int) ($rows[$date]->orders ?? 0), 'revenue' => (int) ($rows[$date]->revenue ?? 0)];
        }
        return $days;
    }

    /* ---------- menu dan kategori ---------- */

    public function byCategory(ReportFilter $filter): array
    {
        return $this->soldLines($filter)
            ->leftJoin('items as i', 'i.id', '=', 'oi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->selectRaw("COALESCE(c.name, 'Tanpa kategori') AS category, SUM(oi.qty) AS qty, SUM(oi.unit_price * oi.qty) AS revenue")
            ->groupBy('category')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['name' => $r->category, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue])->all();
    }

    /** Menu terlaris menurut jumlah terjual, dengan margin = harga jual - HPP (snapshot saat pesanan dibuat). */
    public function topItems(ReportFilter $filter): array
    {
        return array_slice($this->itemSales($filter), 0, self::TOP_LIMIT);
    }

    /** Menu kurang laku: yang terjual paling sedikit, termasuk menu aktif yang belum terjual sama sekali. */
    public function lowItems(ReportFilter $filter): array
    {
        $sales = collect($this->itemSales($filter))->keyBy('name');
        $unsold = Item::query()->where('is_active', true)->pluck('name')
            ->reject(fn ($name) => $sales->has($name))
            ->map(fn ($name) => ['name' => $name, 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'margin' => 0, 'margin_rate' => 0.0]);

        return $sales->values()->concat($unsold)
            ->sortBy([['qty', 'asc'], ['name', 'asc']])->take(self::TOP_LIMIT)->values()->all();
    }

    private function itemSales(ReportFilter $filter): array
    {
        return $this->soldLines($filter)
            ->selectRaw('oi.name AS name, SUM(oi.qty) AS qty, SUM(oi.unit_price * oi.qty) AS revenue, SUM(oi.cost * oi.qty) AS cost')
            ->groupBy('oi.name')->get()
            ->map(function ($r) {
                $revenue = (int) $r->revenue;
                $margin = $revenue - (int) $r->cost;
                return [
                    'name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => $revenue, 'cost' => (int) $r->cost,
                    'margin' => $margin, 'margin_rate' => $revenue > 0 ? $margin / $revenue : 0.0,
                ];
            })
            ->sortBy([['qty', 'desc'], ['name', 'asc']])->values()->all();
    }

    /* ---------- pembayaran, meja, konversi ---------- */

    public function byMethod(ReportFilter $filter): array
    {
        return $this->soldOrders($filter)
            ->join('payments as p', 'p.order_id', '=', 'orders.id')
            ->selectRaw('p.method AS method, COUNT(*) AS orders, SUM(orders.total) AS revenue')
            ->groupBy('p.method')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['method' => $r->method, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue])->all();
    }

    /** Performa per meja: pesanan terjual, omzet, jumlah scan QR, dan konversi (pesanan terbayar per scan). */
    public function tables(ReportFilter $filter): array
    {
        $scans = DB::table('table_scans')->whereIn('branch_id', $filter->branchIds)
            ->whereBetween('scanned_at', [$filter->start(), $filter->end()])
            ->selectRaw('dining_table_id, COUNT(*) AS scans')->groupBy('dining_table_id')->pluck('scans', 'dining_table_id');

        $sold = $this->soldOrders($filter)
            ->join('dining_tables as t', 't.id', '=', 'orders.dining_table_id')
            ->leftJoin('floor_areas as a', 'a.id', '=', 't.floor_area_id')
            ->selectRaw("t.id AS id, t.label AS label, COALESCE(a.name, 'Tanpa area') AS area, COUNT(*) AS orders, SUM(orders.total) AS revenue")
            ->groupBy('t.id', 't.label', 'a.name')->get()->keyBy('id');

        $tables = DB::table('dining_tables as t')->leftJoin('floor_areas as a', 'a.id', '=', 't.floor_area_id')
            ->whereIn('t.branch_id', $filter->branchIds)->where(fn ($q) => $q->where('t.is_active', true)->orWhereIn('t.id', $sold->keys()))
            ->selectRaw("t.id AS id, t.label AS label, COALESCE(a.name, 'Tanpa area') AS area")->get();

        return $tables->map(function ($t) use ($sold, $scans) {
            $orders = (int) ($sold[$t->id]->orders ?? 0);
            $scanCount = (int) ($scans[$t->id] ?? 0);
            return [
                'label' => $t->label, 'area' => $t->area, 'orders' => $orders, 'revenue' => (int) ($sold[$t->id]->revenue ?? 0),
                'scans' => $scanCount, 'conversion' => $scanCount > 0 ? min(1.0, $orders / $scanCount) : null,
            ];
        })->sortBy([['revenue', 'desc'], ['label', 'asc']])->values()->all();
    }

    public function areas(array $tables): array
    {
        return collect($tables)->groupBy('area')->map(function ($rows, $area) {
            $orders = $rows->sum('orders');
            $scans = $rows->sum('scans');
            return [
                'area' => $area, 'orders' => $orders, 'revenue' => $rows->sum('revenue'), 'scans' => $scans,
                'conversion' => $scans > 0 ? min(1.0, $orders / $scans) : null,
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    /** Corong: scan QR -> pesanan dibuat -> pesanan terbayar. */
    public function funnel(ReportFilter $filter, int $paidOrders): array
    {
        $scans = DB::table('table_scans')->whereIn('branch_id', $filter->branchIds)
            ->whereBetween('scanned_at', [$filter->start(), $filter->end()])->count();
        $created = DB::table('orders')->whereIn('branch_id', $filter->branchIds)
            ->whereBetween('created_at', [$filter->start(), $filter->end()])->count();

        return [
            'scans' => $scans, 'created' => $created, 'paid' => $paidOrders,
            'scan_to_paid' => $scans > 0 ? min(1.0, $paidOrders / $scans) : null,
        ];
    }

    /* ---------- perbandingan cabang (owner) ---------- */

    public function compareBranches(ReportFilter $filter): array
    {
        $names = Branch::query()->whereIn('id', $filter->branchIds)->pluck('name', 'id');

        return $names->map(function (string $name, int $branchId) use ($filter) {
            $branchFilter = new ReportFilter([$branchId], $filter->from, $filter->to, $branchId);
            $summary = $this->summary($branchFilter);
            return ['branch' => $name] + array_intersect_key($summary, array_flip(['sold_orders', 'gross', 'refunds', 'net', 'average_order', 'cancel_rate']));
        })->sortByDesc('net')->values()->all();
    }

    /* ---------- tutup harian ---------- */

    /**
     * Rekonsiliasi dengan gateway untuk satu tanggal. "Diterima gateway" = semua pesanan dengan paid_at pada tanggal itu,
     * apa pun statusnya sekarang (termasuk dibatalkan/refund dan pembayaran terlambat), karena dana memang masuk.
     */
    public function dailyClose(User $user, ?string $branch, ?string $date): array
    {
        $day = $this->parseDate($date) ?? CarbonImmutable::now()->startOfDay();
        $filter = $this->filterFor($user, $branch, $day->toDateString(), $day->toDateString());
        $range = [$filter->start(), $filter->end()];

        $received = DB::table('orders')->join('payments as p', 'p.order_id', '=', 'orders.id')
            ->whereIn('orders.branch_id', $filter->branchIds)->whereNotNull('orders.paid_at')->whereBetween('orders.paid_at', $range)
            ->selectRaw("p.method AS method, COUNT(*) AS count, SUM(orders.total) AS amount,
                SUM(CASE WHEN orders.needs_review = 1 THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN orders.needs_review = 1 THEN orders.total ELSE 0 END) AS late_amount,
                SUM(CASE WHEN orders.status = 'cancelled' AND orders.needs_review = 0 THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN orders.status = 'cancelled' AND orders.needs_review = 0 THEN orders.total ELSE 0 END) AS cancelled_amount")
            ->groupBy('p.method')->get()->keyBy('method');

        $unpaid = DB::table('orders')->join('payments as p', 'p.order_id', '=', 'orders.id')
            ->whereIn('orders.branch_id', $filter->branchIds)->whereNull('orders.paid_at')->whereBetween('orders.created_at', $range)
            ->selectRaw("p.method AS method,
                SUM(CASE WHEN orders.status = 'pending_payment' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN orders.status = 'pending_payment' THEN orders.total ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN orders.status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
                SUM(CASE WHEN orders.status = 'expired' THEN orders.total ELSE 0 END) AS expired_amount")
            ->groupBy('p.method')->get()->keyBy('method');

        $rows = collect($received->keys())->merge($unpaid->keys())->unique()->sort()->values()->map(function ($method) use ($received, $unpaid) {
            $r = $received[$method] ?? null;
            $u = $unpaid[$method] ?? null;
            return [
                'method' => $method,
                'count' => (int) ($r->count ?? 0), 'amount' => (int) ($r->amount ?? 0),
                'late_count' => (int) ($r->late_count ?? 0), 'late_amount' => (int) ($r->late_amount ?? 0),
                'cancelled_count' => (int) ($r->cancelled_count ?? 0), 'cancelled_amount' => (int) ($r->cancelled_amount ?? 0),
                'pending_count' => (int) ($u->pending_count ?? 0), 'pending_amount' => (int) ($u->pending_amount ?? 0),
                'expired_count' => (int) ($u->expired_count ?? 0), 'expired_amount' => (int) ($u->expired_amount ?? 0),
            ];
        })->all();

        $totals = [];
        foreach (['count', 'amount', 'late_count', 'late_amount', 'cancelled_count', 'cancelled_amount', 'pending_count', 'pending_amount', 'expired_count', 'expired_amount'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return ['date' => $day, 'filter' => $filter, 'rows' => $rows, 'totals' => $totals,
            'refunds' => $this->refundsCreatedOn($filter, $range)];
    }

    /** Refund yang dicatat pada tanggal itu (dana yang perlu dikembalikan lewat dashboard Kasera). */
    private function refundsCreatedOn(ReportFilter $filter, array $range): int
    {
        return (int) DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')
            ->whereIn('orders.branch_id', $filter->branchIds)->whereBetween('refunds.created_at', $range)->sum('refunds.amount');
    }

    /* ---------- query dasar ---------- */

    /** Pesanan terbayar: paid_at terisi, status bukan pending/expired, paid_at dalam rentang. */
    private function paidOrders(ReportFilter $filter, ?int $branchId = null): Builder
    {
        return DB::table('orders')
            ->whereIn('orders.branch_id', $branchId !== null ? [$branchId] : $filter->branchIds)
            ->whereNotNull('orders.paid_at')
            ->whereNotIn('orders.status', [OrderStatus::PendingPayment->value, OrderStatus::Expired->value])
            ->whereBetween('orders.paid_at', [$filter->start(), $filter->end()]);
    }

    private function soldOrders(ReportFilter $filter): Builder
    {
        return $this->paidOrders($filter)->where('orders.status', '!=', OrderStatus::Cancelled->value);
    }

    /** Baris pesanan (order_items) dari pesanan terjual. */
    private function soldLines(ReportFilter $filter): Builder
    {
        return DB::table('order_items as oi')
            ->joinSub($this->soldOrders($filter)->select('orders.id'), 'sold', 'sold.id', '=', 'oi.order_id');
    }

    /* ---------- ekspresi SQL beda driver, dan tanggal ---------- */

    private function hourExpression(): string
    {
        return DB::getDriverName() === 'sqlite' ? "CAST(strftime('%H', orders.paid_at) AS INTEGER)" : 'HOUR(orders.paid_at)';
    }

    private function dateExpression(): string
    {
        return DB::getDriverName() === 'sqlite' ? 'date(orders.paid_at)' : 'DATE(orders.paid_at)';
    }

    /** Default 7 hari terakhir termasuk hari ini; urutan dibalik bila terbalik; maksimal setahun. */
    private function resolveRange(?string $from, ?string $to): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $toDate = $this->parseDate($to) ?? $today;
        $fromDate = $this->parseDate($from) ?? $toDate->subDays(6);

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }
        if ($fromDate->diffInDays($toDate) >= ReportFilter::MAX_DAYS) {
            $fromDate = $toDate->subDays(ReportFilter::MAX_DAYS - 1);
        }
        return [$fromDate, $toDate];
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

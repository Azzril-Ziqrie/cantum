<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Item;
use App\Models\TableScan;
use App\Models\User;
use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fixture (zona waktu aplikasi), Cabang A, tanggal 2026-11-18:
 *   O1 completed  qris    paid 12:10  total 100.000  Kopi 2x20.000 (HPP 8.000) + Nasi 2x30.000 (HPP 12.000)
 *   O2 served     va_bca  paid 13:05  total  50.000  Kopi 1x20.000 + Nasi 1x30.000
 *   O3 cancelled  qris    paid 12:30  total  30.000  Nasi 1x30.000, refund 30.000
 *   O4 pending    qris    belum bayar total  99.999
 *   O5 expired    qris    paid 14:00  total  77.777  (bayar terlambat, needs_review)
 * Cabang B, 2026-11-19: O6 completed qris paid 09:00 total 70.000, Teh 1x70.000.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $a;
    private Branch $b;
    private int $tableA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 15:00:00');
        Brand::create(['id' => 1, 'name' => 'Kopi Uji']);

        $this->a = Branch::create(['name' => 'Cabang A']);
        $this->b = Branch::create(['name' => 'Cabang B']);
        $areaA = DB::table('floor_areas')->insertGetId(['branch_id' => $this->a->id, 'name' => 'Indoor']);
        $this->tableA = DB::table('dining_tables')->insertGetId(['branch_id' => $this->a->id, 'floor_area_id' => $areaA, 'label' => 'A1', 'token' => str_repeat('a', 24)]);
        $tableB = DB::table('dining_tables')->insertGetId(['branch_id' => $this->b->id, 'label' => 'B1', 'token' => str_repeat('b', 24)]);

        $o1 = $this->order($this->a, $this->tableA, 'O1', 'completed', '2026-11-18 12:10:00', 100000, 'qris');
        $this->line($o1, 'Kopi', 2, 20000, 8000);
        $this->line($o1, 'Nasi', 2, 30000, 12000);

        $o2 = $this->order($this->a, $this->tableA, 'O2', 'served', '2026-11-18 13:05:00', 50000, 'va_bca');
        $this->line($o2, 'Kopi', 1, 20000, 8000);
        $this->line($o2, 'Nasi', 1, 30000, 12000);

        $o3 = $this->order($this->a, $this->tableA, 'O3', 'cancelled', '2026-11-18 12:30:00', 30000, 'qris');
        $this->line($o3, 'Nasi', 1, 30000, 12000);
        DB::table('refunds')->insert(['order_id' => $o3, 'amount' => 30000, 'reason' => 'Bahan habis', 'created_at' => '2026-11-18 12:40:00', 'updated_at' => '2026-11-18 12:40:00']);

        $this->order($this->a, $this->tableA, 'O4', 'pending_payment', null, 99999, 'qris');
        $this->order($this->a, $this->tableA, 'O5', 'expired', '2026-11-18 14:00:00', 77777, 'qris', needsReview: true);

        $o6 = $this->order($this->b, $tableB, 'O6', 'completed', '2026-11-19 09:00:00', 70000, 'qris');
        $this->line($o6, 'Teh', 1, 70000, 10000);

        foreach (range(1, 4) as $_) {
            DB::table('table_scans')->insert(['branch_id' => $this->a->id, 'dining_table_id' => $this->tableA, 'scanned_at' => '2026-11-18 11:00:00']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(Branch $branch, int $tableId, string $code, string $status, ?string $paidAt, int $total, string $method, bool $needsReview = false): int
    {
        $createdAt = $paidAt ?? '2026-11-18 11:30:00';
        $id = DB::table('orders')->insertGetId([
            'code' => $code, 'branch_id' => $branch->id, 'dining_table_id' => $tableId, 'table_label' => 'X', 'status' => $status,
            'needs_review' => $needsReview, 'subtotal' => $total, 'discount' => 0, 'service' => 0, 'tax' => 0, 'rounding' => 0, 'total' => $total,
            'expires_at' => $createdAt, 'paid_at' => $paidAt, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        DB::table('payments')->insert(['order_id' => $id, 'method' => $method, 'amount' => $total, 'status' => $paidAt ? 'paid' : 'pending', 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        return $id;
    }

    private function line(int $orderId, string $name, int $qty, int $price, int $cost): void
    {
        DB::table('order_items')->insert(['order_id' => $orderId, 'name' => $name, 'unit_price' => $price, 'cost' => $cost, 'qty' => $qty]);
    }

    private function user(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        if ($branch !== null) {
            $user->branches()->attach($branch);
        }
        return $user;
    }

    private function report(User $user, string $branch, string $from, string $to): array
    {
        $service = app(ReportService::class);
        return $service->build($user, $service->filterFor($user, $branch, $from, $to));
    }

    public function test_summary_counts_only_paid_orders_and_subtracts_refunds(): void
    {
        $s = $this->report($this->user(Role::Owner), (string) $this->a->id, '2026-11-18', '2026-11-18')['summary'];

        $this->assertSame(3, $s['paid_orders'], 'O4 (pending) dan O5 (expired) tidak dihitung.');
        $this->assertSame(1, $s['cancelled_orders']);
        $this->assertSame(2, $s['sold_orders']);
        $this->assertSame(180000, $s['gross']);
        $this->assertSame(30000, $s['refunds']);
        $this->assertSame(150000, $s['net']);
        $this->assertSame(75000, $s['average_order']);
        $this->assertEqualsWithDelta(1 / 3, $s['cancel_rate'], 0.0001);
        $this->assertEqualsWithDelta(30000 / 180000, $s['refund_rate'], 0.0001);
    }

    public function test_hours_days_categories_and_methods_exclude_cancelled_orders(): void
    {
        $r = $this->report($this->user(Role::Owner), (string) $this->a->id, '2026-11-17', '2026-11-18');

        $this->assertCount(24, $r['by_hour']);
        $this->assertSame(100000, $r['by_hour'][12]['revenue']);
        $this->assertSame(50000, $r['by_hour'][13]['revenue']);
        $this->assertSame(12, $r['peak_hour']);

        $this->assertSame([0, 150000], [$r['by_day'][0]['revenue'], $r['by_day'][1]['revenue']]);
        $this->assertSame('2026-11-17', $r['by_day'][0]['date']);

        $methods = collect($r['by_method'])->keyBy('method');
        $this->assertSame(100000, $methods['qris']['revenue']);
        $this->assertSame(50000, $methods['va_bca']['revenue']);
    }

    public function test_top_items_use_snapshot_cost_for_margin(): void
    {
        $category = Category::create(['name' => 'Menu']);
        Item::create(['category_id' => $category->id, 'name' => 'Belum Laku', 'price' => 10000]);

        $r = $this->report($this->user(Role::Owner), (string) $this->a->id, '2026-11-18', '2026-11-18');

        $kopi = $r['top_items'][0];
        $this->assertSame(['Kopi', 3, 60000, 24000, 36000], [$kopi['name'], $kopi['qty'], $kopi['revenue'], $kopi['cost'], $kopi['margin']]);
        $this->assertEqualsWithDelta(0.6, $kopi['margin_rate'], 0.0001);

        $nasi = $r['top_items'][1];
        $this->assertSame(['Nasi', 3, 90000, 54000], [$nasi['name'], $nasi['qty'], $nasi['revenue'], $nasi['margin']], 'Nasi dari pesanan O3 yang dibatalkan tidak ikut.');

        $this->assertSame('Belum Laku', $r['low_items'][0]['name'], 'Menu aktif tanpa penjualan masuk daftar kurang laku.');
        $this->assertSame(0, $r['low_items'][0]['qty']);
    }

    public function test_table_performance_and_scan_conversion(): void
    {
        $r = $this->report($this->user(Role::Owner), (string) $this->a->id, '2026-11-18', '2026-11-18');

        $table = $r['tables'][0];
        $this->assertSame(['A1', 2, 150000, 4], [$table['label'], $table['orders'], $table['revenue'], $table['scans']]);
        $this->assertEqualsWithDelta(0.5, $table['conversion'], 0.0001);
        $this->assertSame('Indoor', $r['areas'][0]['area']);

        $this->assertSame(4, $r['funnel']['scans']);
        $this->assertSame(5, $r['funnel']['created']);
        $this->assertSame(3, $r['funnel']['paid']);
        $this->assertEqualsWithDelta(0.75, $r['funnel']['scan_to_paid'], 0.0001);
    }

    public function test_owner_sees_all_branches_and_comparison_but_manager_only_own_branch(): void
    {
        $owner = $this->report($this->user(Role::Owner), 'all', '2026-11-18', '2026-11-19');
        $this->assertSame(150000 + 70000, $owner['summary']['net']);
        $this->assertCount(2, $owner['branches']);
        $this->assertSame('Cabang A', $owner['branches'][0]['branch']);

        $manager = $this->report($this->user(Role::Manager, $this->a), 'all', '2026-11-18', '2026-11-19');
        $this->assertSame(150000, $manager['summary']['net'], 'Manager "semua cabang" tetap hanya cabangnya.');
        $this->assertSame([], $manager['branches']);
    }

    public function test_manager_cannot_open_another_branch_and_other_roles_are_denied(): void
    {
        $manager = $this->user(Role::Manager, $this->a);

        $this->actingAs($manager)->get(route('admin.reports', ['branch' => $this->b->id]))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.reports', ['branch' => $this->a->id]))->assertOk();

        foreach ([Role::Cashier, Role::Kitchen, Role::Warehouse] as $role) {
            $this->actingAs($this->user($role, $this->a))->get(route('admin.reports'))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('admin.reports'))->assertRedirect('/staff/login');
    }

    public function test_daily_close_reconciles_everything_the_gateway_received(): void
    {
        $close = app(ReportService::class)->dailyClose($this->user(Role::Owner), (string) $this->a->id, '2026-11-18');
        $rows = collect($close['rows'])->keyBy('method');

        $this->assertSame(4, $close['totals']['count'], 'O1, O2, O3, dan O5 (terlambat) sudah diterima gateway.');
        $this->assertSame(257777, $close['totals']['amount']);
        $this->assertSame([1, 77777], [$close['totals']['late_count'], $close['totals']['late_amount']]);
        $this->assertSame([1, 30000], [$close['totals']['cancelled_count'], $close['totals']['cancelled_amount']]);
        $this->assertSame([1, 99999], [$close['totals']['pending_count'], $close['totals']['pending_amount']]);
        $this->assertSame(30000, $close['refunds']);
        $this->assertSame([3, 207777], [$rows['qris']['count'], $rows['qris']['amount']]);
        $this->assertSame([1, 50000], [$rows['va_bca']['count'], $rows['va_bca']['amount']]);
    }

    public function test_csv_export_is_a_utf8_download_with_net_revenue_and_neutralised_formulas(): void
    {
        $category = Category::create(['name' => 'Menu']);
        Item::create(['category_id' => $category->id, 'name' => '=HYPERLINK("x")', 'price' => 1000]);

        $response = $this->actingAs($this->user(Role::Owner))->get(route('admin.reports.csv', ['branch' => $this->a->id, 'from' => '2026-11-18', 'to' => '2026-11-18']));

        $response->assertOk()->assertDownload('laporan-penjualan-2026-11-18_2026-11-18.csv');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Omzet bersih",150000', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    public function test_pages_render_for_owner(): void
    {
        $owner = $this->user(Role::Owner);
        $query = ['branch' => 'all', 'from' => '2026-11-18', 'to' => '2026-11-19'];

        $this->actingAs($owner)->get(route('admin.reports', $query))->assertOk()->assertSee('Omzet bersih')->assertSee('Perbandingan antar cabang');
        $this->actingAs($owner)->get(route('admin.reports.print', $query))->assertOk()->assertSee('Cetak / simpan PDF');
        $this->actingAs($owner)->get(route('admin.reports.close', ['date' => '2026-11-18']))->assertOk()->assertSee('Diterima gateway');
        $this->actingAs($owner)->get(route('admin.reports.close.csv', ['date' => '2026-11-18']))->assertOk();
    }

    public function test_range_is_swapped_when_reversed_and_capped_at_a_year(): void
    {
        $service = app(ReportService::class);
        $owner = $this->user(Role::Owner);

        $reversed = $service->filterFor($owner, 'all', '2026-11-19', '2026-11-18');
        $this->assertSame('2026-11-18', $reversed->from->toDateString());

        $capped = $service->filterFor($owner, 'all', '2020-01-01', '2026-11-19');
        $this->assertSame(366, $capped->dayCount());
    }

    public function test_scan_is_recorded_once_per_session_and_table(): void
    {
        $table = \App\Models\DiningTable::query()->findOrFail($this->tableA);
        $request = Request::create('/t/x');
        $request->setLaravelSession(app('session')->driver('array'));
        $before = TableScan::query()->count();

        TableScan::record($table, $request);
        TableScan::record($table, $request);
        $this->assertSame($before + 1, TableScan::query()->count());

        Carbon::setTestNow(now()->addMinutes(TableScan::DEDUPE_MINUTES + 1));
        TableScan::record($table, $request);
        $this->assertSame($before + 2, TableScan::query()->count());
    }

    public function test_opening_the_customer_menu_logs_a_scan(): void
    {
        $before = TableScan::query()->count();

        $this->get(route('menu.show', str_repeat('a', 24)))->assertOk();

        $this->assertSame($before + 1, TableScan::query()->count());
    }
}

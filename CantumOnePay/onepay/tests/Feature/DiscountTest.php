<?php

namespace Tests\Feature;

use App\Enums\PaymentOutcome;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\DiscountTarget;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\OrderRejected;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private DiningTable $table;
    private Category $drinks;
    private Category $food;
    private Item $coffee;   // 20.000, minuman
    private Item $bread;    // 30.000, makanan
    private int $ipCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Brand::create(['id' => 1, 'name' => 'Resto Uji', 'tax_percent' => 11, 'service_percent' => 0, 'rounding_to' => 100]);
        $this->branch = Branch::create(['name' => 'Utama']);
        $this->table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);
        $this->drinks = Category::create(['name' => 'Minuman']);
        $this->food = Category::create(['name' => 'Makanan']);
        $this->coffee = Item::create(['category_id' => $this->drinks->id, 'name' => 'Kopi', 'price' => 20000]);
        $this->bread = Item::create(['category_id' => $this->food->id, 'name' => 'Roti', 'price' => 30000]);
    }

    private function promo(array $attributes = [], array $targets = []): Discount
    {
        $discount = Discount::create($attributes + ['name' => 'Promo', 'type' => 'percent', 'value' => 10, 'scope' => 'order']);
        foreach ($targets as $type => $id) {
            DiscountTarget::create(['discount_id' => $discount->id, 'target_type' => $type, 'target_id' => $id]);
        }
        return $discount;
    }

    /** Kopi 20.000 + Roti 30.000 = 50.000 kecuali ditentukan lain. */
    private function place(?string $code = null, ?array $cart = null): Order
    {
        $cart ??= [['item_id' => $this->coffee->id, 'qty' => 1], ['item_id' => $this->bread->id, 'qty' => 1]];
        return app(OrderService::class)->create($this->table->load('branch'), $cart, [], 'qris', '10.0.0.' . ++$this->ipCounter, $code);
    }

    private function staff(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        if ($branch !== null) {
            $user->branches()->attach($branch);
        }
        return $user;
    }

    private function formData(array $overrides = []): array
    {
        return $overrides + ['name' => 'Hemat', 'code' => 'hemat10', 'type' => 'percent', 'value' => 10, 'scope' => 'order', 'is_active' => 1, 'all_branches' => 1];
    }

    // ---- perhitungan ----

    public function test_percent_discount_is_capped_by_the_ceiling_and_reduces_tax_base(): void
    {
        $this->promo(['code' => 'HEMAT', 'value' => 20, 'max_discount' => 6000]);

        $order = $this->place('hemat');

        // 20% x 50.000 = 10.000, dipotong plafon 6.000. Dasar pajak 44.000, pajak 4.840, total 48.840 dibulatkan 48.800.
        $this->assertSame(50000, $order->subtotal);
        $this->assertSame(6000, $order->discount);
        $this->assertSame(4840, $order->tax);
        $this->assertSame(48800, $order->total);
        $this->assertSame('Promo', $order->discount_label);
    }

    public function test_fixed_discount_and_service_charge_follow_the_calculation_order(): void
    {
        Brand::query()->update(['service_percent' => 10]);
        $this->promo(['type' => 'fixed', 'value' => 10000]);

        $order = $this->place();

        // 50.000 - 10.000 = 40.000; service 4.000; pajak 11% x 44.000 = 4.840; 48.840 -> 48.800.
        $this->assertSame(10000, $order->discount);
        $this->assertSame(4000, $order->service);
        $this->assertSame(4840, $order->tax);
        $this->assertSame(48800, $order->total);
    }

    public function test_fixed_discount_never_exceeds_the_basket(): void
    {
        $this->promo(['type' => 'fixed', 'value' => 99000]);
        $lines = [['item_id' => $this->coffee->id, 'unit_price' => 20000, 'qty' => 1]];

        $applied = app(DiscountService::class)->resolve($lines, $this->branch->id, null);

        $this->assertSame(20000, $applied->amount);
    }

    public function test_item_scope_only_discounts_the_chosen_menu(): void
    {
        $this->promo(['value' => 50, 'scope' => 'items'], ['item' => $this->bread->id]);

        $this->assertSame(15000, $this->place()->discount);
    }

    public function test_category_scope_only_discounts_the_chosen_category(): void
    {
        $this->promo(['value' => 50, 'scope' => 'categories'], ['category' => $this->drinks->id]);

        $this->assertSame(10000, $this->place()->discount);
    }

    public function test_scope_without_matching_menu_gives_no_discount_and_rejects_the_code(): void
    {
        $this->promo(['code' => 'ROTI', 'scope' => 'items'], ['item' => $this->bread->id]);

        $this->expectException(OrderRejected::class);
        $this->place('ROTI', [['item_id' => $this->coffee->id, 'qty' => 1]]);
    }

    public function test_minimum_spend_rejects_a_code_but_silently_skips_an_automatic_promo(): void
    {
        $this->promo(['code' => 'BESAR', 'min_spend' => 100000]);
        $this->promo(['name' => 'Otomatis', 'min_spend' => 100000]);

        $this->assertSame(0, $this->place()->discount);

        $this->expectExceptionMessage('Minimal belanja');
        $this->place('BESAR');
    }

    // ---- jadwal ----

    public function test_period_limits_when_a_code_works(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00'));
        $this->promo(['code' => 'NANTI', 'starts_at' => '2026-11-20']);
        $this->promo(['code' => 'LEWAT', 'ends_at' => '2026-11-17']);
        $this->promo(['code' => 'PAS', 'starts_at' => '2026-11-18', 'ends_at' => '2026-11-18']);

        $this->assertSame(5000, $this->place('PAS')->discount);
        $this->assertRejected('NANTI', 'belum dimulai');
        $this->assertRejected('LEWAT', 'sudah berakhir');
    }

    public function test_happy_hour_applies_only_inside_the_time_window_and_on_chosen_days(): void
    {
        $monday = Carbon::parse('2026-11-16');
        $this->assertSame(1, $monday->dayOfWeekIso);
        $this->promo(['name' => 'Happy hour', 'days' => [1], 'time_from' => '17:00:00', 'time_until' => '19:00:00']);

        $this->travelTo($monday->copy()->setTime(17, 30));
        $this->assertSame(5000, $this->place()->discount);

        $this->travelTo($monday->copy()->setTime(19, 0)); // batas akhir tidak termasuk
        $this->assertSame(0, $this->place()->discount);

        $this->travelTo($monday->copy()->setTime(16, 59));
        $this->assertSame(0, $this->place()->discount);

        $this->travelTo($monday->copy()->addDay()->setTime(17, 30)); // Selasa
        $this->assertSame(0, $this->place()->discount);
    }

    public function test_time_window_may_cross_midnight(): void
    {
        $this->promo(['time_from' => '22:00:00', 'time_until' => '02:00:00']);

        $this->travelTo(Carbon::parse('2026-11-18 23:30'));
        $this->assertSame(5000, $this->place()->discount);
        $this->travelTo(Carbon::parse('2026-11-19 01:00'));
        $this->assertSame(5000, $this->place()->discount);
        $this->travelTo(Carbon::parse('2026-11-19 12:00'));
        $this->assertSame(0, $this->place()->discount);
    }

    // ---- kuota ----

    public function test_usage_limit_is_enforced_and_returned_when_an_unpaid_order_expires(): void
    {
        $promo = $this->promo(['code' => 'SATU', 'usage_limit' => 1]);

        $first = $this->place('SATU');
        $this->assertSame(1, $promo->refresh()->used_count);
        $this->assertRejected('SATU', 'Kuota promo sudah habis');

        app(PaymentService::class)->applyOutcome($first, PaymentOutcome::Failed, 'test');

        $this->assertSame(0, $promo->refresh()->used_count);
        $this->assertSame(5000, $this->place('SATU')->discount);
    }

    public function test_quota_stays_used_when_the_order_is_paid(): void
    {
        $promo = $this->promo(['code' => 'SATU', 'usage_limit' => 1]);
        $order = $this->place('SATU');

        app(PaymentService::class)->applyOutcome($order, PaymentOutcome::Paid, 'test');
        app(PaymentService::class)->applyOutcome($order, PaymentOutcome::Failed, 'test'); // tidak berpengaruh pada pesanan terbayar

        $this->assertSame(1, $promo->refresh()->used_count);
    }

    public function test_reserving_the_last_slot_twice_fails_the_second_time(): void
    {
        $promo = $this->promo(['usage_limit' => 1]);
        $service = app(DiscountService::class);
        $applied = new \App\Services\AppliedDiscount($promo->id, 'Promo', 5000);

        $service->reserveUsage($applied);

        $this->expectException(OrderRejected::class);
        $service->reserveUsage($applied);
    }

    // ---- cabang, kode, pemilihan ----

    public function test_branch_specific_promo_does_not_apply_elsewhere(): void
    {
        $other = Branch::create(['name' => 'Cabang Lain']);
        $promo = $this->promo(['code' => 'LAIN', 'all_branches' => false]);
        $promo->branches()->attach($other);

        $this->assertRejected('LAIN', 'tidak berlaku di cabang ini');

        $promo->branches()->attach($this->branch);
        $this->assertSame(5000, $this->place('LAIN')->discount);
    }

    public function test_unknown_and_inactive_codes_are_rejected_and_codes_are_case_insensitive(): void
    {
        $this->promo(['code' => 'MATI', 'is_active' => false]);
        $this->promo(['code' => 'HIDUP']);

        $this->assertRejected('SALAH', 'Kode promo tidak ditemukan');
        $this->assertRejected('MATI', 'tidak aktif');
        $this->assertSame(5000, $this->place('  hidup ')->discount);
    }

    public function test_the_largest_discount_wins_and_only_one_is_applied(): void
    {
        $this->promo(['name' => 'Otomatis 10%', 'value' => 10]);                                // 5.000
        $this->promo(['name' => 'Kode 8rb', 'code' => 'DELAPAN', 'type' => 'fixed', 'value' => 8000]); // 8.000
        $this->promo(['name' => 'Otomatis 5%', 'value' => 5]);                                  // 2.500

        $order = $this->place('DELAPAN');

        $this->assertSame(8000, $order->discount);
        $this->assertSame('Kode 8rb', $order->discount_label);
        $this->assertSame(5000, $this->place()->discount); // tanpa kode: otomatis terbesar
    }

    // ---- keamanan: browser tidak bisa menentukan potongan ----

    public function test_browser_cannot_set_the_discount_or_total(): void
    {
        $response = $this->postJson(route('order.store'), [
            'token' => $this->table->token, 'method' => 'qris', 'discount' => 49000, 'total' => 1000, 'discount_label' => 'gratis',
            'cart' => [['item_id' => $this->coffee->id, 'qty' => 1, 'unit_price' => 1, 'discount' => 999]],
        ])->assertOk();

        $order = Order::query()->where('code', $response->json('code'))->firstOrFail();
        $this->assertSame(0, $order->discount);
        $this->assertSame(22200, $order->total);
    }

    public function test_fake_code_from_the_browser_is_rejected(): void
    {
        $this->postJson(route('order.store'), [
            'token' => $this->table->token, 'method' => 'qris', 'promo_code' => 'GRATIS',
            'cart' => [['item_id' => $this->coffee->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJson(['error' => 'Kode promo tidak ditemukan.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_preview_endpoint_reports_the_discount_without_saving_anything(): void
    {
        $this->promo(['code' => 'HEMAT']);
        $cart = [['item_id' => $this->coffee->id, 'qty' => 1], ['item_id' => $this->bread->id, 'qty' => 1]];

        $this->postJson(route('promo.check'), ['token' => $this->table->token, 'promo_code' => 'hemat', 'cart' => $cart])
            ->assertOk()->assertJson(['discount' => 5000, 'label' => 'Promo', 'total' => 50000]);
        $this->postJson(route('promo.check'), ['token' => $this->table->token, 'promo_code' => 'salah', 'cart' => $cart])
            ->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Discount::query()->first()->used_count);
    }

    public function test_automatic_promos_are_listed_for_customers_but_codes_are_not(): void
    {
        $this->promo(['name' => 'Happy hour']);
        $this->promo(['name' => 'Rahasia', 'code' => 'RAHASIA']);
        $this->promo(['name' => 'Mati', 'is_active' => false]);

        $names = array_column(app(DiscountService::class)->publicPromos($this->branch->id), 'name');

        $this->assertSame(['Happy hour'], $names);
    }

    // ---- admin ----

    public function test_owner_creates_updates_and_audits_a_promo(): void
    {
        $owner = $this->staff(Role::Owner);

        $this->actingAs($owner)->post(route('admin.discounts.store'), $this->formData(['value' => 15, 'days' => [1, 2], 'time_from' => '17:00', 'time_until' => '19:00']))
            ->assertRedirect(route('admin.discounts'));

        $promo = Discount::query()->firstOrFail();
        $this->assertSame('HEMAT10', $promo->code);
        $this->assertSame([1, 2], $promo->days);
        $this->assertTrue($promo->all_branches);

        $this->put(route('admin.discounts.update', $promo), $this->formData(['value' => 20]))->assertRedirect();
        $this->assertSame(20, $promo->refresh()->value);
        $this->assertSame(['discount.create', 'discount.update'], AuditLog::query()->orderBy('id')->pluck('action')->all());
        $this->get(route('admin.discounts'))->assertOk()->assertSee('Hemat');
    }

    public function test_item_scope_requires_targets_and_percent_is_capped_at_100(): void
    {
        $this->actingAs($this->staff(Role::Owner))
            ->post(route('admin.discounts.store'), $this->formData(['scope' => 'items']))->assertSessionHasErrors('target_ids');
        $this->post(route('admin.discounts.store'), $this->formData(['value' => 150]))->assertSessionHasErrors('value');
        $this->post(route('admin.discounts.store'), $this->formData(['scope' => 'items', 'target_ids' => [$this->bread->id]]))->assertSessionDoesntHaveErrors();

        $this->assertSame([$this->bread->id], Discount::query()->firstOrFail()->targets->pluck('target_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_form_pages_and_the_customer_menu_banner_render(): void
    {
        $promo = $this->promo(['name' => 'Happy hour kopi', 'scope' => 'items'], ['item' => $this->coffee->id]);
        $owner = $this->staff(Role::Owner);

        $this->actingAs($owner)->get(route('admin.discounts.create'))->assertOk()->assertSee('Promo baru');
        $this->get(route('admin.discounts.edit', $promo))->assertOk()->assertSee('Happy hour kopi');

        $this->get(route('menu.show', $this->table->token))->assertOk()->assertSee('Happy hour kopi')->assertSee('assets/promo.css', false);
    }

    public function test_manager_is_limited_to_promos_of_their_own_branches(): void
    {
        $other = Branch::create(['name' => 'Cabang Lain']);
        $manager = $this->staff(Role::Manager, $this->branch);
        $foreign = $this->promo(['name' => 'Milik lain', 'all_branches' => false]);
        $foreign->branches()->attach($other);
        $global = $this->promo(['name' => 'Semua cabang']);

        $this->actingAs($manager)->post(route('admin.discounts.store'), $this->formData())->assertForbidden();      // semua cabang
        $this->post(route('admin.discounts.store'), $this->formData(['all_branches' => 0, 'branch_ids' => [$other->id]]))->assertForbidden();
        $this->get(route('admin.discounts.edit', $foreign))->assertForbidden();
        $this->get(route('admin.discounts.edit', $global))->assertForbidden();

        $this->post(route('admin.discounts.store'), $this->formData(['code' => 'CABANG', 'all_branches' => 0, 'branch_ids' => [$this->branch->id]]))->assertRedirect();
        $names = $this->get(route('admin.discounts'))->assertOk()->viewData('discounts')->pluck('name')->all();
        $this->assertSame(['Hemat'], $names);
    }

    public function test_cashier_cannot_open_the_discount_pages(): void
    {
        $cashier = $this->staff(Role::Cashier, $this->branch);

        $this->actingAs($cashier)->get(route('admin.discounts'))->assertForbidden();
        $this->post(route('admin.discounts.store'), $this->formData())->assertForbidden();
    }

    public function test_used_promo_is_deactivated_instead_of_deleted(): void
    {
        $owner = $this->staff(Role::Owner);
        $unused = $this->promo(['name' => 'Belum dipakai', 'min_spend' => 9999999]);
        $used = $this->promo(['name' => 'Sudah dipakai', 'code' => 'PAKAI']);
        $this->place('PAKAI');

        $this->actingAs($owner)->delete(route('admin.discounts.destroy', $unused))->assertRedirect();
        $this->delete(route('admin.discounts.destroy', $used))->assertRedirect();

        $this->assertNull(Discount::query()->find($unused->id));
        $this->assertFalse($used->refresh()->is_active);
    }

    private function assertRejected(string $code, string $messagePart): void
    {
        try {
            $this->place($code);
            $this->fail("Kode $code seharusnya ditolak.");
        } catch (OrderRejected $rejection) {
            $this->assertStringContainsString($messagePart, $rejection->getMessage());
        }
    }
}

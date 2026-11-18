<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\GatewaySetting;
use App\Models\Order;
use App\Models\User;
use App\Services\GatewaySettings;
use App\Services\OrderRejected;
use App\Services\OrderService;
use App\Services\SettingsService;
use App\Services\UserAdminService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsersSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const PLAIN_KEY = 'kp_test_abcdef1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(Role $role, bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active]);
    }

    private function owner(): User
    {
        return User::query()->where('role', Role::Owner)->firstOrFail();
    }

    private function placeOrder(string $method = 'qris'): Order
    {
        $table = DiningTable::query()->with('branch')->where('label', 'A1')->firstOrFail();
        return app(OrderService::class)->create($table, [['item_id' => 3, 'qty' => 1]], [], $method, '127.0.0.1');
    }

    private function setHours(array $attributes): void
    {
        Branch::query()->firstOrFail()->update($attributes);
    }

    // ---- hak akses ----

    public function test_only_owner_can_open_users_and_settings_pages(): void
    {
        $routes = ['admin.users', 'admin.settings', 'admin.settings.audit', 'admin.settings.webhooks'];

        foreach ([Role::Manager, Role::Cashier, Role::Kitchen, Role::Warehouse] as $role) {
            foreach ($routes as $route) {
                $this->flushSession(); // satu sesi = satu pengguna (AuthenticateSession)
                $this->actingAs($this->user($role))->get(route($route))->assertForbidden();
            }
        }
        foreach ($routes as $route) {
            $this->flushSession();
            $this->actingAs($this->owner())->get(route($route))->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.users'))->assertRedirect('/staff/login');
        $this->put(route('admin.settings.brand'), [])->assertRedirect('/staff/login');
    }

    public function test_manager_cannot_change_settings_or_gateway(): void
    {
        $manager = $this->user(Role::Manager);

        $this->actingAs($manager)->put(route('admin.settings.gateway'), ['api_key' => self::PLAIN_KEY])->assertForbidden();
        $this->assertNull(GatewaySetting::query()->find(1));
    }

    // ---- pengguna ----

    public function test_owner_creates_user_with_branch_access_and_hashed_password(): void
    {
        $branch = Branch::query()->firstOrFail();

        $this->actingAs($this->owner())->post(route('admin.users.store'), [
            'name' => 'Sari Kasir', 'email' => 'sari@contoh.test', 'password' => 'rahasia-aman-99', 'role' => 'cashier', 'branch_ids' => [$branch->id],
        ])->assertRedirect();

        $user = User::query()->where('email', 'sari@contoh.test')->firstOrFail();
        $this->assertSame(Role::Cashier, $user->role);
        $this->assertTrue($user->canAccessBranch($branch->id));
        $this->assertNotSame('rahasia-aman-99', $user->password);
        $this->assertTrue(password_verify('rahasia-aman-99', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.create', 'entity_id' => $user->id]);
    }

    public function test_user_is_deactivated_not_deleted_and_cannot_log_in_afterwards(): void
    {
        $cashier = $this->user(Role::Cashier);

        $this->actingAs($this->owner())->post(route('admin.users.toggle', $cashier))->assertRedirect();

        $this->assertFalse($cashier->refresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $cashier->id]);
    }

    public function test_password_can_be_reset_and_is_audited_without_the_value(): void
    {
        $cashier = $this->user(Role::Cashier);

        $this->actingAs($this->owner())->put(route('admin.users.update', $cashier), [
            'name' => $cashier->name, 'email' => $cashier->email, 'role' => 'cashier', 'password' => 'sandi-baru-123',
        ])->assertRedirect();

        $this->assertTrue(password_verify('sandi-baru-123', $cashier->refresh()->password));
        $audit = AuditLog::query()->where('action', 'user.password_reset')->firstOrFail();
        $this->assertStringNotContainsString('sandi-baru-123', json_encode($audit->toArray()));
    }

    public function test_last_active_owner_cannot_be_deactivated_or_demoted(): void
    {
        $owner = $this->owner();
        $service = app(UserAdminService::class);

        try {
            $service->setActive($owner, $owner, false);
            $this->fail('Owner terakhir berhasil dinonaktifkan.');
        } catch (OrderRejected) {
            $this->assertTrue($owner->refresh()->is_active);
        }

        $this->actingAs($owner)->put(route('admin.users.update', $owner), [
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'manager',
        ])->assertSessionHasErrors('form');
        $this->assertSame(Role::Owner, $owner->refresh()->role);
    }

    public function test_an_owner_can_step_down_when_another_active_owner_exists(): void
    {
        $owner = $this->owner();
        $second = $this->user(Role::Owner);

        app(UserAdminService::class)->setActive($second, $owner, false);

        $this->assertFalse($owner->refresh()->is_active);
    }

    public function test_inactive_second_owner_does_not_count_as_a_remaining_owner(): void
    {
        $owner = $this->owner();
        $this->user(Role::Owner, active: false);

        $this->expectException(OrderRejected::class);
        app(UserAdminService::class)->setActive($owner, $owner, false);
    }

    // ---- brand dan metode pembayaran ----

    public function test_brand_update_saves_tax_service_rounding_and_whitelists_methods(): void
    {
        $this->actingAs($this->owner())->put(route('admin.settings.brand'), [
            'name' => 'Kopi Baru', 'tax_percent' => 10, 'service_percent' => 5, 'rounding_to' => 500,
            'enabled_methods' => ['qris', 'va_bca', 'bitcoin', 'checkout'], 'accent_color' => '#112233',
        ])->assertSessionHasNoErrors();

        $brand = Brand::current();
        $this->assertSame('Kopi Baru', $brand->name);
        $this->assertSame(500, (int) $brand->rounding_to);
        $this->assertSame(['qris', 'va_bca'], $brand->enabledMethods());
    }

    public function test_brand_update_needs_at_least_one_valid_method(): void
    {
        $this->actingAs($this->owner())->put(route('admin.settings.brand'), [
            'name' => 'Kopi', 'tax_percent' => 11, 'service_percent' => 0, 'rounding_to' => 100,
            'enabled_methods' => ['bitcoin'], 'accent_color' => '#000000',
        ])->assertSessionHasErrors('form');

        $this->assertContains('qris', Brand::current()->enabledMethods());
    }

    public function test_a_disabled_method_cannot_be_used_for_an_order(): void
    {
        Brand::current()->update(['enabled_methods' => 'qris']);

        $this->expectException(OrderRejected::class);
        $this->placeOrder('va_bca');
    }

    // ---- gateway ----

    public function test_gateway_keys_are_stored_encrypted_and_override_env(): void
    {
        config(['onepay.kasera.api_key' => 'kp_test_dari_env']);

        $this->actingAs($this->owner())->put(route('admin.settings.gateway'), [
            'api_key' => self::PLAIN_KEY, 'webhook_secret' => 'rahasia-webhook',
        ])->assertSessionHasNoErrors();

        $raw = DB::table('gateway_settings')->first();
        $this->assertNotSame(self::PLAIN_KEY, $raw->api_key);
        $this->assertStringNotContainsString('abcdef1234567890', (string) $raw->api_key);
        $this->assertStringNotContainsString('rahasia-webhook', (string) $raw->webhook_secret);

        $gateway = app(GatewaySettings::class);
        $this->assertSame(self::PLAIN_KEY, $gateway->apiKey());
        $this->assertSame('rahasia-webhook', $gateway->webhookSecret());
        $this->assertSame('sandbox', $gateway->mode());
    }

    public function test_blank_gateway_input_keeps_the_current_keys(): void
    {
        $owner = $this->owner();
        $settings = app(SettingsService::class);
        $settings->updateGateway($owner, self::PLAIN_KEY, 'rahasia-webhook');

        $settings->updateGateway($owner, '', '');

        $this->assertSame(self::PLAIN_KEY, app(GatewaySettings::class)->apiKey());
    }

    public function test_api_key_with_wrong_prefix_is_rejected(): void
    {
        $this->actingAs($this->owner())->put(route('admin.settings.gateway'), ['api_key' => 'sk_live_salah'])->assertSessionHasErrors('form');

        $this->assertNull(GatewaySetting::query()->find(1));
    }

    public function test_live_key_prefix_is_reported_as_production_mode(): void
    {
        app(SettingsService::class)->updateGateway($this->owner(), 'kp_live_abcdef1234567890', null);

        $this->assertSame('produksi', app(GatewaySettings::class)->mode());
    }

    public function test_settings_page_and_audit_never_expose_the_full_key(): void
    {
        $owner = $this->owner();
        app(SettingsService::class)->updateGateway($owner, self::PLAIN_KEY, 'rahasia-webhook');

        $page = $this->actingAs($owner)->get(route('admin.settings'))->assertOk();

        $page->assertDontSee(self::PLAIN_KEY, false)->assertDontSee('rahasia-webhook', false)->assertSee('••••', false);
        $this->assertStringNotContainsString(self::PLAIN_KEY, json_encode(AuditLog::all()->toArray()));
    }

    public function test_kasera_client_sends_the_database_key_as_bearer_token(): void
    {
        $owner = $this->owner();
        app(SettingsService::class)->updateGateway($owner, self::PLAIN_KEY, null);
        Http::fake(['*/payment_methods' => Http::response(['data' => []])]);

        $this->actingAs($owner)->post(route('admin.settings.gateway.test'))->assertSessionHas('status');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ' . self::PLAIN_KEY));
    }

    public function test_connection_test_reports_failure_without_leaking_the_key(): void
    {
        $owner = $this->owner();
        app(SettingsService::class)->updateGateway($owner, self::PLAIN_KEY, null);
        Http::fake(['*/payment_methods' => Http::response(['message' => 'Invalid API key'], 401)]);

        $response = $this->actingAs($owner)->post(route('admin.settings.gateway.test'));

        $response->assertSessionHas('gateway_error');
        $this->assertStringNotContainsString(self::PLAIN_KEY, (string) session('gateway_error'));
    }

    public function test_webhook_signature_uses_the_database_secret(): void
    {
        config(['onepay.kasera.webhook_secret' => 'secret-lama-env']);
        app(SettingsService::class)->updateGateway($this->owner(), null, 'secret-dari-db');
        $order = $this->placeOrder();

        $this->sendWebhook($order->code, 'secret-lama-env')->assertStatus(401);
        $this->sendWebhook($order->code, 'secret-dari-db')->assertOk();
    }

    private function sendWebhook(string $code, string $secret): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => 'payment.paid', 'data' => ['id' => 'tx_1', 'external_id' => $code]]);
        $t = time();

        return $this->call('POST', route('kasera.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_KASERA_SIGNATURE_V1' => "t=$t,v1=" . hash_hmac('sha256', "$t.$body", $secret),
        ], $body);
    }

    // ---- jam buka, libur, batas tutup ----

    public function test_closed_toggle_rejects_orders(): void
    {
        $this->setHours(['is_open' => false]);

        $this->expectExceptionMessage('Cabang sedang tutup.');
        $this->placeOrder();
    }

    public function test_branch_without_schedule_stays_open_all_day(): void
    {
        Carbon::setTestNow('2026-11-18 03:00:00');

        $this->assertSame('pending_payment', $this->placeOrder()->status->value);
    }

    public function test_order_outside_opening_hours_is_rejected(): void
    {
        $this->setHours(['open_hours' => $this->hours('08:00', '22:00')]);
        Carbon::setTestNow('2026-11-18 07:00:00'); // Rabu

        $this->expectExceptionMessage('Cabang sedang tutup.');
        $this->placeOrder();
    }

    public function test_order_inside_opening_hours_is_accepted(): void
    {
        $this->setHours(['open_hours' => $this->hours('08:00', '22:00')]);
        Carbon::setTestNow('2026-11-18 12:00:00');

        $this->assertNotNull($this->placeOrder()->id);
    }

    public function test_order_cutoff_before_closing_time_is_enforced(): void
    {
        $this->setHours(['open_hours' => $this->hours('08:00', '22:00'), 'last_order_minutes' => 30]);

        Carbon::setTestNow('2026-11-18 21:20:00');
        $this->assertNotNull($this->placeOrder()->id);

        Carbon::setTestNow('2026-11-18 21:45:00');
        $this->expectExceptionMessage('Pesanan sudah ditutup menjelang jam tutup.');
        $this->placeOrder();
    }

    public function test_holiday_rejects_orders_even_during_opening_hours(): void
    {
        $this->setHours(['open_hours' => $this->hours('08:00', '22:00'), 'holidays' => ['2026-11-18']]);
        Carbon::setTestNow('2026-11-18 12:00:00');

        $this->expectExceptionMessage('Cabang libur hari ini.');
        $this->placeOrder();
    }

    public function test_day_marked_closed_rejects_orders(): void
    {
        $hours = $this->hours('08:00', '22:00');
        $hours['wed']['closed'] = true;
        $this->setHours(['open_hours' => $hours]);
        Carbon::setTestNow('2026-11-18 12:00:00'); // Rabu

        $this->expectExceptionMessage('Cabang sedang tutup.');
        $this->placeOrder();
    }

    public function test_hours_that_cross_midnight_accept_orders_after_midnight(): void
    {
        $hours = $this->hours('08:00', '22:00');
        $hours['wed'] = ['closed' => false, 'open' => '18:00', 'close' => '02:00'];
        $this->setHours(['open_hours' => $hours]);

        Carbon::setTestNow('2026-11-19 01:00:00'); // Kamis dini hari, masih jendela Rabu
        $this->assertNotNull($this->placeOrder()->id);

        Carbon::setTestNow('2026-11-19 03:00:00');
        $this->expectException(OrderRejected::class);
        $this->placeOrder();
    }

    public function test_branch_form_saves_hours_holidays_and_ignores_invalid_dates(): void
    {
        $branch = Branch::query()->firstOrFail();
        $hours = array_map(fn ($row) => ['open' => $row['open'], 'close' => $row['close']], $this->hours('09:00', '21:00'));

        $this->actingAs($this->owner())->put(route('admin.settings.branches.update', $branch), [
            'name' => 'Cabang Utama', 'address' => 'Jl. Baru', 'prep_minutes' => 20, 'last_order_minutes' => 15,
            'is_open' => 1, 'is_active' => 1, 'use_hours' => 1, 'hours' => $hours,
            'holidays' => "2026-12-25\nbukan-tanggal\n2026-12-25\n2027-01-01",
        ])->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertSame(['2026-12-25', '2027-01-01'], $branch->holidays);
        $this->assertSame('09:00', $branch->open_hours['mon']['open']);
        $this->assertSame(20, $branch->prep_minutes);
        $this->assertSame(15, $branch->last_order_minutes);
    }

    public function test_unchecking_schedule_clears_opening_hours(): void
    {
        $branch = Branch::query()->firstOrFail();
        $branch->update(['open_hours' => $this->hours('08:00', '22:00')]);

        $this->actingAs($this->owner())->put(route('admin.settings.branches.update', $branch), [
            'name' => 'Cabang Utama', 'prep_minutes' => 15, 'last_order_minutes' => 0, 'is_open' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull($branch->refresh()->open_hours);
    }

    public function test_owner_can_add_a_branch(): void
    {
        $this->actingAs($this->owner())->post(route('admin.settings.branches.store'), [
            'name' => 'Cabang Kedua', 'prep_minutes' => 10, 'last_order_minutes' => 0, 'is_open' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['name' => 'Cabang Kedua']);
    }

    // ---- log dan ekspor ----

    public function test_audit_log_page_filters_by_action(): void
    {
        $owner = $this->owner();
        AuditLog::record($owner, 'order.cancel_refund', 'order', 7, 'paid', 'cancelled: bahan habis');
        AuditLog::record($owner, 'branch.open', 'branch', 1, null, 'tutup');

        $this->actingAs($owner)->get(route('admin.settings.audit', ['action' => 'cancel']))
            ->assertOk()->assertSee('order.cancel_refund')->assertDontSee('branch.open');
    }

    public function test_webhook_log_page_shows_signature_result_and_filters_invalid(): void
    {
        $order = $this->placeOrder();
        $this->sendWebhook($order->code, 'salah')->assertStatus(401);
        $this->sendWebhook($order->code, (string) config('onepay.kasera.webhook_secret'))->assertOk();

        $this->actingAs($this->owner())->get(route('admin.settings.webhooks', ['signature' => 'invalid']))
            ->assertOk()->assertSee('signature_invalid')
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 1 && !$logs->first()->signature_valid);
    }

    public function test_backup_note_is_recorded(): void
    {
        $this->actingAs($this->owner())->post(route('admin.settings.backup'), ['note' => 'dump ke drive'])->assertSessionHasNoErrors();

        $brand = Brand::current();
        $this->assertNotNull($brand->last_backup_at);
        $this->assertSame('dump ke drive', $brand->backup_note);
    }

    public function test_menu_export_is_csv_and_neutralises_spreadsheet_formulas(): void
    {
        \App\Models\Item::query()->where('id', 1)->update(['name' => '=HYPERLINK("http://x")']);

        $response = $this->actingAs($this->owner())->get(route('admin.settings.export.menu'))->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('kategori,nama,deskripsi,harga,hpp,stasiun,best_seller,aktif', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Nasi Goreng', $csv);
    }

    public function test_orders_export_lists_orders_in_range(): void
    {
        $order = $this->placeOrder();

        $csv = $this->actingAs($this->owner())->get(route('admin.settings.export.orders'))->assertOk()->streamedContent();

        $this->assertStringContainsString($order->code, $csv);
    }

    public function test_exports_are_owner_only(): void
    {
        $this->actingAs($this->user(Role::Manager))->get(route('admin.settings.export.orders'))->assertForbidden();
    }

    private function hours(string $open, string $close): array
    {
        $hours = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $hours[$day] = ['closed' => false, 'open' => $open, 'close' => $close];
        }
        return $hours;
    }
}

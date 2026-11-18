<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Models\User;
use App\Services\GatewaySettings;
use App\Services\Kasera\KaseraClient;
use App\Services\Kasera\KaseraException;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Ketahanan integrasi Kasera: retry, aturan Rp 1.000.000, idempotensi, dan pemeriksaan webhook. Semua memakai Http::fake. */
class KaseraHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'onepay.kasera.webhook_secret' => self::SECRET,
            'onepay.kasera.api_key' => 'kp_test_x',
            'onepay.kasera.delivery_terms' => true,
            'onepay.kasera.retry_backoff_ms' => [0, 0],
        ]);
        $this->seed(DatabaseSeeder::class);
    }

    private function placeOrder(string $method = 'qris', int $qty = 1): Order
    {
        $table = DiningTable::query()->where('label', 'A1')->firstOrFail();
        return app(OrderService::class)->create($table, [['item_id' => 3, 'qty' => $qty]], ['name' => 'Budi'], $method, '127.0.0.1');
    }

    private function invoice(string $id = 'pr_1', array $extra = []): array
    {
        return $extra + [
            'id' => $id,
            'status' => 'pending',
            'payment_method' => 'qris',
            'payment' => ['qr_string' => '00020101021226FAKE', 'payment_code' => '8808123456789012'],
            'instructions' => ['title' => 'Cara bayar', 'steps' => ['Buka aplikasi', 'Bayar']],
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
    }

    private function signedWebhook(string $event, Order $order, array $data = []): TestResponse
    {
        $body = json_encode(['event' => $event, 'data' => ['external_id' => $order->code] + $data]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "$timestamp.$body", self::SECRET);

        return $this->call('POST', route('kasera.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_KASERA_SIGNATURE_V1' => "t=$timestamp,v1=$signature",
        ], $body);
    }

    /* ---------- retry dan ketahanan ---------- */

    public function test_server_errors_are_retried_until_the_gateway_recovers(): void
    {
        Http::fake(['*/transactions' => Http::sequence()->push(['message' => 'down'], 500)->push(['message' => 'down'], 502)->push($this->invoice(), 201)]);

        $response = app(KaseraClient::class)->createTransaction(['amount' => 50000], 'order-X');

        $this->assertSame('pr_1', $response['id']);
        Http::assertSentCount(3);
    }

    public function test_connection_failures_are_retried(): void
    {
        Http::fake(['*/transactions' => Http::sequence()->pushFailedConnection()->push($this->invoice(), 201)]);

        $this->assertSame('pr_1', app(KaseraClient::class)->createTransaction(['amount' => 50000], 'order-X')['id']);
        Http::assertSentCount(2);
    }

    public function test_client_errors_are_never_retried(): void
    {
        Http::fake(['*/transactions' => Http::response(['error' => ['code' => 'delivery_date_required', 'message' => 'x']], 422)]);

        try {
            app(KaseraClient::class)->createTransaction(['amount' => 50000], 'order-X');
            $this->fail('422 harus dilempar sebagai KaseraException.');
        } catch (KaseraException $error) {
            $this->assertSame(422, $error->httpStatus);
            $this->assertSame('delivery_date_required', $error->errorCode());
        }
        Http::assertSentCount(1);
    }

    public function test_retries_are_limited_and_the_last_error_surfaces(): void
    {
        Http::fake(['*/transactions' => Http::response(['message' => 'down'], 503)]);

        try {
            app(KaseraClient::class)->createTransaction(['amount' => 50000], 'order-X');
            $this->fail('503 yang menetap harus dilempar.');
        } catch (KaseraException $error) {
            $this->assertSame(503, $error->httpStatus);
        }
        Http::assertSentCount(3); // percobaan pertama + 2 retry
    }

    public function test_request_log_never_contains_the_api_key_or_secret(): void
    {
        $logged = [];
        \Illuminate\Support\Facades\Log::listen(function ($event) use (&$logged) {
            $logged[] = $event->message . json_encode($event->context);
        });
        Http::fake(['*/transactions' => Http::response($this->invoice(), 201)]);

        app(KaseraClient::class)->createTransaction(['amount' => 50000], 'order-X');

        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString('kp_test_x', implode('|', $logged));
        $this->assertStringNotContainsString(self::SECRET, implode('|', $logged));
    }

    /* ---------- idempotensi dan isi permintaan ---------- */

    public function test_conflict_with_a_known_invoice_reads_the_existing_one(): void
    {
        Http::fake([
            '*/transactions/pr_exist' => Http::response($this->invoice('pr_exist'), 200),
            '*/transactions' => Http::response(['error' => ['code' => 'idempotency_key_reused', 'existing_id' => 'pr_exist', 'message' => 'x']], 409),
        ]);
        $order = $this->placeOrder();

        $payment = app(PaymentService::class)->ensureGatewayPayment($order);

        $this->assertSame('pr_exist', $payment->provider_ref);
        $this->assertNotEmpty($payment->details['payment']['qr_string']);
    }

    public function test_conflict_without_an_invoice_id_surfaces_the_error(): void
    {
        Http::fake(['*/transactions' => Http::response(['error' => ['code' => 'idempotency_key_reused', 'message' => 'x']], 409)]);

        $this->expectException(KaseraException::class);
        app(PaymentService::class)->ensureGatewayPayment($this->placeOrder());
    }

    public function test_a_retry_minutes_later_sends_the_identical_request(): void
    {
        $bodies = [];
        $keys = [];
        $calls = 0;
        Http::fake(function (Request $request) use (&$bodies, &$keys, &$calls) {
            $bodies[] = $request->body();
            $keys[] = $request->header('Idempotency-Key')[0] ?? null;
            return ++$calls <= 3 ? Http::response(['message' => 'down'], 500) : Http::response($this->invoice(), 201);
        });
        $order = $this->placeOrder();

        try {
            app(PaymentService::class)->ensureGatewayPayment($order);
            $this->fail('Percobaan pertama harus gagal.');
        } catch (KaseraException) {
            // tagihan belum terbentuk
        }
        $this->travel(7)->minutes();
        config(['onepay.order_expiry_minutes' => 99]); // konfigurasi berubah: isi permintaan tidak boleh ikut berubah
        app(PaymentService::class)->ensureGatewayPayment($order->refresh());

        $this->assertCount(4, $bodies);
        $this->assertCount(1, array_unique($bodies));
        $this->assertSame(['order-' . $order->code], array_unique($keys));
        $this->assertSame(15, json_decode($bodies[0], true)['expires_in_minutes']);
    }

    /* ---------- aturan di atas Rp 1.000.000 ---------- */

    public function test_delivery_terms_are_sent_above_one_million(): void
    {
        Http::fake(['*/transactions' => Http::response($this->invoice(), 201)]);
        $order = $this->placeOrder('qris', 50);

        app(PaymentService::class)->ensureGatewayPayment($order);

        $this->assertGreaterThan(1_000_000, $order->total);
        Http::assertSent(fn (Request $request) => $request['delivery_terms_shown'] === true
            && $request['delivery_date'] === $order->created_at->copy()->timezone('Asia/Jakarta')->toDateString());
    }

    public function test_delivery_terms_are_not_sent_at_or_below_the_threshold_or_when_disabled(): void
    {
        Http::fake(['*/transactions' => Http::response($this->invoice(), 201)]);

        app(PaymentService::class)->ensureGatewayPayment($this->placeOrder('qris', 1));
        config(['onepay.kasera.delivery_terms' => false]);
        app(PaymentService::class)->ensureGatewayPayment($this->placeOrder('qris', 50));

        Http::assertSent(fn (Request $request) => !isset($request['delivery_date']) && !isset($request['delivery_terms_shown']));
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => isset($request['delivery_date']));
    }

    public function test_pay_page_and_cart_show_the_terms_only_above_the_threshold(): void
    {
        Http::fake(['*/transactions' => Http::response($this->invoice(), 201)]);
        $big = $this->placeOrder('qris', 50);
        $small = $this->placeOrder('qris', 1);

        $this->get(route('pay.show', $big))->assertOk()->assertSee('Ketentuan layanan');
        $this->get(route('pay.show', $small))->assertOk()->assertDontSee('Ketentuan layanan');

        $token = DiningTable::query()->where('label', 'A1')->value('token');
        $this->get(route('menu.show', $token))->assertOk()->assertSee('"threshold":1000000', false);
        config(['onepay.kasera.delivery_terms' => false]);
        $this->get(route('menu.show', $token))->assertOk()->assertDontSee('"threshold"', false);
    }

    /* ---------- halaman bayar ---------- */

    public function test_qris_page_offers_download_and_copy_of_the_qr(): void
    {
        Http::fake(['*/transactions' => Http::response($this->invoice(), 201)]);
        $order = $this->placeOrder('qris');

        $this->get(route('pay.show', $order))->assertOk()
            ->assertSee('id="qrDownload"', false)
            ->assertSee('Salin teks QR')
            ->assertSee('data-copy="00020101021226FAKE"', false)
            ->assertSee('id="expiredNotice"', false);
    }

    public function test_bank_logo_is_shown_only_when_the_file_exists(): void
    {
        Http::fake(['*/transactions' => Http::response($this->invoice('pr_va', ['payment_method' => 'va_bca']), 201)]);
        $order = $this->placeOrder('va_bca');
        $logo = public_path('assets/banks/va_bca.svg');

        $this->get(route('pay.show', $order))->assertOk()->assertDontSee('bank-logo"', false)->assertSee('BCA Virtual Account');

        @mkdir(dirname($logo));
        file_put_contents($logo, '<svg xmlns="http://www.w3.org/2000/svg"/>');
        try {
            $this->get(route('pay.show', $order))->assertOk()->assertSee('class="bank-logo"', false)->assertSee('BCA Virtual Account');
        } finally {
            @unlink($logo);
            @rmdir(dirname($logo));
        }
    }

    public function test_pay_page_retry_button_exists_when_the_gateway_is_down(): void
    {
        Http::fake(['*/transactions' => Http::response(['message' => 'down'], 503)]);
        $order = $this->placeOrder();

        $this->get(route('pay.show', $order))->assertOk()->assertSee('Tagihan belum bisa dibuat')->assertSee('data-reload', false);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    /* ---------- webhook: nominal dan id tagihan ---------- */

    public function test_matching_amount_and_ref_mark_the_order_paid(): void
    {
        $order = $this->placeOrder();
        $order->payment->update(['provider_ref' => 'pr_1']);

        $this->signedWebhook('payment.paid', $order, ['id' => 'pr_1', 'amount' => $order->total])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_a_different_amount_is_flagged_and_not_paid(): void
    {
        $order = $this->placeOrder();

        $this->signedWebhook('payment.paid', $order, ['id' => 'pr_1', 'amount' => $order->total - 1000])->assertOk();
        $this->signedWebhook('payment.paid', $order, ['id' => 'pr_1', 'amount' => $order->total - 1000])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertTrue($order->needs_review);
        $this->assertSame(1, $order->statusLogs()->where('reason', 'like', 'Nominal pembayaran%')->count());
        $this->assertSame(['amount_mismatch', 'duplicate'], PaymentWebhook::query()->orderBy('id')->pluck('result')->all());
    }

    public function test_a_webhook_for_another_invoice_is_ignored_for_paid_and_for_expiry(): void
    {
        $order = $this->placeOrder();
        $order->payment->update(['provider_ref' => 'pr_real']);

        $this->signedWebhook('payment.paid', $order, ['id' => 'pr_other'])->assertOk();
        $this->signedWebhook('payment.expired', $order, ['id' => 'pr_other'])->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(['ref_mismatch', 'ref_mismatch'], PaymentWebhook::query()->orderBy('id')->pluck('result')->all());
    }

    public function test_sync_with_a_different_amount_is_flagged_and_not_paid(): void
    {
        $order = $this->placeOrder();
        $order->payment->update(['provider_ref' => 'pr_1']);
        Http::fake(['*/transactions/pr_1' => Http::response(['id' => 'pr_1', 'status' => 'succeeded', 'amount' => 1000])]);

        app(PaymentService::class)->syncWithGateway($order);

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertTrue($order->needs_review);
    }

    /* ---------- mode kunci ---------- */

    public function test_mode_warning_matches_key_and_environment(): void
    {
        $gateway = fn () => (new GatewaySettings())->modeWarning();

        $this->app['env'] = 'production';
        config(['onepay.kasera.api_key' => 'kp_test_abc']);
        $this->assertStringContainsString('sandbox', (string) $gateway());
        config(['onepay.kasera.api_key' => 'kp_live_abc']);
        $this->assertNull($gateway());

        $this->app['env'] = 'local';
        $this->assertStringContainsString('uang sungguhan', (string) $gateway());
        config(['onepay.kasera.api_key' => 'kp_test_abc']);
        $this->assertNull($gateway());
    }

    public function test_settings_page_shows_the_mode_warning_in_production_with_a_sandbox_key(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner, 'is_active' => true]);
        $this->app['env'] = 'production';
        config(['onepay.kasera.api_key' => 'kp_test_abc']);

        $this->actingAs($owner)->get(route('admin.settings'))->assertOk()->assertSee('masih memakai kunci sandbox');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Services\OrderRejected;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['onepay.kasera.webhook_secret' => self::SECRET, 'onepay.kasera.api_key' => 'kp_test_x']);
        $this->seed(DatabaseSeeder::class);
    }

    private function placeOrder(array $cart = null, string $method = 'qris'): Order
    {
        $cart ??= [['item_id' => 3, 'qty' => 1]];
        return app(OrderService::class)->create(DiningTable::query()->where('label', 'A1')->firstOrFail(), $cart, ['name' => 'Budi'], $method, '127.0.0.1');
    }

    private function signedWebhook(string $event, string $code, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => $event, 'data' => ['id' => 'tx_1', 'external_id' => $code]]);
        $t = time();
        $signature = hash_hmac('sha256', "$t.$body", $secret);

        return $this->call('POST', route('kasera.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_KASERA_SIGNATURE_V1' => "t=$t,v1=$signature",
        ], $body);
    }

    public function test_valid_webhook_marks_order_paid_and_sends_it_to_the_kitchen(): void
    {
        $order = $this->placeOrder();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        $this->signedWebhook('payment.paid', $order->code)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('tx_1', $order->payment->provider_ref);
    }

    public function test_the_same_webhook_twice_is_applied_once(): void
    {
        $order = $this->placeOrder();

        $this->signedWebhook('payment.paid', $order->code)->assertOk();
        $this->signedWebhook('payment.paid', $order->code)->assertOk();

        $this->assertSame(1, $order->statusLogs()->where('to_status', 'paid')->count());
        $this->assertSame(['applied', 'duplicate'], PaymentWebhook::query()->orderBy('id')->pluck('result')->all());
    }

    public function test_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        $order = $this->placeOrder();

        $this->signedWebhook('payment.paid', $order->code, 'wrong-secret')->assertStatus(401);

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_unknown_order_and_unknown_event_are_acknowledged(): void
    {
        $order = $this->placeOrder();

        $this->signedWebhook('payment.paid', 'TIDAKADA')->assertOk();
        $this->signedWebhook('payment.refunded', $order->code)->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_payment_after_expiry_is_flagged_for_review_and_never_reaches_the_kitchen(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);
        $payments->applyOutcome($order, PaymentOutcome::Failed, 'test');

        $this->signedWebhook('payment.paid', $order->code)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertTrue($order->needs_review);
    }

    public function test_sync_fallback_confirms_payment_when_gateway_reports_succeeded(): void
    {
        $order = $this->placeOrder();
        $order->payment->update(['provider_ref' => 'tx_9']);
        Http::fake(['*/transactions/tx_9' => Http::response(['id' => 'tx_9', 'status' => 'succeeded'])]);

        app(PaymentService::class)->syncWithGateway($order);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_expiry_job_expires_only_overdue_pending_orders(): void
    {
        $overdue = $this->placeOrder();
        $overdue->update(['expires_at' => now()->subMinute()]);
        $fresh = $this->placeOrder();

        $count = app(PaymentService::class)->expireStaleOrders();

        $this->assertSame(1, $count);
        $this->assertSame(OrderStatus::Expired, $overdue->refresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $fresh->refresh()->status);
    }

    public function test_prices_come_from_the_database_and_modifiers_are_added(): void
    {
        // Es Kopi Susu 22.000 + Besar 5.000, 2 gelas = 54.000; pajak 11% = 5.940; total 59.940 dibulatkan 59.900.
        $order = $this->placeOrder([['item_id' => 1, 'qty' => 2, 'modifier_ids' => [2], 'unit_price' => 1]]);

        $this->assertSame(54000, $order->subtotal);
        $this->assertSame(5940, $order->tax);
        $this->assertSame(59900, $order->total);
        $this->assertSame(27000, $order->items->first()->unit_price);
    }

    public function test_required_modifier_missing_is_rejected(): void
    {
        $this->expectException(OrderRejected::class);
        $this->placeOrder([['item_id' => 1, 'qty' => 1]]);
    }

    public function test_sold_out_item_cannot_be_ordered(): void
    {
        \DB::table('branch_items')->insert(['branch_id' => 1, 'item_id' => 3, 'is_available' => false]);

        $this->expectException(OrderRejected::class);
        $this->placeOrder([['item_id' => 3, 'qty' => 1]]);
    }

    public function test_total_outside_method_limits_is_rejected(): void
    {
        // Americano 18.000 lolos QRIS (min 1.000) tapi VA minimal 10.000; 1 gelas Rp 19.980 lolos. Uji batas bawah dengan harga override.
        \DB::table('branch_items')->insert(['branch_id' => 1, 'item_id' => 2, 'is_available' => true, 'price_override' => 500]);

        $this->expectException(OrderRejected::class);
        $this->placeOrder([['item_id' => 2, 'qty' => 1]], 'va_bca');
    }

    public function test_item_prices_are_snapshotted_on_the_order(): void
    {
        $order = $this->placeOrder();
        \DB::table('items')->where('id', 3)->update(['price' => 99999, 'name' => 'Diganti']);

        $line = $order->refresh()->items->first();
        $this->assertSame('Nasi Goreng', $line->name);
        $this->assertSame(32000, $line->unit_price);
    }
}

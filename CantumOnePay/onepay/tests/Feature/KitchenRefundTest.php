<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\StaffOrderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenRefundTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->branch = Branch::query()->firstOrFail();
    }

    /** Pesanan dengan satu item dapur (Nasi Goreng) dan satu item bar (Es Kopi Susu Besar). */
    private function placeOrder(string $label = 'A1'): Order
    {
        $table = DiningTable::query()->where('label', $label)->firstOrFail();
        $food = Item::query()->where('name', 'Nasi Goreng')->firstOrFail();
        $coffee = Item::query()->where('name', 'Es Kopi Susu')->firstOrFail();
        $large = $coffee->modifierGroups->first()->modifiers->firstWhere('name', 'Besar');

        return app(OrderService::class)->create($table, [
            ['item_id' => $food->id, 'qty' => 1],
            ['item_id' => $coffee->id, 'qty' => 1, 'modifier_ids' => [$large->id]],
        ], [], 'qris', '127.0.0.1');
    }

    private function paidOrder(string $label = 'A1'): Order
    {
        $order = $this->placeOrder($label);
        app(PaymentService::class)->applyOutcome($order, PaymentOutcome::Paid, 'test');
        return $order->refresh();
    }

    private function staff(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->branches()->attach($branch ?? $this->branch);
        return $user;
    }

    private function tableStatus(string $label): string
    {
        $tables = collect(app(StaffOrderService::class)->floorPlan($this->branch->id))->pluck('tables')->flatten(1);
        return $tables->firstWhere('label', $label)['status'];
    }

    public function test_kitchen_screen_shows_only_paid_orders_of_its_station(): void
    {
        $paid = $this->paidOrder('A1');
        $pending = $this->placeOrder('A2');
        $kitchen = $this->staff(Role::Kitchen);

        $response = $this->actingAs($kitchen)->getJson(route('staff.kitchen.snapshot', ['branch' => $this->branch->id, 'station' => 'kitchen']))->assertOk();

        $codes = collect($response->json('orders'))->pluck('code');
        $this->assertTrue($codes->contains($paid->code));
        $this->assertFalse($codes->contains($pending->code), 'Pesanan belum dibayar tidak boleh tampil di dapur.');
        $names = collect($response->json('orders.0.lines'))->pluck('name')->all();
        $this->assertSame(['Nasi Goreng'], $names, 'Layar dapur hanya memuat item stasiun dapur.');

        $barNames = collect($this->getJson(route('staff.kitchen.snapshot', ['branch' => $this->branch->id, 'station' => 'bar']))->json('orders.0.lines'))->pluck('name')->all();
        $this->assertSame(['Es Kopi Susu'], $barNames);
    }

    public function test_kitchen_role_uses_kitchen_screen_not_the_floor_plan(): void
    {
        $kitchen = $this->staff(Role::Kitchen);

        $this->actingAs($kitchen)->get(route('staff.dashboard'))->assertRedirect(route('staff.kitchen'));
        $this->get(route('staff.kitchen'))->assertOk()->assertSee('Dapur dan bar');
        $this->get(route('staff.refunds'))->assertForbidden();
    }

    public function test_kitchen_cannot_read_another_branch(): void
    {
        $other = Branch::create(['name' => 'Cabang Lain']);
        $kitchen = $this->staff(Role::Kitchen);

        $this->actingAs($kitchen)->getJson(route('staff.kitchen.snapshot', ['branch' => $other->id, 'station' => 'bar']))->assertStatus(422);
    }

    public function test_first_item_started_moves_the_order_to_preparing_and_all_ready_makes_the_table_ready(): void
    {
        $order = $this->paidOrder();
        $kitchen = $this->staff(Role::Kitchen);
        [$food, $coffee] = [$order->items[0], $order->items[1]];
        $this->assertSame('new', $this->tableStatus('A1'));

        $this->actingAs($kitchen)->postJson(route('staff.kitchen.prep', $food), ['status' => 'making'])->assertOk()->assertJson(['order_status' => 'preparing']);
        $this->assertSame(OrderStatus::Preparing, $order->refresh()->status);
        $this->assertSame('preparing', $this->tableStatus('A1'));

        $this->postJson(route('staff.kitchen.prep', $food), ['status' => 'ready'])->assertOk();
        $this->assertSame('preparing', $this->tableStatus('A1'), 'Masih ada item bar yang belum siap.');

        $this->postJson(route('staff.kitchen.prep', $coffee), ['status' => 'ready'])->assertOk();
        $this->assertSame('ready', $this->tableStatus('A1'));
        $this->assertDatabaseHas('order_status_logs', ['order_id' => $order->id, 'to_status' => 'preparing', 'actor' => 'user:' . $kitchen->id]);

        $this->actingAs($this->staff(Role::Manager))->postJson(route('staff.api.advance', $order))->assertOk()->assertJson(['status' => 'served']);
        $this->assertSame('served', $this->tableStatus('A1'));
    }

    public function test_unpaid_order_items_cannot_be_started(): void
    {
        $pending = $this->placeOrder();
        $kitchen = $this->staff(Role::Kitchen);

        $this->actingAs($kitchen)->postJson(route('staff.kitchen.prep', $pending->items[0]), ['status' => 'making'])->assertStatus(422);
        $this->assertSame('pending', $this->tableStatus('A1'));
    }

    public function test_cashier_requests_cancellation_and_manager_approves_it(): void
    {
        $order = $this->paidOrder();
        $cashier = $this->staff(Role::Cashier);

        $this->actingAs($cashier)->postJson(route('staff.cancellations.request', $order), ['reason' => ''])->assertStatus(422);
        $this->postJson(route('staff.cancellations.request', $order), ['reason' => 'Pelanggan berubah pikiran'])->assertOk();
        $this->postJson(route('staff.cancellations.request', $order), ['reason' => 'lagi'])->assertStatus(422);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status, 'Mengajukan belum membatalkan.');

        $snapshot = $this->getJson(route('staff.api.snapshot', ['branch' => $this->branch->id]))->json('orders.0.cancel_request');
        $this->assertSame('Pelanggan berubah pikiran', $snapshot['reason']);

        $manager = $this->staff(Role::Manager);
        $request = $order->cancellationRequests()->firstOrFail();
        $this->actingAs($manager)->postJson(route('staff.cancellations.decide', $request), ['approve' => 1])->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('refunds', ['order_id' => $order->id, 'amount' => $order->total, 'status' => 'pending']);
        $this->assertDatabaseHas('cancellation_requests', ['id' => $request->id, 'status' => 'approved', 'decided_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.cancel_approved', 'entity_id' => $order->id]);
    }

    public function test_rejecting_a_request_needs_a_reason_and_keeps_the_order_active(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->staff(Role::Cashier))->postJson(route('staff.cancellations.request', $order), ['reason' => 'salah catat'])->assertOk();
        $request = $order->cancellationRequests()->firstOrFail();
        $manager = $this->staff(Role::Manager);

        $this->actingAs($manager)->postJson(route('staff.cancellations.decide', $request), ['approve' => 0, 'note' => ''])->assertStatus(422);
        $this->postJson(route('staff.cancellations.decide', $request), ['approve' => 0, 'note' => 'Sudah dimasak'])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertDatabaseHas('cancellation_requests', ['id' => $request->id, 'status' => 'rejected', 'decision_note' => 'Sudah dimasak']);
    }

    public function test_only_cashiers_can_request_and_only_managers_can_decide(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->staff(Role::Kitchen))->postJson(route('staff.cancellations.request', $order), ['reason' => 'x'])->assertStatus(422);

        $this->actingAs($this->staff(Role::Cashier))->postJson(route('staff.cancellations.request', $order), ['reason' => 'x'])->assertOk();
        $request = $order->cancellationRequests()->firstOrFail();
        $this->actingAs($this->staff(Role::Cashier))->postJson(route('staff.cancellations.decide', $request), ['approve' => 1])->assertStatus(422);
    }

    public function test_payment_after_expiry_goes_to_review_and_is_resolved_with_a_note(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);
        $payments->applyOutcome($order, PaymentOutcome::Failed, 'test');
        $payments->applyOutcome($order->refresh(), PaymentOutcome::Paid, 'test');
        $manager = $this->staff(Role::Manager);

        $overview = $this->actingAs($manager)->getJson(route('staff.refunds.data', ['branch' => $this->branch->id]))->assertOk();
        $this->assertSame([$order->code], collect($overview->json('review'))->pluck('code')->all());

        $this->postJson(route('staff.refunds.review', $order), ['note' => ''])->assertStatus(422);
        $this->postJson(route('staff.refunds.review', $order), ['note' => 'Ref Kasera 123'])->assertOk();

        $this->assertFalse($order->refresh()->needs_review);
        $this->assertDatabaseHas('refunds', ['order_id' => $order->id, 'status' => 'done', 'note' => 'Ref Kasera 123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.review_resolved', 'entity_id' => $order->id]);
        $this->postJson(route('staff.refunds.review', $order), ['note' => 'lagi'])->assertStatus(422);
    }

    public function test_pending_refund_is_completed_with_a_note_and_cashier_cannot(): void
    {
        $order = $this->paidOrder();
        $manager = $this->staff(Role::Manager);
        $this->actingAs($manager)->postJson(route('staff.api.cancel', $order), ['reason' => 'Bahan habis'])->assertOk();
        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->staff(Role::Cashier))->postJson(route('staff.refunds.complete', $refund), ['note' => 'x'])->assertStatus(422);
        $this->actingAs($manager)->postJson(route('staff.refunds.complete', $refund), ['note' => ''])->assertStatus(422);
        $this->postJson(route('staff.refunds.complete', $refund), ['note' => 'Ref 99'])->assertOk();

        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'done', 'note' => 'Ref 99', 'resolved_by' => $manager->id]);
    }

    public function test_customer_status_page_shows_progress_steps_for_a_paid_order(): void
    {
        $order = $this->paidOrder();

        $this->get(route('pay.show', $order))->assertOk()->assertSee('Progres pesanan')->assertSee('Dibayar')->assertSee('Disajikan');
    }

    public function test_manager_can_mark_a_menu_item_sold_out_and_close_the_branch(): void
    {
        $manager = $this->staff(Role::Manager);
        $item = Item::query()->where('name', 'Nasi Goreng')->firstOrFail();

        $this->actingAs($manager)->postJson(route('staff.api.availability', [$this->branch, $item->id]), ['available' => 0])->assertOk();
        $menu = collect($this->getJson(route('staff.api.snapshot', ['branch' => $this->branch->id]))->json('menu'));
        $this->assertFalse($menu->firstWhere('id', $item->id)['is_available']);

        $this->postJson(route('staff.api.open', $this->branch), ['value' => 0])->assertOk();
        $this->assertFalse($this->branch->refresh()->is_open);
    }
}

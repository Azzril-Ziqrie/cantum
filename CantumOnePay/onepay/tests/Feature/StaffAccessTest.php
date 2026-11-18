<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function paidOrder(): Order
    {
        $table = DiningTable::query()->where('label', 'A1')->firstOrFail();
        $order = app(OrderService::class)->create($table, [['item_id' => 3, 'qty' => 1]], [], 'qris', '127.0.0.1');
        app(PaymentService::class)->applyOutcome($order, PaymentOutcome::Paid, 'test');
        return $order->refresh();
    }

    private function staff(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        if ($branch !== null) {
            $user->branches()->attach($branch);
        }
        return $user;
    }

    public function test_dashboard_requires_login(): void
    {
        $this->getJson(route('staff.api.snapshot', ['branch' => 1]))->assertUnauthorized();
        $this->get(route('staff.dashboard'))->assertRedirect('/staff/login');
    }

    public function test_snapshot_lists_only_paid_orders_of_the_staffs_branch(): void
    {
        $paid = $this->paidOrder();
        $pending = app(OrderService::class)->create(DiningTable::query()->where('label', 'A2')->firstOrFail(), [['item_id' => 4, 'qty' => 1]], [], 'qris', '127.0.0.1');
        $cashier = $this->staff(Role::Cashier, Branch::query()->first());

        $codes = collect($this->actingAs($cashier)->getJson(route('staff.api.snapshot', ['branch' => 1]))->assertOk()->json('orders'))->pluck('code');

        $this->assertTrue($codes->contains($paid->code));
        $this->assertFalse($codes->contains($pending->code), 'Pesanan belum dibayar tidak boleh tampil di dapur.');
    }

    public function test_staff_cannot_read_another_branch(): void
    {
        $other = Branch::create(['name' => 'Cabang Lain']);
        $cashier = $this->staff(Role::Cashier, Branch::query()->first());

        $this->actingAs($cashier)->getJson(route('staff.api.snapshot', ['branch' => $other->id]))->assertForbidden();
    }

    public function test_kitchen_can_advance_the_order_step_by_step(): void
    {
        $order = $this->paidOrder();
        $kitchen = $this->staff(Role::Kitchen, Branch::query()->first());

        $this->actingAs($kitchen)->postJson(route('staff.api.advance', $order))->assertOk()->assertJson(['status' => 'preparing']);
        $this->postJson(route('staff.api.advance', $order))->assertOk()->assertJson(['status' => 'served']);
    }

    public function test_cashier_cannot_cancel_but_manager_can_with_a_reason_that_creates_a_refund(): void
    {
        $order = $this->paidOrder();
        $branch = Branch::query()->first();

        $this->actingAs($this->staff(Role::Cashier, $branch))->postJson(route('staff.api.cancel', $order), ['reason' => 'x'])->assertStatus(422);

        $manager = $this->staff(Role::Manager, $branch);
        $this->actingAs($manager)->postJson(route('staff.api.cancel', $order), ['reason' => ''])->assertStatus(422);
        $this->postJson(route('staff.api.cancel', $order), ['reason' => 'Bahan habis'])->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('refunds', ['order_id' => $order->id, 'amount' => $order->total, 'reason' => 'Bahan habis']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.cancel_refund', 'entity_id' => $order->id]);
    }

    public function test_login_fails_for_inactive_user_and_wrong_password(): void
    {
        $user = User::factory()->create(['role' => Role::Cashier, 'is_active' => false, 'password' => 'rahasia123']);

        $this->post(route('staff.login'), ['email' => $user->email, 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}

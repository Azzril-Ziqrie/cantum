<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PrepStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    private Branch $east;
    private Branch $west;

    protected function setUp(): void
    {
        parent::setUp();
        Brand::create(['id' => 1, 'name' => 'Resto', 'tax_percent' => 0, 'service_percent' => 0, 'rounding_to' => 1, 'enabled_methods' => 'qris']);
        $this->east = Branch::create(['name' => 'Timur']);
        $this->west = Branch::create(['name' => 'Barat']);
        Item::create(['category_id' => Category::create(['name' => 'Kopi'])->id, 'name' => 'Kopi', 'price' => 20000]);
    }

    private function table(Branch $branch, string $label): DiningTable
    {
        return DiningTable::create(['branch_id' => $branch->id, 'label' => $label, 'token' => DiningTable::newToken()]);
    }

    private function paidOrder(DiningTable $table, int $qty = 1): Order
    {
        $order = app(OrderService::class)->create($table->load('branch'), [['item_id' => 1, 'qty' => $qty]], [], 'qris', '127.0.0.1');
        app(PaymentService::class)->applyOutcome($order, PaymentOutcome::Paid, 'test');
        return $order->refresh();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::Owner, 'is_active' => true]);
    }

    private function data(User $user): array
    {
        return collect($this->actingAs($user)->getJson(route('staff.overview.data'))->assertOk()->json('branches'))->keyBy('name')->all();
    }

    public function test_only_the_owner_can_see_the_overview(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager, 'is_active' => true]);
        $manager->branches()->attach($this->east);

        $this->actingAs($manager)->get(route('staff.overview'))->assertForbidden();
        $this->actingAs($manager)->getJson(route('staff.overview.data'))->assertForbidden();
        $this->actingAs($this->owner())->get(route('staff.overview'))->assertOk()->assertSee('Semua cabang');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('staff.overview'))->assertRedirect('/staff/login');
    }

    public function test_counts_revenue_and_tables_are_correct_per_branch(): void
    {
        $a1 = $this->table($this->east, 'A1');
        $a2 = $this->table($this->east, 'A2');
        $this->table($this->east, 'A3');
        $b1 = $this->table($this->west, 'B1');

        $this->paidOrder($a1);                                            // baru: 20.000
        $preparing = $this->paidOrder($a2, 2);                            // 40.000, diproses
        $preparing->update(['status' => OrderStatus::Preparing]);
        $this->paidOrder($b1);                                            // cabang lain: 20.000

        $east = $this->data($this->owner())['Timur'];

        $this->assertSame(['new' => 1, 'preparing' => 1, 'ready' => 0, 'served' => 0], $east['counts']);
        $this->assertSame(60000, $east['revenue_today']);
        $this->assertSame([3, 2], [$east['tables_total'], $east['tables_occupied']]);
        $this->assertSame(20000, $this->data($this->owner())['Barat']['revenue_today']);
    }

    public function test_a_preparing_order_with_all_items_ready_counts_as_ready(): void
    {
        $order = $this->paidOrder($this->table($this->east, 'A1'));
        $order->update(['status' => OrderStatus::Preparing]);
        $order->items()->update(['prep_status' => PrepStatus::Ready]);

        $this->assertSame(1, $this->data($this->owner())['Timur']['counts']['ready']);
    }

    public function test_unpaid_orders_are_not_counted_and_refunds_reduce_revenue(): void
    {
        $table = $this->table($this->east, 'A1');
        app(OrderService::class)->create($table->load('branch'), [['item_id' => 1, 'qty' => 1]], [], 'qris', '127.0.0.1');   // belum dibayar
        $cancelled = $this->paidOrder($table, 2);                                                                              // 40.000
        $cancelled->update(['status' => OrderStatus::Cancelled]);
        \DB::table('refunds')->insert(['order_id' => $cancelled->id, 'amount' => 40000, 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->paidOrder($table);                                                                                              // 20.000

        $east = $this->data($this->owner())['Timur'];

        $this->assertSame(1, $east['active_total']);
        $this->assertSame(20000, $east['revenue_today']);
    }

    public function test_inactive_branches_are_hidden_and_review_count_is_shown(): void
    {
        $this->west->update(['is_active' => false]);
        $order = $this->paidOrder($this->table($this->east, 'A1'));
        $order->update(['needs_review' => true]);

        $data = $this->data($this->owner());

        $this->assertSame(['Timur'], array_keys($data));
        $this->assertSame(1, $data['Timur']['review']);
    }

    public function test_dashboard_opens_on_the_branch_from_the_query_string(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('staff.dashboard', ['branch' => $this->east->id]))->assertOk()
            ->assertViewHas('clientConfig', fn ($config) => $config['branch'] === $this->east->id);
        // Cabang tak dikenal: jatuh ke cabang pertama menurut nama (Barat).
        $this->actingAs($owner)->get(route('staff.dashboard', ['branch' => 9999]))->assertOk()
            ->assertViewHas('clientConfig', fn ($config) => $config['branch'] === $this->west->id);
    }

    public function test_dashboard_ignores_a_branch_the_user_cannot_access(): void
    {
        $cashier = User::factory()->create(['role' => Role::Cashier, 'is_active' => true]);
        $cashier->branches()->attach($this->east);

        $this->actingAs($cashier)->get(route('staff.dashboard', ['branch' => $this->west->id]))->assertOk()
            ->assertViewHas('clientConfig', fn ($config) => $config['branch'] === $this->east->id);
    }

    public function test_owner_can_close_a_branch_from_the_overview_api(): void
    {
        $this->actingAs($this->owner())->postJson(route('staff.api.open', $this->east), ['value' => 0])->assertOk();

        $this->assertFalse($this->east->fresh()->is_open);
        $this->assertFalse($this->data($this->owner())['Timur']['is_open']);
    }
}

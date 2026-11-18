<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderRejected;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        Brand::create(['id' => 1, 'name' => 'Resto', 'tax_percent' => 10, 'service_percent' => 5, 'rounding_to' => 1, 'enabled_methods' => 'qris,va_bca,va_bri']);
        $this->branch = Branch::create(['name' => 'Cabang A']);
        $this->table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);
        $category = Category::create(['name' => 'Kopi']);
        Item::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 20000]);
    }

    private function order(string $method = 'qris'): Order
    {
        return app(OrderService::class)->create($this->table->fresh('branch'), [['item_id' => 1, 'qty' => 1]], [], $method, '127.0.0.1');
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::Owner, 'is_active' => true]);
    }

    private function branchForm(array $overrides = []): array
    {
        return $overrides + ['name' => 'Cabang A', 'prep_minutes' => 15, 'last_order_minutes' => 0, 'is_open' => 1, 'is_active' => 1];
    }

    public function test_branch_without_overrides_follows_the_brand(): void
    {
        $order = $this->order();

        // 20.000 + service 5% (1.000) = 21.000; pajak 10% = 2.100; total 23.100.
        $this->assertSame([1000, 2100, 23100], [$order->service, $order->tax, $order->total]);
    }

    public function test_branch_can_drop_service_charge_while_brand_keeps_it(): void
    {
        $this->branch->update(['service_percent' => 0]);

        $order = $this->order();

        $this->assertSame(0, $order->service);
        $this->assertSame(2000, $order->tax);
        $this->assertSame(22000, $order->total);
        $this->assertEquals(5, Brand::current()->service_percent);
    }

    public function test_branch_can_have_a_different_tax_rate(): void
    {
        $this->branch->update(['tax_percent' => 0]);

        $this->assertSame(0, $this->order()->tax);
        $this->assertSame(21000, Order::query()->latest('id')->value('total'));
    }

    public function test_branch_rounding_overrides_the_brand(): void
    {
        $this->branch->update(['rounding_to' => 1000]);

        $order = $this->order();

        $this->assertSame(23000, $order->total);
        $this->assertSame(-100, $order->rounding);
    }

    public function test_branch_can_restrict_payment_methods(): void
    {
        $this->branch->update(['enabled_methods' => 'qris']);

        $this->assertSame('qris', $this->order('qris')->payment->method);

        $this->expectException(OrderRejected::class);
        $this->order('va_bca');
    }

    public function test_branch_methods_never_exceed_the_brand(): void
    {
        $this->branch->update(['enabled_methods' => 'qris,va_mandiri']);

        $this->assertSame(['qris'], $this->branch->fresh()->effectiveSettings()->enabledMethods);
    }

    public function test_existing_orders_keep_their_numbers_when_rules_change(): void
    {
        $order = $this->order();

        $this->branch->update(['service_percent' => 0, 'tax_percent' => 0]);

        $this->assertSame([1000, 2100, 23100], [$order->fresh()->service, $order->fresh()->tax, $order->fresh()->total]);
    }

    public function test_customer_menu_receives_the_branch_rules(): void
    {
        $this->branch->update(['service_percent' => 0, 'tax_percent' => 3, 'enabled_methods' => 'qris']);

        $config = $this->get(route('menu.show', $this->table->token))->assertOk()->viewData('clientConfig');

        $this->assertSame([0.0, 3.0], [$config['service'], $config['tax']]);
        $this->assertSame(['qris'], array_keys($config['methods']));
    }

    public function test_owner_saves_branch_rules_and_it_is_audited(): void
    {
        $this->actingAs($this->owner())
            ->put(route('admin.settings.branches.update', $this->branch), $this->branchForm([
                'service_percent' => '0', 'tax_percent' => '8.5', 'rounding_to' => '100',
                'restrict_methods' => 1, 'branch_methods' => ['qris', 'va_bca'],
            ]))->assertSessionHasNoErrors();

        $branch = $this->branch->fresh();
        $this->assertEquals([0, 8.5, 100], [$branch->service_percent, $branch->tax_percent, $branch->rounding_to]);
        $this->assertSame('qris,va_bca', $branch->enabled_methods);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.rules', 'entity_id' => $branch->id]);
    }

    public function test_blank_fields_clear_the_override(): void
    {
        $this->branch->update(['service_percent' => 0, 'enabled_methods' => 'qris']);

        $this->actingAs($this->owner())
            ->put(route('admin.settings.branches.update', $this->branch), $this->branchForm(['service_percent' => '', 'tax_percent' => '', 'rounding_to' => '']))
            ->assertSessionHasNoErrors();

        $branch = $this->branch->fresh();
        $this->assertNull($branch->service_percent);
        $this->assertNull($branch->enabled_methods);
    }

    public function test_methods_outside_the_brand_or_empty_are_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('admin.settings.branches.update', $this->branch), $this->branchForm(['restrict_methods' => 1, 'branch_methods' => ['va_mandiri']]))
            ->assertSessionHasErrors('form');
        $this->actingAs($owner)->put(route('admin.settings.branches.update', $this->branch), $this->branchForm(['restrict_methods' => 1]))
            ->assertSessionHasErrors('form');
        $this->assertNull($this->branch->fresh()->enabled_methods);
    }

    public function test_out_of_range_values_are_rejected(): void
    {
        $this->actingAs($this->owner())
            ->put(route('admin.settings.branches.update', $this->branch), $this->branchForm(['tax_percent' => '150', 'rounding_to' => '7']))
            ->assertSessionHasErrors(['tax_percent', 'rounding_to']);
    }

    public function test_only_the_owner_can_change_branch_rules(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager, 'is_active' => true]);
        $manager->branches()->attach($this->branch);

        $this->actingAs($manager)->put(route('admin.settings.branches.update', $this->branch), $this->branchForm(['service_percent' => '0']))->assertForbidden();

        $this->assertNull($this->branch->fresh()->service_percent);
    }
}

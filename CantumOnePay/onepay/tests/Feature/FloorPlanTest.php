<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\FloorElement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FloorPlanTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;
    private Branch $other;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Brand::create(['id' => 1, 'name' => 'Kopi Tes']);
        $this->main = Branch::create(['name' => 'Utama']);
        $this->other = Branch::create(['name' => 'Cabang Dua']);
        $this->manager = $this->staff(Role::Manager, $this->main);
    }

    private function staff(Role $role, Branch ...$branches): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->branches()->attach(array_map(fn (Branch $b) => $b->id, $branches));
        return $user;
    }

    private function table(string $label, ?Branch $branch = null): DiningTable
    {
        $branch ??= $this->main;
        return DiningTable::create(['branch_id' => $branch->id, 'floor_area_id' => $branch->defaultArea()->id, 'label' => $label, 'token' => DiningTable::newToken()]);
    }

    private function tableUrl(DiningTable $table, string $suffix = ''): string
    {
        return url("admin/tables/table/{$table->id}{$suffix}");
    }

    private function orderFor(DiningTable $table): Order
    {
        return Order::create([
            'code' => 'OP' . $table->id . 'TEST', 'branch_id' => $table->branch_id, 'dining_table_id' => $table->id, 'table_label' => $table->label,
            'subtotal' => 10000, 'total' => 11100, 'expires_at' => now()->addMinutes(15),
        ]);
    }

    /* ---------- hak akses ---------- */

    public function test_page_requires_login_and_a_role_that_manages_the_floor(): void
    {
        $this->get(route('admin.tables'))->assertRedirect('/staff/login');

        $this->actingAs($this->staff(Role::Cashier, $this->main))->get(route('admin.tables'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.tables'))->assertOk()->assertSee('Meja dan denah');
    }

    public function test_cashier_cannot_change_the_floor_plan(): void
    {
        $table = $this->table('A1');

        $this->actingAs($this->staff(Role::Cashier, $this->main))
            ->patchJson($this->tableUrl($table), ['seats' => 9])->assertStatus(422)->assertJson(['error' => 'Tidak punya akses.']);

        $this->assertSame(4, $table->refresh()->seats);
    }

    public function test_manager_is_confined_to_their_own_branch(): void
    {
        $foreign = $this->table('Z1', $this->other);
        $this->actingAs($this->manager);

        $this->getJson(route('admin.tables.layout', ['branch' => $this->other->id]))->assertStatus(422);
        $this->patchJson($this->tableUrl($foreign), ['seats' => 9])->assertStatus(422);
        $this->postJson(route('admin.tables.store', $this->other), ['label' => 'Z2'])->assertStatus(422);
        $this->postJson($this->tableUrl($foreign, '/token'))->assertStatus(422);
        $this->deleteJson($this->tableUrl($foreign))->assertStatus(422);

        $this->assertSame(4, $foreign->refresh()->seats);
        $this->assertDatabaseMissing('dining_tables', ['label' => 'Z2']);
    }

    public function test_owner_can_work_on_every_branch(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner, 'is_active' => true]);

        $this->actingAs($owner)->postJson(route('admin.tables.store', $this->other), ['label' => 'Z1'])->assertOk();
    }

    /* ---------- layout dan meja ---------- */

    public function test_layout_is_one_flat_plan_with_tables_and_their_qr_urls(): void
    {
        $table = $this->table('A1');
        $this->main->defaultArea()->elements()->create(['type' => 'bar', 'label' => 'Bar', 'pos_x' => 10, 'pos_y' => 10, 'width' => 200, 'height' => 40]);

        $layout = $this->actingAs($this->manager)->getJson(route('admin.tables.layout', ['branch' => $this->main->id]))->assertOk()->json();

        $this->assertArrayNotHasKey('areas', $layout);
        $this->assertSame('A1', $layout['tables'][0]['label']);
        $this->assertSame(route('menu.show', $table->token), $layout['tables'][0]['url']);
        $this->assertSame('Bar', $layout['elements'][0]['label']);
    }

    public function test_layout_gathers_tables_and_elements_from_every_area_of_the_branch(): void
    {
        $this->table('A1');
        $legacy = FloorArea::create(['branch_id' => $this->main->id, 'name' => 'Outdoor']);
        DiningTable::create(['branch_id' => $this->main->id, 'floor_area_id' => $legacy->id, 'label' => 'B1', 'token' => DiningTable::newToken()]);
        $legacy->elements()->create(['type' => 'door', 'label' => 'Pintu', 'pos_x' => 0, 'pos_y' => 0, 'width' => 100, 'height' => 16]);

        $layout = $this->actingAs($this->manager)->getJson(route('admin.tables.layout', ['branch' => $this->main->id]))->assertOk()->json();

        $this->assertSame(['A1', 'B1'], array_column($layout['tables'], 'label'));
        $this->assertCount(1, $layout['elements']);
    }

    public function test_every_branch_gets_exactly_one_hidden_default_area(): void
    {
        $first = $this->main->defaultArea();
        $second = $this->main->defaultArea();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(FloorArea::DEFAULT_NAME, $first->name);
        $this->assertSame(1, FloorArea::query()->where('branch_id', $this->main->id)->count());
        $this->assertNotSame($first->id, $this->other->defaultArea()->id);
    }

    public function test_new_tables_and_elements_land_in_the_branchs_default_area(): void
    {
        $this->actingAs($this->manager);
        $table = $this->postJson(route('admin.tables.store', $this->main), ['label' => 'A1'])->assertOk()->json();
        $element = $this->postJson(route('admin.tables.elements.store', $this->main), ['type' => 'bar', 'pos_x' => 10, 'pos_y' => 10, 'width' => 200, 'height' => 40])->assertOk()->json();
        $this->postJson(route('admin.tables.bulk', $this->main), ['prefix' => 'B', 'from' => 1, 'to' => 3])->assertOk();

        $areaId = $this->main->defaultArea()->id;
        $this->assertSame($areaId, DiningTable::query()->findOrFail($table['id'])->floor_area_id);
        $this->assertSame($areaId, FloorElement::query()->findOrFail($element['id'])->floor_area_id);
        $this->assertSame(1, FloorArea::query()->where('branch_id', $this->main->id)->count(), 'Tidak ada area baru yang dibuat.');
        $this->assertSame(4, DiningTable::query()->where('floor_area_id', $areaId)->count());
    }

    public function test_editor_page_no_longer_offers_areas(): void
    {
        $this->actingAs($this->manager)->get(route('admin.tables'))->assertOk()
            ->assertDontSee('+ Area', false)->assertDontSee('Nonaktifkan area', false)->assertSee('+ Banyak meja', false);
    }

    public function test_area_routes_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.tables.areas.store'));
        $this->assertFalse(Route::has('admin.tables.areas.update'));
        $this->assertFalse(Route::has('admin.tables.areas.order'));
    }

    public function test_position_changes_are_saved_and_out_of_range_values_are_rejected(): void
    {
        $table = $this->table('A1');
        $this->actingAs($this->manager);

        $this->patchJson($this->tableUrl($table), ['pos_x' => 230, 'pos_y' => 140, 'rotation' => 45, 'shape' => 'round', 'locked' => true])->assertOk();
        $table->refresh();
        $this->assertSame([230, 140, 45, 'round', true], [$table->pos_x, $table->pos_y, $table->rotation, $table->shape, $table->locked]);

        $this->patchJson($this->tableUrl($table), ['pos_x' => 5000])->assertStatus(422);
        $this->patchJson($this->tableUrl($table), ['shape' => 'triangle'])->assertStatus(422);
        $this->assertSame(230, $table->refresh()->pos_x);
    }

    public function test_bulk_creates_a_numbered_range_with_unique_tokens(): void
    {
        $this->actingAs($this->manager)->postJson(route('admin.tables.bulk', $this->main), ['prefix' => 'A', 'from' => 1, 'to' => 10])
            ->assertOk()->assertJsonCount(10, 'tables');

        $tables = DiningTable::query()->where('branch_id', $this->main->id)->get();
        $this->assertCount(10, $tables);
        $this->assertCount(10, $tables->pluck('token')->unique());
        $this->assertTrue($tables->pluck('label')->contains('A10'));
    }

    public function test_bulk_is_all_or_nothing_when_a_label_already_exists(): void
    {
        $this->table('A3');

        $this->actingAs($this->manager)->postJson(route('admin.tables.bulk', $this->main), ['prefix' => 'A', 'from' => 1, 'to' => 5])
            ->assertStatus(422)->assertJsonFragment(['error' => 'Meja A3 sudah ada di cabang ini.']);

        $this->assertSame(1, DiningTable::query()->count());
    }

    public function test_bulk_rejects_reversed_or_oversized_ranges(): void
    {
        $this->actingAs($this->manager);

        $this->postJson(route('admin.tables.bulk', $this->main), ['prefix' => 'A', 'from' => 10, 'to' => 1])->assertStatus(422);
        $this->postJson(route('admin.tables.bulk', $this->main), ['prefix' => 'A', 'from' => 1, 'to' => 200])->assertStatus(422);
        $this->assertSame(0, DiningTable::query()->count());
    }

    public function test_duplicate_label_in_the_same_branch_is_rejected_but_allowed_in_another_branch(): void
    {
        $this->table('A1');
        $owner = User::factory()->create(['role' => Role::Owner, 'is_active' => true]);

        $this->actingAs($this->manager)->postJson(route('admin.tables.store', $this->main), ['label' => 'A1'])->assertStatus(422);
        $this->actingAs($owner)->postJson(route('admin.tables.store', $this->other), ['label' => 'A1'])->assertOk();
    }

    public function test_renaming_to_an_existing_label_is_rejected(): void
    {
        $this->table('A1');
        $second = $this->table('A2');

        $this->actingAs($this->manager)->patchJson($this->tableUrl($second), ['label' => 'A1'])->assertStatus(422);
        $this->assertSame('A2', $second->refresh()->label);
    }

    public function test_duplicate_copies_the_table_with_the_next_number_and_a_fresh_token(): void
    {
        $table = $this->table('A3');
        $table->update(['seats' => 6, 'shape' => 'long', 'width' => 140, 'height' => 70]);

        $copy = $this->actingAs($this->manager)->postJson($this->tableUrl($table, '/duplicate'))->assertOk()->json();

        $this->assertSame('A4', $copy['label']);
        $this->assertSame([6, 'long', 140], [$copy['seats'], $copy['shape'], $copy['width']]);
        $this->assertNotSame($table->token, DiningTable::query()->findOrFail($copy['id'])->token);
    }

    public function test_table_without_orders_is_deleted_but_one_with_orders_is_only_deactivated(): void
    {
        $clean = $this->table('A1');
        $used = $this->table('A2');
        $this->orderFor($used);
        $this->actingAs($this->manager);

        $this->deleteJson($this->tableUrl($clean))->assertOk()->assertJson(['result' => 'deleted']);
        $this->deleteJson($this->tableUrl($used))->assertOk()->assertJson(['result' => 'deactivated']);

        $this->assertDatabaseMissing('dining_tables', ['id' => $clean->id]);
        $this->assertFalse($used->refresh()->is_active);
        $this->assertSame(1, $used->orders()->count(), 'Riwayat pesanan tetap ada.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.active', 'entity_id' => $used->id]);
    }

    public function test_deactivated_table_can_no_longer_be_ordered(): void
    {
        $table = $this->table('A1');
        $this->actingAs($this->manager)->patchJson($this->tableUrl($table), ['is_active' => false])->assertOk();

        $this->get(route('menu.show', $table->token))->assertNotFound();
    }

    /* ---------- token dan QR ---------- */

    public function test_regenerating_the_token_invalidates_the_old_qr_immediately_and_is_audited(): void
    {
        $table = $this->table('A1');
        $oldToken = $table->token;

        $this->actingAs($this->manager)->postJson($this->tableUrl($table, '/token'))->assertOk();

        $table->refresh();
        $this->assertNotSame($oldToken, $table->token);
        $this->assertNull(DiningTable::findOrderable($oldToken));
        $this->assertNotNull(DiningTable::findOrderable($table->token));
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.token', 'entity' => 'table', 'entity_id' => $table->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'table.token')->count());
    }

    public function test_qr_endpoint_returns_an_svg_that_encodes_the_table_url(): void
    {
        $table = $this->table('A1');
        $this->actingAs($this->manager);

        $response = $this->get($this->tableUrl($table, '/qr.svg'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringStartsWith('<svg', $response->getContent());

        $other = $this->table('A2');
        $this->assertNotSame($response->getContent(), $this->get($this->tableUrl($other, '/qr.svg'))->getContent());
    }

    public function test_sticker_carries_the_table_number_and_brand(): void
    {
        $table = $this->table('A7');

        $svg = $this->actingAs($this->manager)->get($this->tableUrl($table, '/sticker.svg'))->assertOk()->getContent();

        $this->assertStringContainsString('MEJA A7', $svg);
        $this->assertStringContainsString('KOPI TES', $svg);
    }

    public function test_qr_and_sheet_are_forbidden_for_another_branch_and_for_guests(): void
    {
        $foreign = $this->table('Z1', $this->other);

        $this->get($this->tableUrl($foreign, '/qr.svg'))->assertRedirect('/staff/login');

        $this->actingAs($this->manager);
        $this->get($this->tableUrl($foreign, '/qr.svg'))->assertForbidden();
        $this->get($this->tableUrl($foreign, '/sticker.svg'))->assertForbidden();
        $this->get(route('admin.tables.sheet', ['branch' => $this->other->id]))->assertForbidden();
    }

    public function test_sheet_prints_a_sticker_for_every_active_table_or_only_the_chosen_ones(): void
    {
        $a1 = $this->table('A1');
        $this->table('A2');
        $this->table('A3')->update(['is_active' => false]);
        $this->actingAs($this->manager);

        $this->get(route('admin.tables.sheet', ['branch' => $this->main->id]))
            ->assertOk()->assertSee('MEJA A1', false)->assertSee('MEJA A2', false)->assertDontSee('MEJA A3', false);

        $this->get(route('admin.tables.sheet', ['branch' => $this->main->id, 'ids' => [$a1->id]]))
            ->assertOk()->assertSee('MEJA A1', false)->assertDontSee('MEJA A2', false);
    }

    /* ---------- elemen dan salin denah ---------- */

    public function test_decorative_elements_can_be_added_moved_and_removed(): void
    {
        $this->actingAs($this->manager);
        $element = $this->postJson(route('admin.tables.elements.store', $this->main), ['type' => 'bar', 'label' => 'Bar', 'pos_x' => 40, 'pos_y' => 30, 'width' => 240, 'height' => 44])->assertOk()->json();

        $this->patchJson(route('admin.tables.elements.update', $element['id']), ['pos_x' => 100])->assertOk();
        $this->assertDatabaseHas('floor_elements', ['id' => $element['id'], 'pos_x' => 100]);

        $this->postJson(route('admin.tables.elements.store', $this->main), ['type' => 'sofa'])->assertStatus(422);

        $this->deleteJson(route('admin.tables.elements.destroy', $element['id']))->assertOk();
        $this->assertDatabaseMissing('floor_elements', ['id' => $element['id']]);
    }

    public function test_copying_a_layout_creates_independent_tables_with_new_tokens(): void
    {
        $source = $this->table('A1');
        $this->main->defaultArea()->elements()->create(['type' => 'bar', 'label' => 'Bar', 'pos_x' => 10, 'pos_y' => 10, 'width' => 200, 'height' => 40]);
        $owner = User::factory()->create(['role' => Role::Owner, 'is_active' => true]);

        $this->actingAs($owner)->postJson(route('admin.tables.copy'), ['branch_id' => $this->other->id, 'source_branch_id' => $this->main->id])
            ->assertOk()->assertJson(['copied' => 1]);

        $copy = DiningTable::query()->where('branch_id', $this->other->id)->firstOrFail();
        $this->assertSame('A1', $copy->label);
        $this->assertNotSame($source->token, $copy->token);
        $this->assertSame(1, $this->other->defaultArea()->elements()->count());
        $this->assertSame(1, DiningTable::query()->where('branch_id', $this->main->id)->count(), 'Cabang sumber tidak berubah.');
    }

    public function test_copying_a_layout_needs_access_to_both_branches_and_two_different_ones(): void
    {
        $this->table('A1');

        $this->actingAs($this->manager)->postJson(route('admin.tables.copy'), ['branch_id' => $this->other->id, 'source_branch_id' => $this->main->id])->assertStatus(422);
        $this->postJson(route('admin.tables.copy'), ['branch_id' => $this->main->id, 'source_branch_id' => $this->main->id])->assertStatus(422);
        $this->assertSame(0, DiningTable::query()->where('branch_id', $this->other->id)->count());
    }

    public function test_copying_a_layout_stops_when_a_label_already_exists_in_the_target(): void
    {
        $this->table('A1');
        $owner = User::factory()->create(['role' => Role::Owner, 'is_active' => true]);
        $this->table('A1', $this->other);

        $this->actingAs($owner)->postJson(route('admin.tables.copy'), ['branch_id' => $this->other->id, 'source_branch_id' => $this->main->id])->assertStatus(422);

        $this->assertSame(1, DiningTable::query()->where('branch_id', $this->other->id)->count());
    }
}

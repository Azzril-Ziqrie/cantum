<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\User;
use App\Services\OrderRejected;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuAdminTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Branch $otherBranch;
    private Category $drinks;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Brand::create(['id' => 1, 'name' => 'Uji']);
        $this->branch = Branch::create(['name' => 'Utama']);
        $this->otherBranch = Branch::create(['name' => 'Kedua']);
        $this->drinks = Category::create(['name' => 'Minuman', 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $branch && $user->branches()->attach($branch);
        return $user;
    }

    private function item(array $attributes = []): Item
    {
        return Item::create($attributes + ['category_id' => $this->drinks->id, 'name' => 'Teh', 'price' => 10000, 'cost' => 3000]);
    }

    private function menuNames(Branch $branch): array
    {
        return array_column(app(OrderService::class)->branchMenu($branch->id), 'name');
    }

    private function itemPayload(array $overrides = []): array
    {
        return $overrides + ['category_id' => $this->drinks->id, 'name' => 'Latte', 'price' => 25000, 'cost' => 8000, 'station' => 'bar', 'is_active' => 1];
    }

    /** PNG 1x1 asli (GD tidak terpasang di PHP ini, jadi UploadedFile::fake()->image() tidak bisa dipakai). */
    private function png(string $name, int $paddingKb = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($name, $png . str_repeat('0', $paddingKb * 1024));
    }

    /* ---------------------------------------------------------------- hak akses */

    public function test_guests_are_sent_to_login_and_staff_without_menu_rights_are_not_listed(): void
    {
        $this->get(route('admin.menu'))->assertRedirect('/staff/login');
        $this->actingAs($this->user(Role::Owner))->get(route('admin.menu'))->assertOk()->assertSee('Tambah menu');
    }

    public function test_manager_can_view_but_not_change_the_base_menu(): void
    {
        $manager = $this->user(Role::Manager, $this->branch);

        $this->actingAs($manager)->get(route('admin.menu'))->assertOk()->assertDontSee('Tambah menu');
        $this->post(route('admin.menu.items.store'), $this->itemPayload())->assertSessionHasErrors('menu');
        $this->assertDatabaseMissing('items', ['name' => 'Latte']);

        $item = $this->item();
        $this->put(route('admin.menu.items.update', $item), $this->itemPayload(['name' => 'Diganti']))->assertSessionHasErrors('menu');
        $this->assertSame('Teh', $item->refresh()->name);
    }

    public function test_cashier_cannot_open_branch_settings(): void
    {
        $item = $this->item();
        $cashier = $this->user(Role::Cashier, $this->branch);

        // Ditolak sejak di route (EnsureCanManageMenu); service tetap memeriksa ulang sebagai lapis kedua.
        $this->actingAs($cashier)->putJson(route('admin.menu.branch-items.update', [$this->branch, $item]), ['is_available' => false])
            ->assertForbidden();
        $this->assertDatabaseCount('branch_items', 0);
    }

    /* ---------------------------------------------------------------- menu dan foto */

    public function test_owner_creates_an_item_with_a_photo_and_it_shows_on_the_customer_menu(): void
    {
        $owner = $this->user(Role::Owner);

        $this->actingAs($owner)->post(route('admin.menu.items.store'), $this->itemPayload(['photo' => $this->png('latte.png')]))
            ->assertSessionHasNoErrors();

        $item = Item::query()->where('name', 'Latte')->firstOrFail();
        Storage::disk('public')->assertExists($item->photo);
        $menu = app(OrderService::class)->branchMenu($this->branch->id);
        $this->assertSame($item->photoUrl(), $menu[0]['photo_url']);
    }

    public function test_photo_must_be_a_small_image(): void
    {
        $this->actingAs($this->user(Role::Owner));

        $this->post(route('admin.menu.items.store'), $this->itemPayload(['photo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('photo');
        $this->post(route('admin.menu.items.store'), $this->itemPayload(['photo' => $this->png('besar.png', 4096)]))->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('items', 0);
    }

    public function test_replacing_and_removing_a_photo_deletes_the_old_file(): void
    {
        $owner = $this->user(Role::Owner);
        $this->actingAs($owner)->post(route('admin.menu.items.store'), $this->itemPayload(['photo' => $this->png('a.png')]));
        $item = Item::query()->firstOrFail();
        $first = $item->photo;

        $this->put(route('admin.menu.items.update', $item), $this->itemPayload(['photo' => $this->png('b.png')]));
        Storage::disk('public')->assertMissing($first);
        $second = $item->refresh()->photo;
        Storage::disk('public')->assertExists($second);

        $this->put(route('admin.menu.items.update', $item), $this->itemPayload(['remove_photo' => 1]));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($item->refresh()->photo);
    }

    public function test_changing_the_base_price_is_audited(): void
    {
        $owner = $this->user(Role::Owner);
        $item = $this->item();

        $this->actingAs($owner)->put(route('admin.menu.items.update', $item), $this->itemPayload(['name' => 'Teh', 'price' => 12000]));

        $this->assertDatabaseHas('audit_logs', ['action' => 'item.price', 'entity_id' => $item->id, 'old_value' => '10000', 'new_value' => '12000']);
    }

    /* ---------------------------------------------------------------- jadwal tampil */

    public function test_scheduled_item_only_appears_inside_its_window(): void
    {
        $this->item(['name' => 'Bubur', 'available_from' => '06:00', 'available_until' => '11:00']);
        $this->item(['name' => 'Teh']);

        Carbon::setTestNow('2026-11-19 08:00:00');
        $this->assertEqualsCanonicalizing(['Bubur', 'Teh'], $this->menuNames($this->branch));

        Carbon::setTestNow('2026-11-19 14:00:00');
        $this->assertSame(['Teh'], $this->menuNames($this->branch));
    }

    public function test_schedule_crossing_midnight_is_supported(): void
    {
        $this->item(['name' => 'Ronde', 'available_from' => '22:00', 'available_until' => '02:00']);

        Carbon::setTestNow('2026-11-19 23:30:00');
        $this->assertSame(['Ronde'], $this->menuNames($this->branch));
        Carbon::setTestNow('2026-11-20 01:00:00');
        $this->assertSame(['Ronde'], $this->menuNames($this->branch));
        Carbon::setTestNow('2026-11-19 12:00:00');
        $this->assertSame([], $this->menuNames($this->branch));
    }

    public function test_ordering_outside_the_schedule_is_rejected_on_the_server(): void
    {
        $item = $this->item(['name' => 'Bubur', 'available_from' => '06:00', 'available_until' => '11:00']);
        $table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);

        Carbon::setTestNow('2026-11-19 15:00:00');
        $this->expectException(OrderRejected::class);
        app(OrderService::class)->create($table->load('branch'), [['item_id' => $item->id, 'qty' => 1]], [], 'qris', '127.0.0.1');
    }

    /* ---------------------------------------------------------------- per cabang */

    public function test_manager_sets_sold_out_and_a_branch_price_for_their_own_branch(): void
    {
        $item = $this->item();
        $manager = $this->user(Role::Manager, $this->branch);

        $this->actingAs($manager)->putJson(route('admin.menu.branch-items.update', [$this->branch, $item]), ['is_available' => true, 'price_override' => 12500])->assertOk();
        $this->assertSame(12500, app(OrderService::class)->branchMenu($this->branch->id)[0]['price']);
        $this->assertSame(10000, app(OrderService::class)->branchMenu($this->otherBranch->id)[0]['price']);

        $this->putJson(route('admin.menu.branch-items.update', [$this->branch, $item]), ['is_available' => false, 'price_override' => 12500])->assertOk();
        $this->assertSame([], $this->menuNames($this->branch));
        $this->assertSame(['Teh'], $this->menuNames($this->otherBranch));
        $this->assertTrue(AuditLog::query()->where('action', 'item.availability')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'item.branch_price')->exists());
    }

    public function test_manager_cannot_change_another_branch(): void
    {
        $item = $this->item();
        $manager = $this->user(Role::Manager, $this->branch);

        $this->actingAs($manager)->putJson(route('admin.menu.branch-items.update', [$this->otherBranch, $item]), ['is_available' => false])->assertStatus(422);
        $this->assertSame(['Teh'], $this->menuNames($this->otherBranch));
    }

    public function test_copying_branch_settings_replaces_the_target(): void
    {
        $tea = $this->item();
        $coffee = $this->item(['name' => 'Kopi']);
        $owner = $this->user(Role::Owner);
        $this->actingAs($owner)->putJson(route('admin.menu.branch-items.update', [$this->branch, $tea]), ['is_available' => false]);
        $this->putJson(route('admin.menu.branch-items.update', [$this->branch, $coffee]), ['is_available' => true, 'price_override' => 15000]);

        $this->post(route('admin.menu.branches.copy', $this->otherBranch), ['source' => $this->branch->id])->assertSessionHasNoErrors();

        $menu = app(OrderService::class)->branchMenu($this->otherBranch->id);
        $this->assertSame(['Kopi'], array_column($menu, 'name'));
        $this->assertSame(15000, $menu[0]['price']);
    }

    /* ---------------------------------------------------------------- ubah massal */

    public function test_bulk_percent_price_preview_changes_nothing_and_apply_does(): void
    {
        $tea = $this->item(['price' => 20000]);
        $other = Category::create(['name' => 'Makanan']);
        $rice = $this->item(['name' => 'Nasi', 'category_id' => $other->id, 'price' => 30000]);
        $owner = $this->user(Role::Owner);
        $input = ['category_id' => $this->drinks->id, 'mode' => 'percent', 'value' => 10];

        $this->actingAs($owner)->post(route('admin.menu.bulk.preview'), $input)->assertSessionHas('bulk_preview.rows.0.new', 22000);
        $this->assertSame(20000, $tea->refresh()->price);

        $this->post(route('admin.menu.bulk.apply'), $input);
        $this->assertSame(22000, $tea->refresh()->price);
        $this->assertSame(30000, $rice->refresh()->price, 'Kategori lain tidak boleh berubah.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'item.price', 'entity_id' => $tea->id, 'new_value' => '22000']);
    }

    public function test_bulk_amount_never_goes_below_zero(): void
    {
        $tea = $this->item(['price' => 1500]);

        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.bulk.apply'), ['mode' => 'amount', 'value' => -5000]);

        $this->assertSame(0, $tea->refresh()->price);
    }

    public function test_bulk_price_is_owner_only(): void
    {
        $tea = $this->item(['price' => 20000]);

        $this->actingAs($this->user(Role::Manager, $this->branch))->post(route('admin.menu.bulk.apply'), ['mode' => 'amount', 'value' => 1000])->assertSessionHasErrors('menu');

        $this->assertSame(20000, $tea->refresh()->price);
    }

    /* ---------------------------------------------------------------- CSV */

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('menu.csv', $content);
    }

    public function test_csv_import_creates_and_updates_menu_and_categories(): void
    {
        $existing = $this->item(['name' => 'Teh', 'price' => 10000]);
        $csv = "category,name,description,price,cost,station,best_seller,active,sort_order\n"
            . "Minuman,Teh,Teh manis,12000,3500,bar,1,1,1\n"
            . "Dessert,Pudding,,15000,5000,kitchen,0,1,2\n";

        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.import'), ['file' => $this->csv($csv)])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '1 menu baru, 1 diperbarui'));

        $this->assertSame(12000, $existing->refresh()->price);
        $this->assertTrue($existing->is_best_seller);
        $this->assertDatabaseHas('categories', ['name' => 'Dessert']);
        $this->assertDatabaseHas('items', ['name' => 'Pudding', 'price' => 15000, 'station' => 'kitchen']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'item.price', 'entity_id' => $existing->id, 'new_value' => '12000']);
    }

    public function test_csv_import_accepts_semicolon_delimiters_from_excel(): void
    {
        $csv = "\xEF\xBB\xBFcategory;name;price\nMinuman;Es Teh;8000\n";

        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.import'), ['file' => $this->csv($csv)])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items', ['name' => 'Es Teh', 'price' => 8000]);
    }

    public function test_one_bad_csv_row_cancels_the_whole_import(): void
    {
        $csv = "category,name,price\nMinuman,Bagus,9000\nMinuman,Rusak,gratis\n";

        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.import'), ['file' => $this->csv($csv)])->assertSessionHasErrors('menu');

        $this->assertDatabaseMissing('items', ['name' => 'Bagus']);
    }

    public function test_csv_without_required_header_is_rejected(): void
    {
        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.import'), ['file' => $this->csv("nama,harga\nTeh,5000\n")])->assertSessionHasErrors('menu');
    }

    public function test_csv_export_lists_every_item(): void
    {
        $this->item(['name' => 'Teh']);

        $response = $this->actingAs($this->user(Role::Manager, $this->branch))->get(route('admin.menu.export'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('category,name,description,price', $content);
        $this->assertStringContainsString('Minuman,Teh', $content);
    }

    /* ---------------------------------------------------------------- modifier, paket, hapus */

    public function test_owner_manages_modifier_groups_and_options(): void
    {
        $item = $this->item();
        $this->actingAs($this->user(Role::Owner));

        $this->post(route('admin.menu.groups.store', $item), ['name' => 'Ukuran', 'required' => 1]);
        $group = $item->modifierGroups()->firstOrFail();
        $this->assertTrue($group->required);

        $this->post(route('admin.menu.modifiers.store', $group), ['name' => 'Besar', 'price_delta' => 5000]);
        $modifier = $group->modifiers()->firstOrFail();
        $this->put(route('admin.menu.modifiers.update', $modifier), ['name' => 'Jumbo', 'price_delta' => 7000]);
        $this->assertSame(7000, $modifier->refresh()->price_delta);

        $this->delete(route('admin.menu.groups.destroy', $group));
        $this->assertDatabaseMissing('modifiers', ['id' => $modifier->id]);
    }

    public function test_bundle_components_are_saved_and_snapshotted_on_the_order_without_changing_the_price(): void
    {
        $rice = $this->item(['name' => 'Nasi', 'price' => 8000]);
        $tea = $this->item(['name' => 'Es Teh', 'price' => 5000]);

        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.items.store'), $this->itemPayload([
            'name' => 'Paket Hemat', 'price' => 11000, 'is_bundle' => 1,
            'components' => [['item_id' => $rice->id, 'qty' => 2], ['item_id' => $tea->id, 'qty' => 1]],
        ]))->assertSessionHasNoErrors();
        $bundle = Item::query()->where('name', 'Paket Hemat')->firstOrFail();
        $this->assertCount(2, $bundle->bundleComponents);

        $table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);
        $order = app(OrderService::class)->create($table->load('branch'), [['item_id' => $bundle->id, 'qty' => 1]], [], 'qris', '127.0.0.1');

        $line = $order->items()->with('modifiers')->firstOrFail();
        $this->assertSame(11000, $line->unit_price);
        $this->assertEqualsCanonicalizing(['Isi: 2× Nasi', 'Isi: 1× Es Teh'], $line->modifiers->pluck('name')->all());
    }

    public function test_a_bundle_cannot_contain_another_bundle_or_itself(): void
    {
        $inner = $this->item(['name' => 'Paket A', 'is_bundle' => true]);
        $this->actingAs($this->user(Role::Owner))->post(route('admin.menu.items.store'), $this->itemPayload([
            'name' => 'Paket B', 'is_bundle' => 1, 'components' => [['item_id' => $inner->id, 'qty' => 1]],
        ]))->assertSessionHasErrors('menu');
        $this->assertDatabaseMissing('items', ['name' => 'Paket B']);
    }

    public function test_order_prices_stay_snapshotted_when_the_menu_changes_later(): void
    {
        $item = $this->item(['price' => 10000]);
        $table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);
        $order = app(OrderService::class)->create($table->load('branch'), [['item_id' => $item->id, 'qty' => 1]], [], 'qris', '127.0.0.1');

        $this->actingAs($this->user(Role::Owner))->put(route('admin.menu.items.update', $item), $this->itemPayload(['name' => 'Teh Baru', 'price' => 99000]));

        $line = $order->refresh()->items()->firstOrFail();
        $this->assertSame('Teh', $line->name);
        $this->assertSame(10000, $line->unit_price);
    }

    public function test_items_and_categories_with_history_are_deactivated_not_deleted(): void
    {
        $used = $this->item(['name' => 'Pernah dipesan']);
        $table = DiningTable::create(['branch_id' => $this->branch->id, 'label' => 'A1', 'token' => DiningTable::newToken()]);
        app(OrderService::class)->create($table->load('branch'), [['item_id' => $used->id, 'qty' => 1]], [], 'qris', '127.0.0.1');
        $unused = $this->item(['name' => 'Belum dipesan']);

        $this->actingAs($this->user(Role::Owner));
        $this->delete(route('admin.menu.items.destroy', $used));
        $this->delete(route('admin.menu.items.destroy', $unused));
        $this->assertFalse($used->refresh()->is_active);
        $this->assertDatabaseMissing('items', ['id' => $unused->id]);

        $this->delete(route('admin.menu.categories.destroy', $this->drinks));
        $this->assertFalse($this->drinks->refresh()->is_active);
        $this->assertSame([], $this->menuNames($this->branch));
    }

    public function test_item_form_and_index_render_for_the_owner(): void
    {
        $item = $this->item();
        $this->actingAs($this->user(Role::Owner));

        $this->get(route('admin.menu.items.create'))->assertOk()->assertSee('Menu baru');
        $this->get(route('admin.menu.items.edit', $item))->assertOk()->assertSee('Pilihan modifier');
        foreach (['items', 'branch', 'categories', 'bulk', 'csv'] as $tab) {
            $this->get(route('admin.menu', ['tab' => $tab, 'branch' => $this->branch->id]))->assertOk();
        }
    }
}

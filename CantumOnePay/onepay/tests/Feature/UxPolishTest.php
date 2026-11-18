<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class UxPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'password' => 'rahasia123']);
        $user->branches()->attach(Branch::query()->firstOrFail());
        return $user;
    }

    private function menuUrl(): string
    {
        return route('menu.show', DiningTable::query()->where('label', 'A1')->firstOrFail()->token);
    }

    /* ---- role mendarat di halaman yang boleh dibuka ---- */

    public function test_each_role_lands_on_a_page_it_may_open(): void
    {
        $expected = [
            Role::Owner->value => 'staff.dashboard', Role::Manager->value => 'staff.dashboard', Role::Cashier->value => 'staff.dashboard',
            Role::Kitchen->value => 'staff.kitchen', Role::Warehouse->value => 'staff.warehouse',
        ];

        foreach (Role::cases() as $role) {
            $user = $this->staff($role);
            $this->assertSame($expected[$role->value], $role->landingRoute());

            $this->actingAs($user)->get(route('staff.dashboard', [], false))->tap(function ($response) use ($role) {
                $role->landingRoute() === 'staff.dashboard' ? $response->assertOk() : $response->assertRedirect(route($role->landingRoute()));
            });
            $this->actingAs($user)->get(route($role->landingRoute(), [], false))->assertOk();
        }
    }

    public function test_login_redirects_each_role_to_its_landing_page(): void
    {
        foreach ([Role::Kitchen, Role::Warehouse, Role::Cashier] as $role) {
            $user = $this->staff($role);
            $this->post(route('staff.login'), ['email' => $user->email, 'password' => 'rahasia123'])
                ->assertRedirect(route($role->landingRoute()));
            auth()->logout();
        }
    }

    public function test_warehouse_page_is_only_for_warehouse_and_has_a_logout(): void
    {
        $this->actingAs($this->staff(Role::Warehouse))->get(route('staff.warehouse', [], false))
            ->assertOk()->assertSee('Modul stok akan tersedia di pembaruan berikutnya.')->assertSee('Keluar');

        $this->actingAs($this->staff(Role::Kitchen))->get(route('staff.warehouse', [], false))->assertForbidden();
    }

    public function test_warehouse_user_sees_only_its_own_menu_entry(): void
    {
        $html = $this->actingAs($this->staff(Role::Warehouse))->get(route('staff.warehouse', [], false))->getContent();

        $this->assertStringContainsString('Gudang', $html);
        $this->assertStringNotContainsString('Meja dan denah', $html);
        $this->assertStringNotContainsString('Pengaturan', $html);
    }

    public function test_menu_admin_pages_and_csv_export_are_closed_to_roles_that_cannot_manage_the_menu(): void
    {
        foreach ([Role::Cashier, Role::Kitchen, Role::Warehouse] as $role) {
            $user = $this->staff($role);
            foreach (['admin.menu', 'admin.menu.export', 'admin.menu.items.create'] as $name) {
                $this->actingAs($user)->get(route($name, [], false))->assertForbidden();
            }
        }
        $this->actingAs($this->staff(Role::Manager))->get(route('admin.menu', [], false))->assertOk();
    }

    public function test_warehouse_cannot_read_the_order_snapshot(): void
    {
        $branch = Branch::query()->firstOrFail();

        $this->actingAs($this->staff(Role::Warehouse))->getJson(route('staff.api.snapshot', ['branch' => $branch->id], false))->assertForbidden();
        $this->actingAs($this->staff(Role::Kitchen))->getJson(route('staff.api.snapshot', ['branch' => $branch->id], false))->assertOk();
    }

    /* ---- halaman error ---- */

    public function test_error_pages_are_indonesian_and_use_no_inline_handlers(): void
    {
        $messages = [
            403 => 'Kamu tidak punya akses ke halaman ini.', 419 => 'Sesi habis, muat ulang halaman.',
            429 => 'Terlalu banyak permintaan.', 500 => 'Terjadi kesalahan di server.', 503 => 'Sedang dalam pemeliharaan.',
        ];
        foreach ($messages as $code => $headline) {
            $html = view("errors.$code")->render();
            $this->assertStringContainsString($headline, $html, "halaman $code");
            $this->assertStringNotContainsString('onclick=', $html);
            $this->assertStringNotContainsString('Laravel', $html);
        }
    }

    public function test_unknown_page_and_invalid_qr_show_friendly_404s(): void
    {
        $this->get('/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan.');
        $this->get(route('menu.show', 'token-salah'))->assertNotFound()->assertSee('QR meja tidak valid.')->assertDontSee('Ke dashboard staf');
    }

    public function test_the_404_page_never_echoes_internal_exception_messages(): void
    {
        $html = view('errors.404', ['exception' => new NotFoundHttpException('No query results for model [App\Models\Order]')])->render();

        $this->assertStringContainsString('Halaman tidak ditemukan.', $html);
        $this->assertStringNotContainsString('No query results', $html);
    }

    public function test_forbidden_staff_page_renders_the_403_view(): void
    {
        $this->actingAs($this->staff(Role::Kitchen))->get(route('admin.menu', [], false))
            ->assertForbidden()->assertSee('Kamu tidak punya akses ke halaman ini.');
    }

    /* ---- identitas brand di halaman pelanggan ---- */

    public function test_customer_page_uses_the_brand_accent_tagline_and_name(): void
    {
        Brand::query()->update(['accent_color' => '#123456', 'tagline' => 'Kopi enak, harga jujur']);

        $this->get($this->menuUrl())->assertOk()
            ->assertSee('--accent:#123456', false)->assertSee('Kopi enak, harga jujur')->assertSee('Kopi Contoh');
    }

    public function test_an_unsafe_accent_value_falls_back_to_the_default(): void
    {
        $brand = Brand::current();
        $brand->accent_color = '#000;}</style><script>alert(1)</script>';

        $this->assertSame(Brand::DEFAULT_ACCENT, $brand->accent());

        Brand::query()->update(['accent_color' => 'red']);
        $this->get($this->menuUrl())->assertSee('--accent:' . Brand::DEFAULT_ACCENT, false)->assertDontSee('<script>alert', false);
    }

    public function test_text_on_a_light_accent_is_dark_and_on_a_dark_accent_is_white(): void
    {
        $brand = Brand::current();
        $brand->accent_color = '#ffe14d';
        $this->assertSame('#0a1226', $brand->onAccent());

        $brand->accent_color = '#0a1f4d';
        $this->assertSame('#ffffff', $brand->onAccent());
    }

    public function test_customer_page_shows_hours_prep_estimate_and_open_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00')); // Selasa
        $hours = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => '08:00', 'close' => '22:00']);
        Branch::query()->update(['open_hours' => $hours, 'prep_minutes' => 12]);

        $this->get($this->menuUrl())->assertOk()->assertSee('Buka')->assertSee('08:00–22:00')->assertSee('Estimasi penyajian ±12 mnt');

        Carbon::setTestNow();
    }

    public function test_closed_branch_explains_why_orders_cannot_be_paid(): void
    {
        Branch::query()->update(['is_open' => false]);

        $response = $this->get($this->menuUrl())->assertOk();
        $response->assertSee('Cabang sedang tutup.')->assertSee('pesanan belum bisa dibayar');
        $response->assertSee('"closedMessage":"Cabang sedang tutup."', false);
        $response->assertSee('"isOpen":false', false);
    }

    public function test_empty_menu_shows_an_empty_state(): void
    {
        Item::query()->update(['is_active' => false]);

        $this->get($this->menuUrl())->assertOk()->assertSee('Menu belum tersedia.');
    }

    /* ---- chip aturan khusus cabang di dashboard ---- */

    public function test_snapshot_lists_branch_rules_that_differ_from_the_brand(): void
    {
        Brand::query()->update(['service_percent' => 5, 'tax_percent' => 11]);
        $branch = Branch::query()->firstOrFail();
        $owner = $this->staff(Role::Owner);

        $this->actingAs($owner)->getJson(route('staff.api.snapshot', ['branch' => $branch->id]))->assertOk()->assertJson(['rules' => []]);

        $branch->update(['service_percent' => 0, 'tax_percent' => 10.5, 'enabled_methods' => 'qris']);

        $rules = $this->actingAs($owner)->getJson(route('staff.api.snapshot', ['branch' => $branch->id]))->json('rules');
        $this->assertContains('Service 0%', $rules);
        $this->assertContains('Pajak 10,5%', $rules);
        $this->assertContains('Bayar: QRIS', $rules);
    }

    /* ---- tidak ada dialog bawaan browser ---- */

    public function test_no_script_or_view_uses_browser_dialogs_or_inline_handlers(): void
    {
        $offenders = [];
        foreach (glob(public_path('assets/*.js')) as $file) {
            if (preg_match('/(?<![.\w])(alert|prompt)\s*\(|window\.(confirm|alert|prompt)\s*\(|(?<![.\w])confirm\s*\(/', file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if ($file->isFile() && preg_match('/\son(click|submit|change)=/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Dialog browser atau handler inline masih dipakai.');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\CancellationRequest;
use App\Models\Discount;
use App\Models\DiningTable;
use App\Models\FloorElement;
use App\Models\Item;
use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Models\Refund;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pemeriksaan keamanan: header, sesi dan akun, login, webhook, upload, XSS, CSV, serta akses antar cabang dan role.
 * Dua cabang: pengguna cabang 1 tidak boleh menyentuh apa pun milik cabang 2.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia12345';
    private const SECRET = 'test-secret';
    /** PNG 1x1 yang valid (tanpa butuh ekstensi GD). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Branch $branch1;
    private Branch $branch2;
    private Order $order2;
    private DiningTable $table2;
    private FloorElement $element2;
    private Refund $refund2;
    private CancellationRequest $cancellation2;
    private Discount $discount2;
    private User $manager1;
    private User $cashier1;
    private User $kitchen1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['onepay.kasera.webhook_secret' => self::SECRET, 'onepay.kasera.api_key' => 'kp_test_x']);
        $this->seed(DatabaseSeeder::class);

        $this->branch1 = Branch::query()->orderBy('id')->firstOrFail();
        $this->branch2 = Branch::create(['name' => 'Cabang B']);
        $this->manager1 = $this->staff(Role::Manager, $this->branch1);
        $this->cashier1 = $this->staff(Role::Cashier, $this->branch1);
        $this->kitchen1 = $this->staff(Role::Kitchen, $this->branch1);
        $this->seedBranch2();
    }

    private function staff(Role $role, ?Branch $branch = null, string $email = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'password' => self::PASSWORD] + ($email ? ['email' => $email] : []));
        $branch && $user->branches()->attach($branch);
        return $user;
    }

    private function owner(): User
    {
        return User::query()->where('role', Role::Owner)->firstOrFail();
    }

    private function seedBranch2(): void
    {
        $area = $this->branch2->defaultArea();
        $this->table2 = DiningTable::create(['branch_id' => $this->branch2->id, 'floor_area_id' => $area->id, 'label' => 'B1', 'token' => DiningTable::newToken()]);
        $this->element2 = FloorElement::create(['floor_area_id' => $area->id, 'type' => 'bar', 'pos_x' => 10, 'pos_y' => 10]);

        $this->order2 = Order::create([
            'code' => 'OPTESTB2', 'branch_id' => $this->branch2->id, 'dining_table_id' => $this->table2->id, 'table_label' => 'B1',
            'status' => OrderStatus::Paid, 'needs_review' => true, 'subtotal' => 50000, 'total' => 55500, 'expires_at' => now()->addHour(), 'paid_at' => now(),
        ]);
        $this->order2->items()->create(['name' => 'Nasi Goreng', 'unit_price' => 50000, 'qty' => 1, 'station' => 'kitchen']);
        $this->refund2 = Refund::create(['order_id' => $this->order2->id, 'amount' => 55500, 'reason' => 'uji', 'status' => 'pending']);
        $this->cancellation2 = CancellationRequest::create(['order_id' => $this->order2->id, 'requested_by' => $this->cashier1->id, 'reason' => 'uji']);

        $this->discount2 = Discount::create(['name' => 'Promo B', 'type' => 'fixed', 'value' => 1000, 'scope' => 'order', 'all_branches' => false]);
        $this->discount2->branches()->attach($this->branch2);
    }

    // =========================================================================================== header keamanan

    public function test_every_page_carries_security_headers_and_a_csp_with_nonce(): void
    {
        $response = $this->get(route('login'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('Permissions-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('https://localhost/staff/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_inline_scripts_carry_the_request_nonce(): void
    {
        $response = $this->get(route('menu.show', DiningTable::query()->firstOrFail()->token));

        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
        $this->assertNotEmpty($match[1] ?? null);
        $response->assertSee('<script nonce="' . $match[1] . '">', false);
        $this->assertNotSame($match[1], $this->nonceOf($this->get(route('login'))), 'Nonce harus baru di setiap request.');
    }

    private function nonceOf(TestResponse $response): string
    {
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
        return $match[1] ?? '';
    }

    public function test_unknown_pages_also_carry_security_headers(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Content-Security-Policy');
    }

    public function test_production_serves_only_the_configured_host(): void
    {
        config(['app.url' => 'https://resto.test']);
        $this->app['env'] = 'production';

        try {
            $this->call('GET', 'https://jahat.test/staff/login')->assertStatus(400);
            $this->call('GET', 'https://resto.test/staff/login')->assertOk();
        } finally {
            \Illuminate\Http\Request::setTrustedHosts([]);
        }
    }

    public function test_views_have_no_inline_event_handlers_or_nonce_less_inline_scripts(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|input|load|error|focus|blur|keyup|keydown|mouse\w+)\s*=/i', $source, $file->getPathname() . ' memakai handler inline (diblokir CSP).');
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(src|nonce)=)[^>]*>/i', $source, $file->getPathname() . ' memakai <script> inline tanpa nonce.');
            $this->assertDoesNotMatchRegularExpression('/javascript:/i', $source, $file->getPathname() . ' memakai URL javascript:.');
        }
    }

    // =========================================================================================== sesi dan akun

    public function test_every_staff_and_admin_route_rejects_guests(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!preg_match('#^(admin|staff)(/|$)#', $uri) || in_array($uri, ['staff/login'], true)) {
                continue;
            }
            $method = $route->methods()[0];
            $path = preg_replace('/\{[^}]+\}/', '1', $uri);
            $status = $this->call($method, '/' . $path, [], [], [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode();
            $this->assertContains($status, [401, 302], "$method /$uri dibuka tanpa login (status $status).");
            $checked++;
        }
        $this->assertGreaterThan(40, $checked);
    }

    public function test_every_state_changing_route_is_csrf_protected_except_the_webhook(): void
    {
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class); // menyinkronkan grup middleware ke router
        $router = app('router');
        foreach (Route::getRoutes() as $route) {
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
                continue;
            }
            $protected = in_array(ValidateCsrfToken::class, $router->resolveMiddleware($router->gatherRouteMiddleware($route)), true);
            $this->assertTrue($protected, $route->uri() . ' tidak dilindungi CSRF.');
        }

        $this->assertContains('kasera-webhook', (new \ReflectionClass(ValidateCsrfToken::class))->getStaticPropertyValue('neverVerify'));
    }

    /** Di produksi tiap request memuat pengguna dari DB; dalam tes satu proses, jadi cache guard dibuang. */
    private function forgetResolvedUser(): void
    {
        app('auth')->forgetGuards();
    }

    public function test_deactivated_account_loses_access_on_the_very_next_request(): void
    {
        $user = $this->staff(Role::Manager, $this->branch1, 'dinon@contoh.test');
        $this->post(route('staff.login'), ['email' => 'dinon@contoh.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->getJson(route('staff.api.snapshot', ['branch' => $this->branch1->id]))->assertOk();

        $user->update(['is_active' => false]);
        $this->forgetResolvedUser();

        $this->getJson(route('staff.api.snapshot', ['branch' => $this->branch1->id]))->assertUnauthorized();
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }

    public function test_password_change_ends_existing_sessions(): void
    {
        $user = $this->staff(Role::Manager, $this->branch1, 'ganti@contoh.test');
        $this->post(route('staff.login'), ['email' => 'ganti@contoh.test', 'password' => self::PASSWORD]);
        $this->getJson(route('staff.api.snapshot', ['branch' => $this->branch1->id]))->assertOk();

        $user->update(['password' => 'KataSandiBaru99']);
        $this->forgetResolvedUser();

        $this->getJson(route('staff.api.snapshot', ['branch' => $this->branch1->id]))->assertUnauthorized();
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $this->staff(Role::Cashier, $this->branch1, 'sesi@contoh.test');
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('staff.login'), ['email' => 'sesi@contoh.test', 'password' => self::PASSWORD]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_session_cookie_is_httponly_and_samesite(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertContains(strtolower((string) config('session.same_site')), ['lax', 'strict']);
    }

    // =========================================================================================== login

    public function test_login_gives_the_same_answer_for_unknown_email_and_wrong_password(): void
    {
        $this->staff(Role::Cashier, $this->branch1, 'ada@contoh.test');

        $wrong = $this->from(route('login'))->post(route('staff.login'), ['email' => 'ada@contoh.test', 'password' => 'salah-salah']);
        $unknown = $this->from(route('login'))->post(route('staff.login'), ['email' => 'tidakada@contoh.test', 'password' => 'salah-salah']);

        $this->assertSame(session('errors')?->first('email') ?? null, 'Email atau kata sandi salah.');
        $wrong->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        $unknown->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }

    public function test_login_is_blocked_after_five_failures_even_with_the_right_password(): void
    {
        $this->staff(Role::Cashier, $this->branch1, 'blok@contoh.test');
        foreach (range(1, 5) as $_) {
            $this->post(route('staff.login'), ['email' => 'blok@contoh.test', 'password' => 'salah-salah']);
        }

        $this->post(route('staff.login'), ['email' => 'blok@contoh.test', 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.']);
        $this->assertGuest();
    }

    public function test_weak_passwords_are_rejected_when_creating_or_resetting_users(): void
    {
        $owner = $this->owner();
        $base = ['name' => 'Baru', 'email' => 'baru@contoh.test', 'role' => 'cashier', 'branch_ids' => [$this->branch1->id]];

        foreach (['pendek1', 'hurufsajahurufsaja', '12345678901'] as $weak) {
            $this->actingAs($owner)->post(route('admin.users.store'), $base + ['password' => $weak])->assertSessionHasErrors('password');
        }
        $this->assertDatabaseMissing('users', ['email' => 'baru@contoh.test']);

        $this->actingAs($owner)->post(route('admin.users.store'), $base + ['password' => 'KuatSekali123'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('users', ['email' => 'baru@contoh.test']);
    }

    // =========================================================================================== pelanggan dan pesanan

    public function test_customer_cannot_choose_status_total_or_price(): void
    {
        Http::fake(['*' => Http::response(['message' => 'x'], 401)]);
        $table = DiningTable::query()->where('branch_id', $this->branch1->id)->firstOrFail();
        $this->get(route('menu.show', $table->token));

        $response = $this->postJson(route('order.store'), [
            'token' => $table->token, 'method' => 'qris', 'cart' => [['item_id' => 3, 'qty' => 1, 'unit_price' => 1, 'price' => 1]],
            'status' => 'paid', 'total' => 1, 'paid_at' => now()->toDateTimeString(), 'branch_id' => $this->branch2->id,
        ])->assertOk();

        $order = Order::query()->where('code', $response->json('code'))->firstOrFail();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame($this->branch1->id, $order->branch_id);
        $this->assertGreaterThan(30000, $order->total);
    }

    public function test_public_endpoints_are_rate_limited(): void
    {
        foreach (range(1, 20) as $_) {
            $this->postJson(route('order.store'), ['token' => 'tidak-ada'])->assertNotFound();
        }
        $this->postJson(route('order.store'), ['token' => 'tidak-ada'])->assertStatus(429);

        foreach (range(1, 30) as $_) {
            $this->postJson(route('promo.check'), ['token' => 'tidak-ada']);
        }
        $this->postJson(route('promo.check'), ['token' => 'tidak-ada'])->assertStatus(429);
    }

    public function test_menu_content_is_escaped_in_html_and_in_embedded_json(): void
    {
        Item::query()->whereKey(3)->update(['name' => '</script><script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>']);
        $token = DiningTable::query()->where('branch_id', $this->branch1->id)->firstOrFail()->token;

        $html = $this->get(route('menu.show', $token))->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
    }

    public function test_order_status_page_never_shows_the_customer_phone(): void
    {
        $this->order2->update(['customer_phone' => '+628123456789', 'customer_name' => 'Rahasia']);
        $this->order2->payment()->create(['method' => 'qris', 'amount' => 55500]);

        $this->get(route('order.status', $this->order2))->assertDontSee('628123456789');
        $this->get(route('pay.show', $this->order2))->assertDontSee('628123456789');
    }

    // =========================================================================================== webhook

    private function signed(string $body, int $timestamp, string $secret = self::SECRET): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_KASERA_SIGNATURE_V1' => "t=$timestamp,v1=" . hash_hmac('sha256', "$timestamp.$body", $secret)];
    }

    public function test_webhook_with_an_old_timestamp_is_a_rejected_replay(): void
    {
        $order = Order::create([
            'code' => 'OPREPLAY1', 'branch_id' => $this->branch1->id, 'dining_table_id' => DiningTable::query()->firstOrFail()->id, 'table_label' => 'A1',
            'status' => OrderStatus::PendingPayment, 'subtotal' => 20000, 'total' => 22200, 'expires_at' => now()->addHour(),
        ]);
        $body = json_encode(['event' => 'payment.paid', 'data' => ['id' => 'tx', 'external_id' => $order->code]]);

        $this->call('POST', route('kasera.webhook'), [], [], [], $this->signed($body, time() - 3600), $body)->assertStatus(401);

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_webhook_body_is_size_limited_and_forged_requests_store_only_a_fragment(): void
    {
        $this->call('POST', route('kasera.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 300000))->assertStatus(413);
        $this->assertSame(0, PaymentWebhook::query()->count());

        $this->call('POST', route('kasera.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_KASERA_SIGNATURE_V1' => 't=' . time() . ',v1=bad'], str_repeat('b', 100000))->assertStatus(401);
        $stored = PaymentWebhook::query()->firstOrFail();
        $this->assertFalse($stored->signature_valid);
        $this->assertLessThanOrEqual(500, strlen($stored->body));
    }

    // =========================================================================================== upload, CSV

    /** Berkas sungguhan di disk, supaya jenis berkas dideteksi dari ISI-nya seperti di produksi, bukan dari nama. */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $dir = storage_path('framework/testing');
        is_dir($dir) || mkdir($dir, 0775, true);
        $path = $dir . DIRECTORY_SEPARATOR . 'up' . bin2hex(random_bytes(4));
        file_put_contents($path, $content);
        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_menu_photo_rejects_svg_and_disguised_scripts_and_stores_a_random_name(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $fields = ['_method' => 'PUT', 'category_id' => 1, 'name' => 'Foto', 'price' => 10000, 'station' => 'kitchen'];

        $bad = [
            'x.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            'shell.php.jpg' => '<?php echo 1; ?>',
            'foto.png' => '<html><script>alert(1)</script></html>',
        ];
        foreach ($bad as $name => $content) {
            $this->actingAs($owner)->post(route('admin.menu.items.update', 3), $fields + ['photo' => $this->realUpload($name, $content)])
                ->assertSessionHasErrors('photo');
        }
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($owner)->post(route('admin.menu.items.update', 3), $fields + ['photo' => $this->realUpload('evil name.png', base64_decode(self::PNG))])
            ->assertSessionHasNoErrors();

        $stored = Storage::disk('public')->allFiles();
        $this->assertCount(1, $stored);
        $this->assertMatchesRegularExpression('#^menu/[A-Za-z0-9]{40}\.png$#', $stored[0]);
    }

    public function test_csv_exports_neutralize_spreadsheet_formulas(): void
    {
        Item::query()->whereKey(3)->update(['name' => '=HYPERLINK("http://jahat.test","klik")', 'description' => "\t@SUM(1+1)"]);

        foreach (['admin.menu.export', 'admin.settings.export.menu'] as $route) {
            $csv = $this->actingAs($this->owner())->get(route($route))->streamedContent();
            $this->assertStringContainsString("'=HYPERLINK", $csv);
            $this->assertStringNotContainsString(',=HYPERLINK', $csv);
            $this->assertStringNotContainsString(",\"=HYPERLINK", $csv);
        }
    }

    // =========================================================================================== peran

    public function test_roles_cannot_open_pages_above_their_level(): void
    {
        $adminPages = ['admin.tables', 'admin.menu', 'admin.discounts', 'admin.reports'];
        foreach ([$this->cashier1, $this->kitchen1, $this->staff(Role::Warehouse, $this->branch1)] as $user) {
            foreach ($adminPages as $page) {
                $this->actingAs($user)->get(route($page))->assertForbidden();
            }
        }

        $ownerOnly = ['admin.users', 'admin.settings', 'admin.settings.audit', 'admin.settings.webhooks', 'admin.settings.export.menu', 'admin.settings.export.orders', 'staff.overview'];
        foreach ($ownerOnly as $page) {
            $this->actingAs($this->manager1)->get(route($page))->assertForbidden();
        }
    }

    public function test_manager_cannot_change_owner_only_settings_or_create_users(): void
    {
        $this->actingAs($this->manager1);
        $this->put(route('admin.settings.brand'), ['name' => 'X', 'tax_percent' => 0])->assertForbidden();
        $this->put(route('admin.settings.gateway'), ['api_key' => 'kp_live_jahat'])->assertForbidden();
        $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@x.test', 'password' => 'KuatSekali123', 'role' => 'owner'])->assertForbidden();
        $this->post(route('admin.settings.branches.store'), ['name' => 'Cabang Gelap'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@x.test']);
    }

    public function test_cashier_cannot_cancel_but_can_only_request_for_its_own_branch(): void
    {
        $own = Order::create([
            'code' => 'OPKASIR01', 'branch_id' => $this->branch1->id, 'dining_table_id' => DiningTable::query()->firstOrFail()->id, 'table_label' => 'A1',
            'status' => OrderStatus::Paid, 'subtotal' => 20000, 'total' => 22200, 'expires_at' => now()->addHour(), 'paid_at' => now(),
        ]);

        $this->actingAs($this->cashier1)->postJson(route('staff.api.cancel', $own), ['reason' => 'x'])->assertStatus(422);
        $this->postJson(route('staff.cancellations.request', $own), ['reason' => 'pelanggan berubah pikiran'])->assertOk();
        $this->postJson(route('staff.cancellations.request', $this->order2), ['reason' => 'x'])->assertStatus(422);
    }

    // =========================================================================================== IDOR antar cabang

    /** Setiap endpoint yang menyentuh data cabang 2, dipanggil oleh pengguna cabang 1. */
    private function foreignRequests(): array
    {
        $b2 = $this->branch2->id;
        $t2 = $this->table2->id;
        $o2 = $this->order2->id;
        $item = $this->order2->items()->firstOrFail()->id;
        $json = ['HTTP_ACCEPT' => 'application/json'];

        return [
            'dashboard snapshot' => ['manager', 'GET', "/staff/api/snapshot?branch=$b2", []],
            'advance order' => ['manager', 'POST', "/staff/api/orders/$o2/advance", []],
            'cancel order' => ['manager', 'POST', "/staff/api/orders/$o2/cancel", ['reason' => 'uji']],
            'open or close branch' => ['manager', 'POST', "/staff/api/branches/$b2/open", ['value' => 0]],
            'toggle availability' => ['manager', 'POST', "/staff/api/branches/$b2/items/3/availability", ['available' => 0]],
            'kitchen snapshot' => ['kitchen', 'GET', "/staff/kitchen/snapshot?branch=$b2&station=kitchen", []],
            'kitchen prep' => ['kitchen', 'POST', "/staff/kitchen/items/$item/prep", ['status' => 'making']],
            'request cancellation' => ['cashier', 'POST', "/staff/orders/$o2/cancel-request", ['reason' => 'uji']],
            'decide cancellation' => ['manager', 'POST', "/staff/cancellations/{$this->cancellation2->id}/decide", ['approve' => 1, 'note' => 'ok']],
            'complete refund' => ['manager', 'POST', "/staff/refunds/{$this->refund2->id}/complete", ['note' => 'ref 1']],
            'resolve review' => ['manager', 'POST', "/staff/refunds/review/$o2/resolve", ['note' => 'ref 1']],
            'refund data' => ['manager', 'GET', "/staff/refunds/data?branch=$b2", []],
            'floor layout' => ['manager', 'GET', "/admin/tables/layout?branch=$b2", []],
            'create table' => ['manager', 'POST', "/admin/tables/branches/$b2/tables", ['label' => 'ZZ']],
            'bulk tables' => ['manager', 'POST', "/admin/tables/branches/$b2/tables/bulk", ['prefix' => 'Z', 'from' => 1, 'to' => 3]],
            'create element' => ['manager', 'POST', "/admin/tables/branches/$b2/elements", ['type' => 'bar']],
            'update table' => ['manager', 'PATCH', "/admin/tables/table/$t2", ['label' => 'DIRETAS', 'pos_x' => 5]],
            'delete table' => ['manager', 'DELETE', "/admin/tables/table/$t2", []],
            'duplicate table' => ['manager', 'POST', "/admin/tables/table/$t2/duplicate", []],
            'regenerate token' => ['manager', 'POST', "/admin/tables/table/$t2/token", []],
            'update element' => ['manager', 'PATCH', "/admin/tables/element/{$this->element2->id}", ['label' => 'DIRETAS']],
            'delete element' => ['manager', 'DELETE', "/admin/tables/element/{$this->element2->id}", []],
            'table qr' => ['manager', 'GET', "/admin/tables/table/$t2/qr.svg", []],
            'table sticker' => ['manager', 'GET', "/admin/tables/table/$t2/sticker.svg", []],
            'sticker sheet' => ['manager', 'GET', "/admin/tables/sheet?branch=$b2", []],
            'copy layout into foreign' => ['manager', 'POST', '/admin/tables/copy-layout', ['branch_id' => $b2, 'source_branch_id' => $this->branch1->id]],
            'copy layout from foreign' => ['manager', 'POST', '/admin/tables/copy-layout', ['branch_id' => $this->branch1->id, 'source_branch_id' => $b2]],
            'menu branch price' => ['manager', 'PUT', "/admin/menu/branches/$b2/items/3", ['is_available' => 0, 'price_override' => 1]],
            'menu copy into foreign' => ['manager', 'POST', "/admin/menu/branches/$b2/copy", ['source' => $this->branch1->id]],
            'menu copy from foreign' => ['manager', 'POST', "/admin/menu/branches/{$this->branch1->id}/copy", ['source' => $b2]],
            'edit foreign discount' => ['manager', 'GET', "/admin/discounts/{$this->discount2->id}/edit", []],
            'update foreign discount' => ['manager', 'PUT', "/admin/discounts/{$this->discount2->id}", ['name' => 'DIRETAS', 'type' => 'fixed', 'value' => 999, 'scope' => 'order', 'branch_ids' => [$b2]]],
            'toggle foreign discount' => ['manager', 'POST', "/admin/discounts/{$this->discount2->id}/toggle", []],
            'delete foreign discount' => ['manager', 'DELETE', "/admin/discounts/{$this->discount2->id}", []],
            'report foreign branch' => ['manager', 'GET', "/admin/reports?branch=$b2", []],
            'report csv foreign branch' => ['manager', 'GET', "/admin/reports/export.csv?branch=$b2", []],
            'daily close foreign branch' => ['manager', 'GET', "/admin/reports/close?branch=$b2", []],
        ];
    }

    private function fingerprint(): string
    {
        $tables = ['orders', 'order_items', 'branches', 'dining_tables', 'floor_elements', 'discounts', 'discount_branches', 'branch_items', 'refunds', 'cancellation_requests', 'audit_logs', 'order_status_logs'];
        return md5(json_encode(array_map(fn ($table) => DB::table($table)->orderBy(DB::raw('1'))->get()->all(), $tables)));
    }

    public function test_branch_one_staff_cannot_read_or_change_anything_in_branch_two(): void
    {
        $actors = ['manager' => $this->manager1, 'cashier' => $this->cashier1, 'kitchen' => $this->kitchen1];

        foreach ($this->foreignRequests() as $label => [$who, $method, $url, $data]) {
            $before = $this->fingerprint();
            $this->flushSession();
            $response = $this->actingAs($actors[$who])->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);

            $status = $response->getStatusCode();
            // Form biasa menjawab penolakan dengan redirect kembali membawa pesan galat; API menjawab 403/404/422.
            $denied = in_array($status, [403, 404, 422], true) || ($status === 302 && $response->getSession()?->has('errors'));
            $this->assertTrue($denied, "$label: seharusnya ditolak, status $status");
            $this->assertSame($before, $this->fingerprint(), "$label: data cabang lain berubah");
        }
    }

    public function test_the_same_requests_work_for_the_owner_proving_the_denials_are_about_access_only(): void
    {
        $owner = $this->owner();
        foreach (['dashboard snapshot', 'floor layout', 'table qr', 'sticker sheet', 'refund data', 'kitchen snapshot', 'report foreign branch', 'edit foreign discount'] as $label) {
            [, $method, $url, $data] = $this->foreignRequests()[$label];
            $status = $this->actingAs($owner)->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode();
            $this->assertSame(200, $status, "$label: owner seharusnya boleh (status $status)");
        }
    }

    public function test_manager_cannot_create_an_all_branch_promo_or_one_for_a_foreign_branch(): void
    {
        $promo = ['name' => 'Promo Liar', 'type' => 'fixed', 'value' => 5000, 'scope' => 'order'];

        $this->actingAs($this->manager1);
        $this->post(route('admin.discounts.store'), $promo + ['all_branches' => 1]);
        $this->post(route('admin.discounts.store'), $promo + ['branch_ids' => [$this->branch2->id]]);

        $this->assertDatabaseMissing('discounts', ['name' => 'Promo Liar']);
    }

    public function test_table_update_cannot_change_token_or_branch_by_mass_assignment(): void
    {
        $table = DiningTable::query()->where('branch_id', $this->branch1->id)->firstOrFail();
        $token = $table->token;

        $this->actingAs($this->manager1)->patchJson(route('admin.tables.update', $table), ['label' => 'AMAN', 'token' => 'x', 'branch_id' => $this->branch2->id])->assertOk();

        $table->refresh();
        $this->assertSame('AMAN', $table->label);
        $this->assertSame($token, $table->token);
        $this->assertSame($this->branch1->id, $table->branch_id);
    }

    public function test_availability_toggle_for_an_unknown_menu_item_is_a_404_not_a_server_error(): void
    {
        $this->actingAs($this->manager1)
            ->postJson(route('staff.api.availability', [$this->branch1, 99999]), ['available' => 0])->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\FloorArea;
use App\Models\User;
use App\Services\Operations\DatabaseBackup;
use App\Services\Operations\EnvWriter;
use App\Services\Operations\HealthCheck;
use App\Services\Operations\Heartbeat;
use App\Services\Operations\Prerequisites;
use App\Services\Operations\Provisioner;
use App\Services\Operations\SystemHealth;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private string $workdir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workdir = sys_get_temp_dir() . '/onepay-ops-' . bin2hex(random_bytes(4));
        mkdir($this->workdir, 0777, true);

        config([
            'onepay.backup.path' => $this->workdir . '/backups',
            'onepay.backup.use_mysqldump' => false,
            'onepay.heartbeat_path' => $this->workdir . '/heartbeat',
            'onepay.env_path' => $this->workdir . '/.env',
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workdir);
        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $entry) {
            if (in_array(basename($entry), ['.', '..'], true)) {
                continue;
            }
            is_dir($entry) ? $this->removeDirectory($entry) : unlink($entry);
        }
        @rmdir($directory);
    }

    private function check(string $key): HealthCheck
    {
        $found = collect(app(SystemHealth::class)->run())->firstWhere('key', $key);
        $this->assertNotNull($found, "Pemeriksaan $key tidak ada.");
        return $found;
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    // ---------------------------------------------------------------- backup

    public function test_backup_command_writes_a_compressed_dump_and_records_it(): void
    {
        Brand::create(['id' => 1, 'name' => 'Kopi Uji']);

        $this->artisan('onepay:backup')->assertSuccessful();

        $files = glob($this->workdir . '/backups/onepay-*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('Kopi Uji', gzdecode(file_get_contents($files[0])));
        $this->assertNotNull(Brand::query()->value('last_backup_at'));
        $this->assertSame([], glob($this->workdir . '/backups/*.sql'), 'Berkas .sql mentah harus dihapus.');
    }

    public function test_a_backup_can_be_restored_into_an_empty_database(): void
    {
        Brand::create(['id' => 1, 'name' => 'Kopi Pulih', 'tax_percent' => 11]);
        DB::table('branches')->insert(['name' => "Cabang O'Neil", 'is_open' => true, 'is_active' => true]);

        $path = app(DatabaseBackup::class)->run();

        $restored = new PDO('sqlite::memory:');
        $restored->exec(gzdecode(file_get_contents($path)));

        $this->assertSame('Kopi Pulih', $restored->query('SELECT name FROM brand WHERE id = 1')->fetchColumn());
        $this->assertSame("Cabang O'Neil", $restored->query('SELECT name FROM branches')->fetchColumn(), 'Tanda kutip harus lolos dengan benar.');
    }

    public function test_prune_removes_only_backups_older_than_the_limit(): void
    {
        $directory = $this->workdir . '/backups';
        mkdir($directory, 0777, true);
        touch($directory . '/onepay-old.sql.gz', time() - 20 * 86400);
        touch($directory . '/onepay-new.sql.gz', time() - 2 * 86400);
        touch($directory . '/catatan.txt', time() - 90 * 86400);

        $removed = app(DatabaseBackup::class)->prune(14);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($directory . '/onepay-old.sql.gz');
        $this->assertFileExists($directory . '/onepay-new.sql.gz');
        $this->assertFileExists($directory . '/catatan.txt', 'Berkas selain backup tidak boleh disentuh.');
    }

    // ---------------------------------------------------------------- env, prasyarat, data awal

    public function test_env_writer_keeps_comments_activates_commented_keys_and_quotes_values(): void
    {
        $path = $this->workdir . '/.env.test';
        file_put_contents($path, "# komentar\nAPP_ENV=local\n# DB_HOST=127.0.0.1\nAPP_KEY=\n");

        $env = new EnvWriter($path);
        $env->set(['APP_ENV' => 'production', 'DB_HOST' => 'db.example', 'DB_PASSWORD' => 'rah asia#1$x', 'NEW_KEY' => 'baru']);

        $content = file_get_contents($path);
        $this->assertStringContainsString('# komentar', $content);
        $this->assertStringContainsString("APP_ENV=production\n", $content);
        $this->assertStringContainsString("DB_HOST=db.example\n", $content);
        $this->assertStringContainsString("APP_KEY=\n", $content);
        $this->assertSame('rah asia#1$x', $env->get('DB_PASSWORD'), 'Nilai berspasi dan karakter khusus harus bisa dibaca kembali.');
        $this->assertSame('baru', $env->get('NEW_KEY'));
    }

    public function test_env_writer_starts_from_the_template_when_there_is_no_env(): void
    {
        $template = $this->workdir . '/.env.example';
        file_put_contents($template, "APP_NAME=Template\nAPP_URL=http://localhost\n");

        $env = new EnvWriter($this->workdir . '/.env.baru', $template);
        $this->assertFalse($env->exists());
        $env->set(['APP_URL' => 'https://resto.test']);

        $this->assertSame('Template', $env->get('APP_NAME'));
        $this->assertSame('https://resto.test', $env->get('APP_URL'));
    }

    public function test_prerequisites_report_folders_that_are_missing(): void
    {
        $problems = (new Prerequisites($this->workdir))->problems();

        $this->assertCount(4, array_filter($problems, fn (string $p) => str_starts_with($p, 'Folder ')));
    }

    public function test_provisioner_is_idempotent_and_never_overwrites_existing_data(): void
    {
        $provisioner = app(Provisioner::class);

        $first = $provisioner->run('Resto A', 'Cabang A', 'Budi', 'budi@resto.test', 'rahasia-1');
        $second = $provisioner->run('Resto B', 'Cabang B', 'Sari', 'sari@resto.test', 'rahasia-2');

        $this->assertSame(['brand', 'branch', 'owner'], $first);
        $this->assertSame([], $second);
        $this->assertSame('Resto A', Brand::current()->name);
        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, FloorArea::query()->count());
        $this->assertSame(1, User::query()->where('role', Role::Owner)->count());
        $this->assertSame('budi@resto.test', User::query()->value('email'));
    }

    public function test_provision_command_requires_email_and_password(): void
    {
        $this->artisan('onepay:provision')->assertFailed();
    }

    // ---------------------------------------------------------------- kesehatan sistem

    public function test_debug_requested_in_env_fails_in_production_even_if_the_app_forces_it_off(): void
    {
        $this->production();
        file_put_contents($this->workdir . '/.env', "APP_DEBUG=true\n");
        $this->assertSame(HealthCheck::FAIL, $this->check('debug')->status);

        file_put_contents($this->workdir . '/.env', "APP_DEBUG=false\n");
        $this->assertSame(HealthCheck::OK, $this->check('debug')->status);
    }

    public function test_debug_is_only_a_warning_outside_production(): void
    {
        file_put_contents($this->workdir . '/.env', "APP_DEBUG=true\n");

        $this->assertSame(HealthCheck::WARN, $this->check('debug')->status);
    }

    public function test_missing_app_key_and_plain_http_url_fail_in_production(): void
    {
        $this->production();
        config(['app.key' => '', 'app.url' => 'http://resto.test']);

        $this->assertSame(HealthCheck::FAIL, $this->check('app_key')->status);
        $this->assertSame(HealthCheck::FAIL, $this->check('app_url')->status);

        config(['app.key' => 'base64:abc', 'app.url' => 'https://resto.test']);
        $this->assertSame(HealthCheck::OK, $this->check('app_url')->status);
    }

    public function test_scheduler_fails_without_a_recent_heartbeat_and_passes_after_one(): void
    {
        $this->production();
        $this->assertSame(HealthCheck::FAIL, $this->check('scheduler')->status);

        app(Heartbeat::class)->beat();
        $this->assertSame(HealthCheck::OK, $this->check('scheduler')->status);

        file_put_contents($this->workdir . '/heartbeat', (string) (time() - 600));
        $this->assertSame(HealthCheck::FAIL, $this->check('scheduler')->status, 'Heartbeat 10 menit lalu berarti cron berhenti.');
    }

    public function test_scheduler_heartbeat_is_registered_to_run_every_minute(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $heartbeat = $events->first(fn ($event) => $event->description === 'onepay-heartbeat');
        $this->assertNotNull($heartbeat);
        $this->assertSame('* * * * *', $heartbeat->expression);
        $this->assertTrue($events->contains(fn ($event) => str_contains((string) $event->command, 'onepay:backup')));
    }

    public function test_kasera_mode_is_checked_against_the_environment(): void
    {
        config(['onepay.kasera.api_key' => 'kp_test_abcdef123456', 'onepay.kasera.webhook_secret' => 'secret-nyata']);

        $this->assertSame(HealthCheck::OK, $this->check('kasera_key')->status, 'Kunci tes di lokal wajar.');

        $this->production();
        $this->assertSame(HealthCheck::WARN, $this->check('kasera_key')->status, 'Produksi dengan kunci tes harus diperingatkan.');

        config(['onepay.kasera.api_key' => 'kp_live_abcdef123456']);
        $this->assertSame(HealthCheck::OK, $this->check('kasera_key')->status);
    }

    public function test_kasera_placeholder_secret_and_key_fail(): void
    {
        config(['onepay.kasera.api_key' => 'kp_test_ISI_DISINI', 'onepay.kasera.webhook_secret' => 'ISI_DISINI']);

        $this->assertSame(HealthCheck::FAIL, $this->check('kasera_key')->status);
        $this->assertSame(HealthCheck::FAIL, $this->check('kasera_secret')->status);
    }

    public function test_the_kasera_key_never_appears_in_full_in_any_check(): void
    {
        config(['onepay.kasera.api_key' => 'kp_live_RAHASIA1234567890', 'onepay.kasera.webhook_secret' => 'whsec_RAHASIA']);

        $report = json_encode(array_map(fn (HealthCheck $c) => $c->toArray(), app(SystemHealth::class)->run()));

        $this->assertStringNotContainsString('RAHASIA1234567890', $report);
        $this->assertStringNotContainsString('whsec_RAHASIA', $report);
    }

    public function test_backup_freshness_is_reported(): void
    {
        Brand::create(['id' => 1, 'name' => 'Uji']);
        $this->assertSame(HealthCheck::WARN, $this->check('backup')->status, 'Belum pernah backup.');

        Brand::query()->update(['last_backup_at' => now()->subHours(3)]);
        $this->assertSame(HealthCheck::OK, $this->check('backup')->status);

        Brand::query()->update(['last_backup_at' => now()->subDays(3)]);
        $this->assertSame(HealthCheck::WARN, $this->check('backup')->status);
    }

    public function test_overdue_pending_orders_are_flagged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->production();
        $this->assertSame(HealthCheck::OK, $this->check('expiry')->status);

        DB::table('orders')->insert([
            'code' => 'OPTES0001', 'branch_id' => 1, 'dining_table_id' => 1, 'table_label' => 'A1', 'status' => 'pending_payment',
            'subtotal' => 10000, 'total' => 11000, 'expires_at' => now()->subMinutes(30), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(HealthCheck::FAIL, $this->check('expiry')->status);
    }

    public function test_migrations_are_reported_up_to_date(): void
    {
        $this->assertSame(HealthCheck::OK, $this->check('migrations')->status);
        $this->assertSame(HealthCheck::OK, $this->check('database')->status);
    }

    public function test_doctor_command_prints_json_and_exits_non_zero_only_on_failure(): void
    {
        config(['onepay.kasera.api_key' => 'kp_test_abcdef123456', 'onepay.kasera.webhook_secret' => 'secret-nyata']);

        $exit = Artisan::call('onepay:doctor', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report['checks']);
        $this->assertContains('scheduler', array_column($report['checks'], 'key'));
        $this->assertSame($report['ok'], $exit === 0);
    }

    public function test_settings_page_shows_the_health_card_to_the_owner(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::query()->where('role', Role::Owner)->firstOrFail();

        $this->actingAs($owner)->get(route('admin.settings'))->assertOk()->assertSee('Kesehatan sistem')->assertSee('Backup database');
    }

    public function test_settings_page_is_forbidden_for_a_manager(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager, 'is_active' => true]);

        $this->actingAs($manager)->get(route('admin.settings'))->assertForbidden();
    }
}

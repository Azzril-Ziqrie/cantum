<?php

namespace App\Services\Operations;

use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Order;
use App\Services\GatewaySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Pemeriksaan kesiapan produksi. Dipakai oleh `onepay:doctor` dan kartu "Kesehatan sistem" di Pengaturan. */
class SystemHealth
{
    private const SCHEDULER_MAX_AGE_SECONDS = 180;
    private const EXPIRY_GRACE_MINUTES = 5;

    public function __construct(
        private readonly Heartbeat $heartbeat,
        private readonly GatewaySettings $gateway,
    ) {
    }

    /** @return list<HealthCheck> */
    public function run(): array
    {
        return [
            $this->php(),
            $this->appKey(),
            $this->debug(),
            $this->appUrl(),
            $this->database(),
            $this->guarded('migrations', 'Migrasi database', $this->migrations(...)),
            $this->storageLink(),
            $this->writable(),
            $this->scheduler(),
            $this->guarded('expiry', 'Pesanan kedaluwarsa diproses', $this->expiry(...)),
            ...$this->guardedMany($this->kasera(...)),
            $this->guarded('backup', 'Backup database', $this->backup(...)),
            $this->drivers(),
        ];
    }

    /** @param list<HealthCheck> $checks */
    public static function hasFailure(array $checks): bool
    {
        return count(array_filter($checks, fn (HealthCheck $check) => $check->status === HealthCheck::FAIL)) > 0;
    }

    private function php(): HealthCheck
    {
        $problems = (new Prerequisites(base_path()))->problems();
        return $problems === []
            ? HealthCheck::ok('php', 'PHP dan ekstensi', 'PHP ' . PHP_VERSION . ', ekstensi lengkap.')
            : HealthCheck::fail('php', 'PHP dan ekstensi', $problems[0], 'Perbaiki lewat hPanel (PHP Configuration). Masalah lain: ' . (count($problems) - 1) . '.');
    }

    private function appKey(): HealthCheck
    {
        return (string) config('app.key') !== ''
            ? HealthCheck::ok('app_key', 'Kunci aplikasi', 'APP_KEY terisi.')
            : HealthCheck::fail('app_key', 'Kunci aplikasi', 'APP_KEY kosong.', 'Jalankan: php artisan key:generate --force');
    }

    private function debug(): HealthCheck
    {
        return $this->debugRequested()
            ? HealthCheck::failInProduction('debug', 'Mode debug', 'APP_DEBUG=true: detail galat (termasuk kunci) bisa tampil ke pengunjung.', 'Set APP_DEBUG=false di .env lalu jalankan: php artisan config:cache')
            : HealthCheck::ok('debug', 'Mode debug', 'APP_DEBUG=false.');
    }

    /** Nilai yang diminta di .env, karena di produksi aplikasi sendiri memaksa debug mati. */
    private function debugRequested(): bool
    {
        $raw = (new EnvWriter((string) config('onepay.env_path')))->get('APP_DEBUG');
        return $raw !== null ? filter_var($raw, FILTER_VALIDATE_BOOLEAN) : (bool) config('app.debug');
    }

    private function appUrl(): HealthCheck
    {
        $url = (string) config('app.url');
        return str_starts_with($url, 'https://')
            ? HealthCheck::ok('app_url', 'Alamat situs', $url)
            : HealthCheck::failInProduction('app_url', 'Alamat situs', "APP_URL ($url) belum memakai https.", 'Aktifkan SSL di hPanel dan set APP_URL=https://domain-resto.com. Webhook Kasera membutuhkan https.');
    }

    private function database(): HealthCheck
    {
        try {
            DB::connection()->getPdo();
            return HealthCheck::ok('database', 'Koneksi database', 'Terhubung (' . DB::connection()->getDatabaseName() . ').');
        } catch (Throwable $error) {
            return HealthCheck::fail('database', 'Koneksi database', 'Tidak bisa terhubung: ' . $this->shorten($error->getMessage()), 'Periksa DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env.');
        }
    }

    private function migrations(): HealthCheck
    {
        $migrator = app('migrator');
        $repository = $migrator->getRepository();
        if (!$repository->repositoryExists()) {
            return HealthCheck::fail('migrations', 'Migrasi database', 'Tabel belum dibuat.', 'Jalankan: php artisan migrate --force');
        }

        $files = array_keys($migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')]));
        $pending = array_diff($files, $repository->getRan());

        return $pending === []
            ? HealthCheck::ok('migrations', 'Migrasi database', 'Struktur database terbaru.')
            : HealthCheck::fail('migrations', 'Migrasi database', count($pending) . ' migrasi belum dijalankan.', 'Jalankan: php artisan migrate --force');
    }

    private function storageLink(): HealthCheck
    {
        return file_exists(public_path('storage'))
            ? HealthCheck::ok('storage_link', 'Foto menu (storage link)', 'public/storage tersedia.')
            : HealthCheck::warn('storage_link', 'Foto menu (storage link)', 'public/storage belum ada; foto menu tidak akan tampil.', 'Jalankan: php artisan storage:link');
    }

    private function writable(): HealthCheck
    {
        $blocked = array_filter(['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'], fn (string $folder) => !is_writable(base_path($folder)));
        return $blocked === []
            ? HealthCheck::ok('writable', 'Folder tulis', 'storage dan bootstrap/cache bisa ditulis.')
            : HealthCheck::fail('writable', 'Folder tulis', 'Tidak bisa menulis: ' . implode(', ', $blocked), 'Atur izin folder menjadi 775 (chmod -R 775 storage bootstrap/cache).');
    }

    private function scheduler(): HealthCheck
    {
        $last = $this->heartbeat->lastBeat();
        if ($last === null) {
            return HealthCheck::failInProduction('scheduler', 'Cron (scheduler)', 'Belum pernah berjalan: pesanan tidak kedaluwarsa otomatis dan backup tidak jalan.', 'Tambahkan cron tiap menit di hPanel: php /home/USER/domain/artisan schedule:run');
        }

        return $last->diffInSeconds(now()) <= self::SCHEDULER_MAX_AGE_SECONDS
            ? HealthCheck::ok('scheduler', 'Cron (scheduler)', 'Terakhir jalan ' . $last->diffForHumans() . '.')
            : HealthCheck::failInProduction('scheduler', 'Cron (scheduler)', 'Terakhir jalan ' . $last->diffForHumans() . '; cron berhenti.', 'Periksa cron job di hPanel dan path PHP-nya.');
    }

    private function expiry(): HealthCheck
    {
        $overdue = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->count();

        return $overdue === 0
            ? HealthCheck::ok('expiry', 'Pesanan kedaluwarsa diproses', 'Tidak ada pesanan menunggu bayar yang terlewat batas waktu.')
            : HealthCheck::failInProduction('expiry', 'Pesanan kedaluwarsa diproses', "$overdue pesanan melewati batas waktu tetapi belum ditutup.", 'Pastikan cron schedule:run berjalan, atau jalankan: php artisan onepay:expire-orders');
    }

    /** @return list<HealthCheck> */
    private function kasera(): array
    {
        return [$this->kaseraKey(), $this->kaseraSecret()];
    }

    private function kaseraKey(): HealthCheck
    {
        $mode = $this->gateway->mode();

        return match (true) {
            $mode === 'belum diatur' => HealthCheck::fail('kasera_key', 'Kunci Kasera', 'API key belum diisi.', 'Isi KASERA_API_KEY di .env atau di Pengaturan > Gateway.'),
            $mode === 'sandbox' && app()->isProduction() => HealthCheck::warn('kasera_key', 'Kunci Kasera', 'Instalasi produksi masih memakai kunci tes (kp_test_): uang tidak sungguhan.', 'Setelah uji selesai, ganti ke kunci kp_live_ di Pengaturan > Gateway.'),
            $mode === 'produksi' && !app()->isProduction() => HealthCheck::warn('kasera_key', 'Kunci Kasera', 'Lingkungan non-produksi memakai kunci live (kp_live_): transaksi bisa sungguhan.', 'Pakai kunci kp_test_ untuk pengembangan dan uji.'),
            default => HealthCheck::ok('kasera_key', 'Kunci Kasera', "Mode $mode (" . $this->gateway->maskedApiKey() . ').'),
        };
    }

    private function kaseraSecret(): HealthCheck
    {
        $secret = $this->gateway->webhookSecret();
        return $secret !== '' && $secret !== 'ISI_DISINI'
            ? HealthCheck::ok('kasera_secret', 'Webhook secret', 'Terisi.')
            : HealthCheck::fail('kasera_secret', 'Webhook secret', 'Webhook secret belum diisi; webhook pembayaran akan ditolak.', 'Salin dari Kasera (Developer > Webhooks) ke KASERA_WEBHOOK_SECRET atau Pengaturan > Gateway.');
    }

    private function backup(): HealthCheck
    {
        $recorded = Brand::query()->value('last_backup_at');
        if ($recorded === null) {
            return HealthCheck::warn('backup', 'Backup database', 'Belum pernah ada backup.', 'Jalankan: php artisan onepay:backup, dan pastikan cron schedule:run aktif agar backup harian jalan.');
        }

        $last = Carbon::parse($recorded);
        return $last->diffInHours(now()) < (int) config('onepay.backup.stale_after_hours')
            ? HealthCheck::ok('backup', 'Backup database', 'Terakhir ' . $last->diffForHumans() . '.')
            : HealthCheck::warn('backup', 'Backup database', 'Backup terakhir ' . $last->diffForHumans() . '.', 'Periksa cron dan jalankan: php artisan onepay:backup');
    }

    private function drivers(): HealthCheck
    {
        $weak = array_filter(['SESSION_DRIVER' => config('session.driver'), 'CACHE_STORE' => config('cache.default')], fn ($driver) => $driver === 'array');
        return $weak === []
            ? HealthCheck::ok('drivers', 'Session dan cache', 'Session ' . config('session.driver') . ', cache ' . config('cache.default') . '.')
            : HealthCheck::warn('drivers', 'Session dan cache', 'Memakai driver array: login dan batas sinkronisasi tidak bertahan antar permintaan.', 'Set SESSION_DRIVER=file dan CACHE_STORE=file di .env.');
    }

    /** Pemeriksaan yang bergantung pada database: bila gagal, jangan menjatuhkan seluruh laporan. */
    private function guarded(string $key, string $label, callable $check): HealthCheck
    {
        try {
            return $check();
        } catch (Throwable $error) {
            return HealthCheck::fail($key, $label, 'Tidak bisa diperiksa: ' . $this->shorten($error->getMessage()), 'Perbaiki koneksi database dan migrasi lebih dulu.');
        }
    }

    /** @return list<HealthCheck> */
    private function guardedMany(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $error) {
            return [HealthCheck::fail('kasera', 'Kunci Kasera', 'Tidak bisa diperiksa: ' . $this->shorten($error->getMessage()), 'Perbaiki koneksi database dan migrasi lebih dulu.')];
        }
    }

    private function shorten(string $message): string
    {
        return mb_strlen($message) > 160 ? mb_substr($message, 0, 157) . '...' : $message;
    }
}

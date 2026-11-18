<?php

namespace App\Console\Commands;

use App\Services\Operations\EnvWriter;
use App\Services\Operations\Prerequisites;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Pemasangan satu instalasi untuk satu resto. Aman diulang: .env yang sudah terisi dipertahankan,
 * migrasi hanya menambah, dan data awal tidak menimpa yang ada.
 */
class InstallCommand extends Command
{
    protected $signature = 'onepay:install
        {--url= : Alamat situs resto, mis. https://pesan.resto.com}
        {--db-host= : Host database (bawaan 127.0.0.1)}
        {--db-port= : Port database (bawaan 3306)}
        {--db-name= : Nama database}
        {--db-user= : Pengguna database}
        {--db-pass= : Kata sandi database}
        {--kasera-key= : API key Kasera (kp_test_... untuk uji, kp_live_... untuk produksi)}
        {--kasera-secret= : Webhook secret Kasera}
        {--brand= : Nama resto}
        {--branch= : Nama cabang pertama}
        {--owner-name= : Nama akun owner}
        {--owner-email= : Email akun owner}
        {--owner-password= : Kata sandi owner (kosong = dibuat acak)}
        {--reset-env : Tulis ulang .env dari .env.example}
        {--skip-optimize : Lewati config/route/view cache}';

    protected $description = 'Pasang OnePay: cek server, tulis .env, migrasi, data awal, dan optimasi produksi';

    /** Nilai tetap untuk .env produksi baru. */
    private const PRODUCTION_DEFAULTS = [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_LOCALE' => 'id', 'LOG_LEVEL' => 'warning',
        'DB_CONNECTION' => 'mysql', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync',
    ];

    private EnvWriter $env;
    private bool $generatedPassword = false;
    private ?string $ownerEmail = null;
    private bool $ownerCreated = false;

    public function handle(): int
    {
        $this->info('Cantum OnePay: pemasangan');

        if (!$this->checkPrerequisites()) {
            return self::FAILURE;
        }

        $this->env = new EnvWriter(base_path('.env'), base_path('.env.example'));
        $url = $this->configureEnvironment();
        if ($url === null) {
            return self::FAILURE;
        }

        $password = $this->ownerPassword();
        if (!$this->prepareApplication($password)) {
            return self::FAILURE;
        }

        $this->printSummary($url, $password);
        $this->runArtisan(['onepay:doctor'], echo: true);
        return self::SUCCESS;
    }

    private function checkPrerequisites(): bool
    {
        $problems = (new Prerequisites(base_path()))->problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        if ($problems === []) {
            $this->line('Prasyarat server terpenuhi.');
        }
        return $problems === [];
    }

    /** @return string|null APP_URL final, atau null bila ada isian wajib yang kosong */
    private function configureEnvironment(): ?string
    {
        $fresh = !$this->env->exists() || $this->option('reset-env');
        $current = $fresh ? [] : $this->env->read();
        $values = $fresh ? self::PRODUCTION_DEFAULTS : [];

        $fields = [
            // kunci .env => [opsi, pertanyaan, bawaan (null = wajib), disembunyikan saat diketik]
            'APP_URL' => ['url', 'Alamat situs (https://...)', null, false],
            'DB_HOST' => ['db-host', 'Host database', '127.0.0.1', false],
            'DB_PORT' => ['db-port', 'Port database', '3306', false],
            'DB_DATABASE' => ['db-name', 'Nama database', null, false],
            'DB_USERNAME' => ['db-user', 'Pengguna database', null, false],
            'DB_PASSWORD' => ['db-pass', 'Kata sandi database', '', true],
            'KASERA_API_KEY' => ['kasera-key', 'API key Kasera (boleh dikosongkan, isi nanti di Pengaturan)', '', true],
            'KASERA_WEBHOOK_SECRET' => ['kasera-secret', 'Webhook secret Kasera (boleh dikosongkan)', '', true],
        ];

        foreach ($fields as $key => [$option, $question, $default, $secret]) {
            $answer = $this->resolve($key, $option, $question, $default, $secret, $current);
            if ($answer === null) {
                $this->error("$key wajib diisi (opsi --$option).");
                return null;
            }
            $values[$key] = $answer;
        }

        if ($fresh || $this->option('brand')) {
            $values['APP_NAME'] = (string) ($this->option('brand') ?: 'Cantum OnePay');
        }

        $this->env->set($values);
        $this->line($fresh ? '.env produksi ditulis.' : '.env yang ada dipertahankan; hanya isian yang kosong atau diberikan lewat opsi yang diperbarui.');
        return $values['APP_URL'];
    }

    /**
     * Urutan: opsi eksplisit, nilai .env yang sudah terisi, pertanyaan interaktif, nilai bawaan.
     * Mengembalikan null bila wajib tetapi tidak ada jawaban.
     */
    private function resolve(string $key, string $option, string $question, ?string $default, bool $secret, array $current): ?string
    {
        $given = $this->option($option);
        if ($given !== null && $given !== '') {
            return (string) $given;
        }

        $existing = $current[$key] ?? null;
        if ($existing !== null && $existing !== '' && !$this->isPlaceholder($existing)) {
            return $existing;
        }

        if ($this->input->isInteractive()) {
            $answer = $secret ? $this->secret($question) : $this->ask($question, $default);
            return $answer === null || $answer === '' ? $default : (string) $answer;
        }

        return $default;
    }

    private function isPlaceholder(string $value): bool
    {
        return str_contains($value, 'ISI_DISINI') || $value === 'null';
    }

    private function ownerPassword(): string
    {
        $given = (string) $this->option('owner-password');
        if ($given !== '') {
            return $given;
        }
        $this->generatedPassword = true;
        return Str::password(14, symbols: false);
    }

    private function prepareApplication(string $ownerPassword): bool
    {
        $this->runArtisan(['config:clear']);

        $steps = [
            'Kunci aplikasi' => fn () => $this->env->get('APP_KEY') ? true : $this->runArtisan(['key:generate', '--force']),
            'Migrasi database' => fn () => $this->runArtisan(['migrate', '--force'], echo: true),
            'Data awal' => fn () => $this->provision($ownerPassword),
            'Foto menu (storage link)' => fn () => file_exists(public_path('storage')) ? true : $this->runArtisan(['storage:link']),
        ];

        foreach ($steps as $label => $step) {
            $this->line("→ $label");
            if (!$step()) {
                $this->error("Gagal pada langkah: $label. Perbaiki lalu jalankan onepay:install lagi (aman diulang).");
                return false;
            }
        }

        $this->optimize();
        return true;
    }

    private function provision(string $ownerPassword): bool
    {
        $email = (string) ($this->option('owner-email') ?: $this->ask('Email akun owner', 'owner@' . (parse_url((string) $this->env->get('APP_URL'), PHP_URL_HOST) ?: 'resto.com')));

        $process = $this->runArtisan([
            'onepay:provision',
            '--brand=' . ($this->option('brand') ?: 'Resto Saya'),
            '--branch=' . ($this->option('branch') ?: 'Cabang Utama'),
            '--owner-name=' . ($this->option('owner-name') ?: 'Owner'),
            '--owner-email=' . $email,
        ], env: ['ONEPAY_OWNER_PASSWORD' => $ownerPassword], echo: true, returnProcess: true);

        $this->ownerEmail = $email;
        $this->ownerCreated = str_contains($process->getOutput(), 'Owner dibuat');
        return $process->isSuccessful();
    }

    private function optimize(): void
    {
        if ($this->option('skip-optimize')) {
            return;
        }
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            if (!$this->runArtisan([$command])) {
                $this->warn("$command gagal; aplikasi tetap jalan tanpa cache itu.");
            }
        }
    }

    private function printSummary(string $url, string $password): void
    {
        $this->newLine();
        $this->info('Pemasangan selesai.');

        if ($this->ownerCreated) {
            $this->line("Login owner: {$this->ownerEmail}");
            $this->line($this->generatedPassword ? "Kata sandi: $password   (catat sekarang, tidak ditampilkan lagi)" : 'Kata sandi: sesuai opsi --owner-password');
        } else {
            $this->line('Akun owner sudah ada dari pemasangan sebelumnya; kata sandi tidak diubah.');
        }

        $this->newLine();
        $this->line('Langkah berikutnya:');
        $this->line('  1. Daftarkan webhook di dashboard Kasera (Developer > Webhooks): ' . rtrim($url, '/') . '/kasera-webhook');
        $this->line('  2. Pasang cron tiap menit di hPanel: php ' . base_path('artisan') . ' schedule:run');
        $this->line('  3. Masuk ke ' . rtrim($url, '/') . '/staff, atur meja dan menu, lalu uji pembayaran dengan kunci kp_test_.');
        $this->line('  4. Setelah uji lulus, ganti ke kunci kp_live_ di Pengaturan > Gateway.');
        $this->newLine();
    }

    /**
     * Langkah artisan dijalankan sebagai proses baru supaya membaca .env yang baru ditulis,
     * bukan konfigurasi lama yang sudah termuat di proses ini.
     */
    private function runArtisan(array $arguments, array $env = [], bool $echo = false, bool $returnProcess = false): bool|Process
    {
        $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments, '--no-interaction'], base_path(), $env === [] ? null : $env);
        $process->setTimeout(600);
        $process->run($echo ? fn (string $type, string $buffer) => $this->output->write($buffer) : null);

        return $returnProcess ? $process : $process->isSuccessful();
    }
}

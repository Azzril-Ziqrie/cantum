<?php

namespace App\Console\Commands;

use App\Services\Operations\Provisioner;
use Illuminate\Console\Command;

/** Dipanggil oleh onepay:install setelah migrasi. Kata sandi owner dikirim lewat env ONEPAY_OWNER_PASSWORD agar tidak tampil di daftar proses. */
class ProvisionCommand extends Command
{
    protected $signature = 'onepay:provision
        {--brand=Resto Saya : Nama resto}
        {--branch=Cabang Utama : Nama cabang pertama}
        {--owner-name=Owner : Nama akun owner}
        {--owner-email= : Email akun owner}';

    protected $description = 'Buat brand, cabang pertama, dan akun owner bila belum ada (aman diulang)';

    public function handle(Provisioner $provisioner): int
    {
        $email = (string) $this->option('owner-email');
        $password = (string) getenv('ONEPAY_OWNER_PASSWORD');
        if ($email === '' || $password === '') {
            $this->error('Email owner (--owner-email) dan env ONEPAY_OWNER_PASSWORD wajib diisi.');
            return self::FAILURE;
        }

        $created = $provisioner->run((string) $this->option('brand'), (string) $this->option('branch'), (string) $this->option('owner-name'), $email, $password);

        $this->line($created === [] ? 'Data awal sudah ada, tidak ada yang diubah.' : 'Dibuat: ' . implode(', ', $created));
        if (in_array('owner', $created, true)) {
            $this->line("Owner dibuat: $email");
        }
        return self::SUCCESS;
    }
}

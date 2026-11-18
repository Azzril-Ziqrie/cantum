<?php

namespace App\Console\Commands;

use App\Services\Operations\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupCommand extends Command
{
    protected $signature = 'onepay:backup {--keep= : Simpan backup N hari terakhir (bawaan dari config)}';

    protected $description = 'Backup database ke storage/app/backups (.sql.gz) dan hapus yang sudah lama';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $path = $backup->run();
        } catch (Throwable $error) {
            $this->error('Backup gagal: ' . $error->getMessage());
            return self::FAILURE;
        }

        $this->info('Backup tersimpan: ' . $path . ' (' . number_format(filesize($path) / 1024, 1) . ' KB)');

        $keepDays = (int) ($this->option('keep') ?: config('onepay.backup.keep_days'));
        $removed = $backup->prune($keepDays);
        if ($removed > 0) {
            $this->line("$removed backup lama (lebih dari $keepDays hari) dihapus.");
        }
        $this->line('Salin berkas ini juga ke tempat lain (komputer atau cloud); backup di server yang sama tidak menolong bila server rusak.');
        return self::SUCCESS;
    }
}

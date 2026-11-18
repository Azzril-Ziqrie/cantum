<?php

namespace App\Services\Operations;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/** Backup database ke berkas .sql.gz. Memakai mysqldump bila ada; bila tidak, dump lewat PDO. */
class DatabaseBackup
{
    private const FILE_PATTERN = 'onepay-*.sql.gz';
    private const MYSQLDUMP_TIMEOUT_SECONDS = 600;

    /** @return string path berkas backup */
    public function run(): string
    {
        $directory = $this->directory();
        $sqlPath = $directory . '/onepay-' . date('Ymd-His') . '.sql';
        $gzPath = $sqlPath . '.gz';

        try {
            $this->dumpTo($sqlPath);
            $this->compress($sqlPath, $gzPath);
        } finally {
            @unlink($sqlPath);
        }

        $this->recordBackup(basename($gzPath));
        return $gzPath;
    }

    /** Hapus backup yang lebih tua dari $keepDays hari. Mengembalikan jumlah berkas yang dihapus. */
    public function prune(int $keepDays): int
    {
        $limit = time() - max(1, $keepDays) * 86400;
        $removed = 0;
        foreach (glob($this->directory() . '/' . self::FILE_PATTERN) ?: [] as $file) {
            if (filemtime($file) < $limit && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    private function dumpTo(string $sqlPath): void
    {
        if ($this->tryMysqldump($sqlPath)) {
            return;
        }

        $handle = fopen($sqlPath, 'wb');
        try {
            (new SqlDumper(DB::connection()))->dump($handle);
        } finally {
            fclose($handle);
        }
    }

    private function tryMysqldump(string $sqlPath): bool
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql' || !config('onepay.backup.use_mysqldump')) {
            return false;
        }

        $binary = (string) (config('onepay.backup.mysqldump_path') ?: (new ExecutableFinder())->find('mysqldump'));
        if ($binary === '') {
            return false;
        }

        try {
            $config = $connection->getConfig();
            $process = new Process([
                $binary, '--host=' . $config['host'], '--port=' . ($config['port'] ?? 3306), '--user=' . $config['username'],
                '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--default-character-set=utf8mb4',
                '--result-file=' . $sqlPath, $config['database'],
            ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
            $process->setTimeout(self::MYSQLDUMP_TIMEOUT_SECONDS);
            $process->run();

            return $process->isSuccessful() && is_file($sqlPath) && filesize($sqlPath) > 0;
        } catch (Throwable) {
            return false; // proc_open dilarang atau mysqldump gagal: pakai dump PDO
        }
    }

    private function compress(string $sqlPath, string $gzPath): void
    {
        $in = fopen($sqlPath, 'rb');
        $out = gzopen($gzPath, 'wb6');
        if ($in === false || $out === false) {
            throw new RuntimeException('Tidak bisa menulis berkas backup di ' . dirname($gzPath));
        }
        while (!feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
    }

    private function recordBackup(string $filename): void
    {
        Brand::query()->update(['last_backup_at' => now(), 'backup_note' => 'Otomatis: ' . $filename]);
    }

    private function directory(): string
    {
        $directory = (string) config('onepay.backup.path');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Folder backup $directory tidak bisa dibuat.");
        }
        return $directory;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Operations\HealthCheck;
use App\Services\Operations\SystemHealth;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'onepay:doctor {--json : Keluaran JSON untuk dipantau mesin}';

    protected $description = 'Periksa kesiapan produksi: konfigurasi, database, cron, gateway, dan backup';

    private const LABEL = [HealthCheck::OK => 'OK', HealthCheck::WARN => 'PERINGATAN', HealthCheck::FAIL => 'GAGAL'];

    public function handle(SystemHealth $health): int
    {
        $checks = $health->run();

        $this->option('json') ? $this->printJson($checks) : $this->printTable($checks);

        return SystemHealth::hasFailure($checks) ? self::FAILURE : self::SUCCESS;
    }

    /** @param list<HealthCheck> $checks */
    private function printJson(array $checks): void
    {
        $this->line(json_encode(['ok' => !SystemHealth::hasFailure($checks), 'checks' => array_map(fn (HealthCheck $c) => $c->toArray(), $checks)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /** @param list<HealthCheck> $checks */
    private function printTable(array $checks): void
    {
        foreach ($checks as $check) {
            $line = sprintf('[%s] %s: %s', self::LABEL[$check->status], $check->label, $check->message);
            match ($check->status) {
                HealthCheck::OK => $this->info($line),
                HealthCheck::WARN => $this->warn($line),
                HealthCheck::FAIL => $this->error($line),
            };
            if ($check->advice !== '') {
                $this->line('       Saran: ' . $check->advice);
            }
        }

        $failures = count(array_filter($checks, fn (HealthCheck $c) => $c->status === HealthCheck::FAIL));
        $warnings = count(array_filter($checks, fn (HealthCheck $c) => $c->status === HealthCheck::WARN));
        $this->newLine();
        $this->line($failures === 0 ? "Siap dipakai ($warnings peringatan)." : "$failures masalah harus diperbaiki sebelum dipakai ($warnings peringatan).");
    }
}

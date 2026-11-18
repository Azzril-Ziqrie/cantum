<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/** Rentang tanggal (inklusif, zona waktu aplikasi) dan cabang yang sudah lolos pemeriksaan akses. */
final class ReportFilter
{
    public const MAX_DAYS = 366;

    /** @param list<int> $branchIds */
    public function __construct(
        public readonly array $branchIds,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $branchId,
    ) {
    }

    public function start(): string
    {
        return $this->from->startOfDay()->format('Y-m-d H:i:s');
    }

    public function end(): string
    {
        return $this->to->endOfDay()->format('Y-m-d H:i:s');
    }

    public function dayCount(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function isMultiBranch(): bool
    {
        return count($this->branchIds) > 1;
    }

    /** Parameter query string untuk tautan ekspor dan cetak. */
    public function queryParams(): array
    {
        return ['branch' => $this->branchId ?? 'all', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}

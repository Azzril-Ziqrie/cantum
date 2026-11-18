<?php

namespace App\Services\Reports;

use App\Services\Kasera\PaymentMethods;

/** Menulis laporan sebagai CSV bersusun (satu berkas, tiap bagian diberi judul). Aman untuk Excel. */
class ReportCsv
{
    /** @param resource $out */
    public function write($out, array $report, ReportFilter $filter, string $branchName): void
    {
        fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8

        $this->row($out, ['Laporan penjualan', $branchName, $filter->from->toDateString(), $filter->to->toDateString()]);
        $this->summary($out, $report['summary']);
        $this->section($out, 'Penjualan per jam', ['Jam', 'Pesanan', 'Omzet'], array_map(
            fn ($r) => [sprintf('%02d:00', $r['hour']), $r['orders'], $r['revenue']], $report['by_hour']));
        $this->section($out, 'Penjualan per hari', ['Tanggal', 'Pesanan', 'Omzet'], array_map(
            fn ($r) => [$r['date'], $r['orders'], $r['revenue']], $report['by_day']));
        $this->section($out, 'Penjualan per kategori', ['Kategori', 'Jumlah', 'Omzet'], array_map(
            fn ($r) => [$r['name'], $r['qty'], $r['revenue']], $report['by_category']));
        $this->section($out, 'Menu terlaris', $this->itemHeader(), array_map($this->itemRow(...), $report['top_items']));
        $this->section($out, 'Menu kurang laku', $this->itemHeader(), array_map($this->itemRow(...), $report['low_items']));
        $this->section($out, 'Metode pembayaran', ['Metode', 'Pesanan', 'Omzet'], array_map(
            fn ($r) => [self::methodLabel($r['method']), $r['orders'], $r['revenue']], $report['by_method']));
        $this->section($out, 'Meja', ['Meja', 'Area', 'Pesanan', 'Omzet', 'Scan QR', 'Konversi'], array_map(
            fn ($r) => [$r['label'], $r['area'], $r['orders'], $r['revenue'], $r['scans'], self::percent($r['conversion'])], $report['tables']));
        $this->section($out, 'Area', ['Area', 'Pesanan', 'Omzet', 'Scan QR', 'Konversi'], array_map(
            fn ($r) => [$r['area'], $r['orders'], $r['revenue'], $r['scans'], self::percent($r['conversion'])], $report['areas']));
        $this->section($out, 'Konversi scan QR', ['Scan QR', 'Pesanan dibuat', 'Pesanan terbayar', 'Scan ke terbayar'], [[
            $report['funnel']['scans'], $report['funnel']['created'], $report['funnel']['paid'], self::percent($report['funnel']['scan_to_paid']),
        ]]);
        if ($report['branches'] !== []) {
            $this->section($out, 'Perbandingan cabang', ['Cabang', 'Pesanan', 'Omzet bruto', 'Refund', 'Omzet bersih', 'Rata-rata', 'Pembatalan'], array_map(
                fn ($r) => [$r['branch'], $r['sold_orders'], $r['gross'], $r['refunds'], $r['net'], $r['average_order'], self::percent($r['cancel_rate'])], $report['branches']));
        }
    }

    /** @param resource $out */
    public function writeDailyClose($out, array $close, string $branchName): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        $this->row($out, ['Tutup harian', $branchName, $close['date']->toDateString()]);
        $header = ['Metode', 'Diterima gateway (pesanan)', 'Diterima gateway (Rp)', 'Terlambat (pesanan)', 'Terlambat (Rp)', 'Dibatalkan (pesanan)', 'Dibatalkan (Rp)', 'Menunggu bayar (pesanan)', 'Menunggu bayar (Rp)', 'Kedaluwarsa (pesanan)', 'Kedaluwarsa (Rp)'];
        $keys = ['count', 'amount', 'late_count', 'late_amount', 'cancelled_count', 'cancelled_amount', 'pending_count', 'pending_amount', 'expired_count', 'expired_amount'];
        $rows = array_map(fn ($r) => [self::methodLabel($r['method']), ...array_map(fn ($k) => $r[$k], $keys)], $close['rows']);
        $rows[] = ['Total', ...array_map(fn ($k) => $close['totals'][$k], $keys)];
        $this->section($out, 'Rekonsiliasi', $header, $rows);
        $this->section($out, 'Refund dicatat hari ini', ['Jumlah'], [[$close['refunds']]]);
    }

    public static function methodLabel(string $code): string
    {
        return PaymentMethods::find($code)['label'] ?? $code;
    }

    public static function percent(?float $value): string
    {
        return $value === null ? '-' : number_format($value * 100, 1, ',', '') . '%';
    }

    private function summary($out, array $s): void
    {
        $this->section($out, 'Ringkasan', ['Ukuran', 'Nilai'], [
            ['Omzet bruto', $s['gross']], ['Refund', $s['refunds']], ['Omzet bersih', $s['net']],
            ['Pesanan terbayar', $s['paid_orders']], ['Pesanan terjual', $s['sold_orders']], ['Pesanan dibatalkan', $s['cancelled_orders']],
            ['Rata-rata nilai pesanan', $s['average_order']], ['Tingkat pembatalan', self::percent($s['cancel_rate'])], ['Tingkat refund', self::percent($s['refund_rate'])],
            ['Subtotal', $s['subtotal']], ['Total diskon', $s['discount']], ['Service charge', $s['service']], ['Pajak', $s['tax']], ['Pembulatan', $s['rounding']],
        ]);
    }

    private function itemHeader(): array
    {
        return ['Menu', 'Terjual', 'Omzet', 'HPP', 'Margin', 'Margin %'];
    }

    private function itemRow(array $r): array
    {
        return [$r['name'], $r['qty'], $r['revenue'], $r['cost'], $r['margin'], self::percent($r['margin_rate'])];
    }

    private function section($out, string $title, array $header, array $rows): void
    {
        $this->row($out, []);
        $this->row($out, [$title]);
        $this->row($out, $header);
        foreach ($rows as $row) {
            $this->row($out, $row);
        }
    }

    private function row($out, array $cells): void
    {
        fputcsv($out, array_map($this->safe(...), $cells), ',', '"', '\\');
    }

    /** Sel yang diawali = + - @ bisa dibaca Excel sebagai rumus; awali dengan apostrof. Angka murni dibiarkan. */
    private function safe(mixed $cell): string
    {
        $text = (string) $cell;
        return (is_string($cell) && $text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) ? "'" . $text : $text;
    }
}

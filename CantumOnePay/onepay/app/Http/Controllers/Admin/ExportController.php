<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CsvCell;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ekspor CSV untuk cadangan data. Berawalan BOM supaya terbaca benar di Excel. */
class ExportController extends Controller
{
    public function menu(): StreamedResponse
    {
        $rows = fn () => Item::query()->with('category:id,name')->orderBy('category_id')->orderBy('sort_order')->orderBy('id')->cursor()
            ->map(fn (Item $item) => [
                $item->category?->name, $item->name, $item->description, $item->price, $item->cost,
                $item->station, (int) $item->is_best_seller, (int) $item->is_active,
            ]);

        return $this->csv('menu-' . now()->format('Ymd') . '.csv',
            ['kategori', 'nama', 'deskripsi', 'harga', 'hpp', 'stasiun', 'best_seller', 'aktif'], $rows);
    }

    public function orders(Request $request): StreamedResponse
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'branch' => ['nullable', 'integer']]);
        $from = ($range['from'] ?? now()->subDays(30)->toDateString()) . ' 00:00:00';
        $to = ($range['to'] ?? now()->toDateString()) . ' 23:59:59';

        $rows = fn () => Order::query()->with(['branch:id,name', 'payment:id,order_id,method'])
            ->whereBetween('created_at', [$from, $to])
            ->when($range['branch'] ?? null, fn ($q, $branch) => $q->where('branch_id', $branch))
            ->orderBy('id')->cursor()
            ->map(fn (Order $o) => [
                $o->code, $o->branch?->name, $o->table_label, $o->status->value, $o->subtotal, $o->discount, $o->service,
                $o->tax, $o->rounding, $o->total, $o->payment?->method, $o->created_at, $o->paid_at,
            ]);

        return $this->csv('pesanan-' . now()->format('Ymd') . '.csv',
            ['kode', 'cabang', 'meja', 'status', 'subtotal', 'diskon', 'service', 'pajak', 'pembulatan', 'total', 'metode', 'dibuat', 'dibayar'], $rows);
    }

    private function csv(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows() as $row) {
                fputcsv($out, array_map(CsvCell::safe(...), $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

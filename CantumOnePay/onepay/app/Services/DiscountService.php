<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Perhitungan promo. Semua dihitung di server dari data database; browser hanya mengirim kode promo.
 *
 * Aturan (keputusan sementara untuk pertanyaan terbuka di goal.md):
 * - Satu promo per pesanan: dari semua promo otomatis yang memenuhi syarat dan kode yang diketik,
 *   dipakai yang memberi potongan terbesar (seri: id terkecil).
 * - Potongan mengurangi dasar service charge dan pajak (subtotal, diskon, service, pajak, pembulatan).
 * - Kuota dihitung saat pesanan dibuat, bukan saat dibayar. Pesanan yang kedaluwarsa tanpa bayar
 *   mengembalikan kuota; pesanan yang sudah dibayar lalu dibatalkan tidak mengembalikannya.
 * - Jam happy hour berlaku dari `time_from` (termasuk) sampai `time_until` (tidak termasuk) dan boleh melewati tengah malam.
 */
class DiscountService
{
    /**
     * @param list<array{item_id:int,unit_price:int,qty:int}> $lines baris keranjang yang sudah dihargai server
     * @throws OrderRejected bila kode promo diketik tetapi tidak bisa dipakai
     */
    public function resolve(array $lines, int $branchId, ?string $code, ?Carbon $now = null): ?AppliedDiscount
    {
        $now ??= now();
        $basket = $this->basket($lines);
        $typed = $this->findByCode($code);

        $candidates = Discount::query()->with(['branches', 'targets'])->where('is_active', true)->whereNull('code')->get();
        if ($typed !== null) {
            $this->assertUsable($typed, $basket, $branchId, $now);
            $candidates->push($typed->load(['branches', 'targets']));
        }

        $best = $this->bestOf($candidates, $basket, $branchId, $now);
        return $best === null ? null : new AppliedDiscount($best['discount']->id, $best['discount']->name, $best['amount']);
    }

    /** Ambil satu kuota secara atomik. Dipanggil di dalam transaksi pembuatan pesanan. */
    public function reserveUsage(AppliedDiscount $applied): void
    {
        $taken = Discount::query()->whereKey($applied->discountId)
            ->whereRaw('(usage_limit IS NULL OR used_count < usage_limit)')
            ->increment('used_count');

        if ($taken === 0) {
            throw new OrderRejected('Kuota promo sudah habis.');
        }
    }

    /** Kembalikan kuota untuk pesanan yang kedaluwarsa tanpa dibayar. */
    public function releaseUsage(Order $order): void
    {
        if ($order->discount_id === null) {
            return;
        }
        Discount::query()->whereKey($order->discount_id)->where('used_count', '>', 0)->decrement('used_count');
    }

    /** Promo otomatis yang sedang berlaku di cabang, untuk ditampilkan di menu pelanggan. */
    public function publicPromos(int $branchId, ?Carbon $now = null): array
    {
        $now ??= now();

        return Discount::query()->with('branches')->where('is_active', true)->whereNull('code')->get()
            ->filter(fn (Discount $d) => $this->scheduleReason($d, $branchId, $now) === null && $d->hasQuotaLeft())
            ->map(fn (Discount $d) => ['name' => $d->name, 'summary' => $d->summary()])
            ->values()->all();
    }

    /** @return array{lines:list<array>,subtotal:int} */
    private function basket(array $lines): array
    {
        $categories = Item::query()->whereIn('id', array_column($lines, 'item_id'))->pluck('category_id', 'id');
        $lines = array_map(fn ($line) => $line + ['category_id' => (int) ($categories[$line['item_id']] ?? 0)], $lines);

        return ['lines' => $lines, 'subtotal' => array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $lines))];
    }

    private function findByCode(?string $code): ?Discount
    {
        $code = Str::upper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $discount = Discount::query()->where('code', $code)->first();
        if ($discount === null) {
            throw new OrderRejected('Kode promo tidak ditemukan.');
        }
        return $discount;
    }

    private function assertUsable(Discount $discount, array $basket, int $branchId, Carbon $now): void
    {
        $reason = $this->unusableReason($discount, $basket, $branchId, $now);
        if ($reason !== null) {
            throw new OrderRejected($reason);
        }
    }

    /** @return array{discount:Discount,amount:int}|null */
    private function bestOf($candidates, array $basket, int $branchId, Carbon $now): ?array
    {
        $best = null;
        foreach ($candidates as $discount) {
            if ($this->unusableReason($discount, $basket, $branchId, $now) !== null) {
                continue;
            }
            $amount = $this->amountFor($discount, $basket);
            if ($best === null || $amount > $best['amount'] || ($amount === $best['amount'] && $discount->id < $best['discount']->id)) {
                $best = ['discount' => $discount, 'amount' => $amount];
            }
        }
        return $best;
    }

    /** Alasan promo tidak bisa dipakai untuk keranjang ini, atau null bila bisa. */
    private function unusableReason(Discount $discount, array $basket, int $branchId, Carbon $now): ?string
    {
        return $this->scheduleReason($discount, $branchId, $now)
            ?? $this->quotaAndSpendReason($discount, $basket)
            ?? ($this->amountFor($discount, $basket) <= 0 ? 'Tidak ada menu di pesanan yang memenuhi promo ini.' : null);
    }

    private function scheduleReason(Discount $discount, int $branchId, Carbon $now): ?string
    {
        return match (true) {
            !$discount->is_active => 'Promo tidak aktif.',
            !$discount->appliesToBranch($branchId) => 'Promo tidak berlaku di cabang ini.',
            $discount->starts_at !== null && $now->toDateString() < $discount->starts_at->toDateString() => 'Promo belum dimulai.',
            $discount->ends_at !== null && $now->toDateString() > $discount->ends_at->toDateString() => 'Promo sudah berakhir.',
            !$this->dayAllowed($discount, $now) => 'Promo tidak berlaku hari ini.',
            !$this->timeAllowed($discount, $now) => 'Promo hanya berlaku pukul ' . substr($discount->time_from, 0, 5) . '–' . substr($discount->time_until, 0, 5) . '.',
            default => null,
        };
    }

    private function quotaAndSpendReason(Discount $discount, array $basket): ?string
    {
        return match (true) {
            !$discount->hasQuotaLeft() => 'Kuota promo sudah habis.',
            $basket['subtotal'] < $discount->min_spend => 'Minimal belanja Rp ' . number_format($discount->min_spend, 0, ',', '.') . ' untuk promo ini.',
            default => null,
        };
    }

    private function dayAllowed(Discount $discount, Carbon $now): bool
    {
        return empty($discount->days) || in_array($now->dayOfWeekIso, array_map('intval', $discount->days), true);
    }

    private function timeAllowed(Discount $discount, Carbon $now): bool
    {
        if ($discount->time_from === null || $discount->time_until === null) {
            return true;
        }
        $time = $now->format('H:i:s');
        $from = $discount->time_from;
        $until = $discount->time_until;

        return $from <= $until ? ($time >= $from && $time < $until) : ($time >= $from || $time < $until);
    }

    /** Potongan rupiah: tipe persen dibatasi plafon, nominal tetap tidak melebihi dasar potongan. */
    private function amountFor(Discount $discount, array $basket): int
    {
        $base = $this->eligibleBase($discount, $basket);
        $amount = $discount->type === Discount::TYPE_PERCENT
            ? (int) floor($base * $discount->value / 100)
            : $discount->value;

        if ($discount->type === Discount::TYPE_PERCENT && $discount->max_discount !== null) {
            $amount = min($amount, $discount->max_discount);
        }
        return max(0, min($amount, $base));
    }

    /** Nilai belanja yang ikut dipotong, sesuai cakupan promo. */
    private function eligibleBase(Discount $discount, array $basket): int
    {
        if ($discount->scope === Discount::SCOPE_ORDER) {
            return $basket['subtotal'];
        }
        $targets = $discount->targetIds();
        $field = $discount->scope === Discount::SCOPE_CATEGORIES ? 'category_id' : 'item_id';

        $matching = array_filter($basket['lines'], fn ($line) => in_array($line[$field], $targets, true));
        return array_sum(array_map(fn ($line) => $line['unit_price'] * $line['qty'], $matching));
    }
}

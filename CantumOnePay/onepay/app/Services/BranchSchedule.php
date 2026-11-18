<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Carbon;

/**
 * Aturan jam buka cabang: tombol buka/tutup, hari libur, jam buka per hari, dan batas pesanan menjelang tutup.
 * Cabang tanpa jam buka (open_hours kosong) dianggap buka sepanjang hari selama tombol buka aktif.
 */
class BranchSchedule
{
    public const DAYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    /** @throws OrderRejected */
    public function assertOrderable(Branch $branch, ?Carbon $now = null): void
    {
        $now ??= now();

        if (!$branch->is_open) {
            throw new OrderRejected('Cabang sedang tutup.');
        }
        if ($branch->open_hours === null && $branch->holidays === null) {
            return;
        }
        if ($this->isHoliday($branch, $now)) {
            throw new OrderRejected('Cabang libur hari ini.');
        }
        if ($branch->open_hours === null) {
            return;
        }

        $window = $this->currentWindow($branch, $now);
        if ($window === null) {
            throw new OrderRejected('Cabang sedang tutup.');
        }
        $lastOrderAt = $window['close']->copy()->subMinutes((int) $branch->last_order_minutes);
        if ($now->greaterThan($lastOrderAt)) {
            throw new OrderRejected('Pesanan sudah ditutup menjelang jam tutup.');
        }
    }

    /**
     * Ringkasan untuk halaman pelanggan: bisa memesan atau tidak, alasannya, dan jam buka hari ini.
     *
     * @return array{orderable: bool, message: ?string, hours: ?string}
     */
    public function statusFor(Branch $branch, ?Carbon $now = null): array
    {
        $now ??= now();
        try {
            $this->assertOrderable($branch, $now);
            $message = null;
        } catch (OrderRejected $rejection) {
            $message = $rejection->getMessage();
        }

        return ['orderable' => $message === null, 'message' => $message, 'hours' => $this->hoursLabel($branch, $now)];
    }

    /** Mis. "08:00–22:00", atau null bila cabang tidak mengatur jam buka. */
    private function hoursLabel(Branch $branch, Carbon $now): ?string
    {
        if ($branch->open_hours === null) {
            return null;
        }
        $window = $this->windowOn($branch, $now->copy()->startOfDay());
        return $window === null ? 'Libur hari ini' : $window['open']->format('H:i') . '–' . $window['close']->format('H:i');
    }

    public function isHoliday(Branch $branch, Carbon $date): bool
    {
        return in_array($date->toDateString(), (array) $branch->holidays, true);
    }

    /** Jendela buka yang sedang berlaku (hari ini, atau kemarin bila melewati tengah malam). */
    private function currentWindow(Branch $branch, Carbon $now): ?array
    {
        foreach ([$now->copy()->startOfDay(), $now->copy()->subDay()->startOfDay()] as $day) {
            $window = $this->windowOn($branch, $day);
            if ($window !== null && $now->betweenIncluded($window['open'], $window['close'])) {
                return $window;
            }
        }
        return null;
    }

    private function windowOn(Branch $branch, Carbon $day): ?array
    {
        $hours = $branch->open_hours[self::DAYS[$day->dayOfWeekIso]] ?? null;
        if ($hours === null || !empty($hours['closed']) || $this->isHoliday($branch, $day)) {
            return null;
        }
        $open = $day->copy()->setTimeFromTimeString($hours['open']);
        $close = $day->copy()->setTimeFromTimeString($hours['close']);
        if ($close->lessThanOrEqualTo($open)) {
            $close->addDay(); // buka sampai lewat tengah malam
        }
        return ['open' => $open, 'close' => $close];
    }
}

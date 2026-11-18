<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Served = 'served';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Status yang tampil di dashboard dapur: hanya setelah dibayar. */
    public static function active(): array
    {
        return [self::Paid, self::Preparing, self::Served];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** Langkah berikutnya yang boleh dijalankan staf, atau null bila sudah ujung alur. */
    public function next(): ?self
    {
        return match ($this) {
            self::Paid => self::Preparing,
            self::Preparing => self::Served,
            self::Served => self::Completed,
            default => null,
        };
    }

    public function customerHeadline(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu pembayaran',
            self::Paid => 'Pembayaran diterima',
            self::Preparing => 'Sedang disiapkan',
            self::Served => 'Sudah disajikan',
            self::Completed => 'Selesai',
            self::Expired => 'Waktu bayar habis',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

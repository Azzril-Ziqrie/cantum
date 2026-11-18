<?php

namespace App\Services\Kasera;

/** Katalog metode bayar Kasera dan batas nominalnya (dari /v1/payment_methods). */
final class PaymentMethods
{
    public const TYPE_QR = 'qr';
    public const TYPE_BANK = 'payment_code';

    public static function all(): array
    {
        $bank = ['type' => self::TYPE_BANK, 'min' => 10_000, 'max' => 10_000_000];

        return [
            'qris' => ['type' => self::TYPE_QR, 'label' => 'QRIS', 'min' => 1_000, 'max' => 10_000_000],
            'va_bca' => $bank + ['label' => 'BCA Virtual Account'],
            'va_bri' => $bank + ['label' => 'BRI Virtual Account'],
            'va_bni' => $bank + ['label' => 'BNI Virtual Account'],
            'va_mandiri' => $bank + ['label' => 'Mandiri Virtual Account'],
            'va_permata' => $bank + ['label' => 'Permata Virtual Account'],
            'va_cimb' => $bank + ['label' => 'CIMB Niaga Virtual Account'],
            'va_danamon' => $bank + ['label' => 'Danamon Virtual Account'],
            'va_maybank' => $bank + ['label' => 'Maybank Virtual Account'],
        ];
    }

    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }
}

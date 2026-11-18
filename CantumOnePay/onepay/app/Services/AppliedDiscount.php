<?php

namespace App\Services;

/** Hasil perhitungan promo untuk satu keranjang: promo mana dan berapa potongannya. */
final class AppliedDiscount
{
    public function __construct(
        public readonly int $discountId,
        public readonly string $label,
        public readonly int $amount,
    ) {
    }
}

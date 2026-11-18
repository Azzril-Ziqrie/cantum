<?php

namespace App\Services;

/** Aturan harga dan pembayaran yang berlaku untuk satu cabang: milik cabang bila diisi, kalau tidak ikut brand. */
final class EffectiveSettings
{
    /** @param list<string> $enabledMethods */
    public function __construct(
        public readonly float $taxPercent,
        public readonly float $servicePercent,
        public readonly int $roundingTo,
        public readonly array $enabledMethods,
    ) {
    }

    public function allowsMethod(string $method): bool
    {
        return in_array($method, $this->enabledMethods, true);
    }
}

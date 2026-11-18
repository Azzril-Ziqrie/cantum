<?php

namespace App\Services\Kasera;

use App\Services\GatewaySettings;

/** Verifikasi tanda tangan webhook Kasera, dihitung dari body mentah. */
class WebhookSignature
{
    public function __construct(private readonly string $secret, private readonly int $toleranceSeconds = 300)
    {
    }

    public static function fromConfig(): self
    {
        return new self(app(GatewaySettings::class)->webhookSecret(), (int) config('onepay.kasera.webhook_tolerance'));
    }

    /** Header `Kasera-Signature-V1` (utama) atau `Kasera-Signature` (skema lama tanpa timestamp). */
    public function isValidRequest(string $rawBody, string $currentHeader, string $legacyHeader): bool
    {
        return ($currentHeader !== '' && $this->verifyCurrent($rawBody, $currentHeader))
            || ($legacyHeader !== '' && $this->verifyLegacy($rawBody, $legacyHeader));
    }

    /** `t=<unix>,v1=<hex>[,v1=<hex>]`, v1 = HMAC-SHA256(secret, "<t>.<rawBody>"). Salah satu v1 cocok = valid. */
    public function verifyCurrent(string $rawBody, string $header): bool
    {
        [$timestamp, $signatures] = $this->parse($header);
        if ($timestamp === null || $signatures === [] || $this->secret === '') {
            return false;
        }
        if (abs(time() - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    public function verifyLegacy(string $rawBody, string $header): bool
    {
        return $this->secret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $this->secret), trim($header));
    }

    /** @return array{0: ?int, 1: list<string>} */
    private function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $signatures[] = substr($part, 3);
            }
        }
        return [$timestamp, $signatures];
    }
}

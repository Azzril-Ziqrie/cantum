<?php

namespace App\Services\Operations;

/** Hasil satu pemeriksaan kesiapan sistem. */
final readonly class HealthCheck
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    private function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $message,
        public string $advice = '',
    ) {
    }

    public static function ok(string $key, string $label, string $message): self
    {
        return new self($key, $label, self::OK, $message);
    }

    public static function warn(string $key, string $label, string $message, string $advice): self
    {
        return new self($key, $label, self::WARN, $message, $advice);
    }

    public static function fail(string $key, string $label, string $message, string $advice): self
    {
        return new self($key, $label, self::FAIL, $message, $advice);
    }

    /** Di produksi masalah ini berat (gagal); di lokal cukup peringatan. */
    public static function failInProduction(string $key, string $label, string $message, string $advice): self
    {
        return app()->isProduction() ? self::fail($key, $label, $message, $advice) : self::warn($key, $label, $message, $advice);
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'status' => $this->status, 'message' => $this->message, 'advice' => $this->advice];
    }
}

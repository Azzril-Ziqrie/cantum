<?php

namespace App\Services;

use App\Models\GatewaySetting;
use Illuminate\Database\QueryException;

/**
 * Kunci Kasera milik resto. Nilai di database (terenkripsi) menimpa nilai dari .env.
 * Kunci tidak pernah ditulis ke log maupun respons; hanya versi tersamar yang boleh ditampilkan.
 */
class GatewaySettings
{
    private ?GatewaySetting $row = null;
    private bool $loaded = false;

    public function apiKey(): string
    {
        return (string) ($this->row()?->api_key ?: config('onepay.kasera.api_key'));
    }

    public function webhookSecret(): string
    {
        return (string) ($this->row()?->webhook_secret ?: config('onepay.kasera.webhook_secret'));
    }

    /** `kp_test_` = sandbox, `kp_live_` = produksi. */
    public function mode(): string
    {
        return match (true) {
            str_starts_with($this->apiKey(), 'kp_live_') => 'produksi',
            str_starts_with($this->apiKey(), 'kp_test_') && !str_ends_with($this->apiKey(), 'ISI_DISINI') => 'sandbox',
            default => 'belum diatur',
        };
    }

    /**
     * Peringatan bila mode kunci tidak cocok dengan lingkungan: produksi yang masih memakai kunci sandbox
     * tidak menerima uang sungguhan, dan kunci produksi di lingkungan pengembangan memakai uang sungguhan.
     */
    public function modeWarning(): ?string
    {
        $isProduction = app()->isProduction();
        return match (true) {
            $isProduction && $this->mode() === 'sandbox' => 'Aplikasi berjalan di mode produksi tetapi masih memakai kunci sandbox (kp_test_). Pembayaran pelanggan tidak akan sungguhan. Ganti dengan kunci kp_live_ milik resto.',
            $isProduction && $this->mode() === 'belum diatur' => 'Kunci Kasera belum diatur. Pelanggan belum bisa membayar.',
            !$isProduction && $this->mode() === 'produksi' => 'Kunci produksi (kp_live_) dipakai di lingkungan ' . app()->environment() . '. Transaksi yang dibuat di sini memakai uang sungguhan.',
            default => null,
        };
    }

    public function isConfigured(): bool
    {
        return $this->mode() !== 'belum diatur' && $this->webhookSecret() !== '' && $this->webhookSecret() !== 'ISI_DISINI';
    }

    /** Awalan kunci dan empat karakter terakhir, sisanya disamarkan. */
    public function maskedApiKey(): string
    {
        return $this->mask($this->apiKey(), 8);
    }

    public function maskedWebhookSecret(): string
    {
        return $this->mask($this->webhookSecret(), 0);
    }

    /** Nilai kosong berarti "pertahankan yang sekarang". */
    public function save(?string $apiKey, ?string $webhookSecret): void
    {
        $values = array_filter(['api_key' => $apiKey, 'webhook_secret' => $webhookSecret], fn ($v) => $v !== null && $v !== '');
        if ($values === []) {
            return;
        }
        $row = GatewaySetting::query()->firstOrNew(['id' => GatewaySetting::ROW_ID]);
        $row->fill($values)->save();
        $this->row = $row;
        $this->loaded = true;
    }

    private function mask(string $secret, int $visiblePrefix): string
    {
        if ($secret === '') {
            return '';
        }
        return substr($secret, 0, $visiblePrefix) . '••••••••' . substr($secret, -4);
    }

    /** Tabel belum dimigrasi (instalasi lama): pakai .env saja. */
    private function row(): ?GatewaySetting
    {
        if (!$this->loaded) {
            try {
                $this->row = GatewaySetting::query()->find(GatewaySetting::ROW_ID);
            } catch (QueryException) {
                $this->row = null;
            }
            $this->loaded = true;
        }
        return $this->row;
    }
}

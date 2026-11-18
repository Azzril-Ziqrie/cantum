<?php

namespace App\Models;

use App\Services\EffectiveSettings;
use App\Services\Kasera\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'is_active' => 'boolean', 'open_hours' => 'array', 'holidays' => 'array'];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(FloorArea::class);
    }

    /**
     * Denah cabang ini satu bidang datar. Tabel floor_areas dipertahankan, tetapi pengguna tidak
     * melihat area: semua meja dan elemen baru ditaruh di satu area bawaan ini.
     */
    public function defaultArea(): FloorArea
    {
        return $this->areas()->firstOrCreate(['name' => FloorArea::DEFAULT_NAME], ['sort_order' => 0]);
    }

    /**
     * Satu-satunya tempat yang memutuskan pajak, service, pembulatan, dan metode bayar sebuah cabang.
     * Metode cabang selalu irisan dengan yang aktif di brand, jadi tidak pernah melebihi brand.
     */
    public function effectiveSettings(?Brand $brand = null): EffectiveSettings
    {
        $brand ??= Brand::current();
        $brandMethods = $brand->enabledMethods();
        $branchMethods = $this->enabled_methods === null ? null : array_map('trim', explode(',', $this->enabled_methods));

        return new EffectiveSettings(
            taxPercent: (float) ($this->tax_percent ?? $brand->tax_percent),
            servicePercent: (float) ($this->service_percent ?? $brand->service_percent),
            roundingTo: max(1, (int) ($this->rounding_to ?? $brand->rounding_to)),
            enabledMethods: $branchMethods === null ? $brandMethods : array_values(array_intersect($brandMethods, $branchMethods)),
        );
    }

    /**
     * Aturan cabang yang berbeda dari brand, sebagai label singkat untuk dashboard
     * (mis. "Service 0%"). Kosong bila cabang sepenuhnya mengikuti brand.
     *
     * @return list<string>
     */
    public function ruleChips(?Brand $brand = null): array
    {
        $brand ??= Brand::current();
        $effective = $this->effectiveSettings($brand);
        $percent = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',') . '%';

        $chips = [];
        if ($effective->servicePercent !== (float) $brand->service_percent) {
            $chips[] = 'Service ' . $percent($effective->servicePercent);
        }
        if ($effective->taxPercent !== (float) $brand->tax_percent) {
            $chips[] = 'Pajak ' . $percent($effective->taxPercent);
        }
        if ($effective->roundingTo !== (int) $brand->rounding_to) {
            $chips[] = 'Pembulatan Rp ' . number_format($effective->roundingTo, 0, ',', '.');
        }
        if ($effective->enabledMethods !== $brand->enabledMethods()) {
            $labels = array_map(fn (string $code) => PaymentMethods::find($code)['label'] ?? $code, $effective->enabledMethods);
            $chips[] = 'Bayar: ' . implode(', ', $labels);
        }
        return $chips;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

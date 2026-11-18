<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const SCOPE_ORDER = 'order';
    public const SCOPE_ITEMS = 'items';
    public const SCOPE_CATEGORIES = 'categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'all_branches' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'discount_branches');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DiscountTarget::class);
    }

    /** Id item atau kategori yang menjadi sasaran promo, sesuai cakupannya. */
    public function targetIds(): array
    {
        $type = $this->scope === self::SCOPE_CATEGORIES ? 'category' : 'item';
        return $this->targets->where('target_type', $type)->pluck('target_id')->map(fn ($id) => (int) $id)->all();
    }

    public function isAutomatic(): bool
    {
        return $this->code === null;
    }

    public function hasQuotaLeft(): bool
    {
        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    public function appliesToBranch(int $branchId): bool
    {
        return $this->all_branches || $this->branches->contains('id', $branchId);
    }

    /** Ringkasan satu kalimat untuk pelanggan dan daftar admin. */
    public function summary(): string
    {
        $parts = [$this->type === self::TYPE_PERCENT
            ? "Diskon {$this->value}%" . ($this->max_discount ? ' (maks Rp ' . number_format($this->max_discount, 0, ',', '.') . ')' : '')
            : 'Potongan Rp ' . number_format($this->value, 0, ',', '.')];

        if ($this->min_spend > 0) {
            $parts[] = 'min. belanja Rp ' . number_format($this->min_spend, 0, ',', '.');
        }
        if ($this->time_from !== null && $this->time_until !== null) {
            $parts[] = substr($this->time_from, 0, 5) . '–' . substr($this->time_until, 0, 5);
        }
        return implode(', ', $parts);
    }
}

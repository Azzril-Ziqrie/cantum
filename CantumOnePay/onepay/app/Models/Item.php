<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_best_seller' => 'boolean', 'is_bundle' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bundleComponents(): HasMany
    {
        return $this->hasMany(BundleComponent::class, 'bundle_item_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /**
     * Menu yang bisa dipesan di cabang: item dan kategori aktif, tidak ditandai habis, dan sedang dalam jadwal tampil.
     * `effective_price` sudah memakai harga khusus cabang bila ada.
     */
    public function scopeOrderableAt(Builder $query, int $branchId, ?Carbon $now = null): Builder
    {
        return $query
            ->select('items.*')
            ->selectRaw('COALESCE(bi.price_override, items.price) AS effective_price')
            ->join('categories as c', 'c.id', '=', 'items.category_id')
            ->leftJoin('branch_items as bi', fn ($join) => $join->on('bi.item_id', '=', 'items.id')->where('bi.branch_id', $branchId))
            ->where('items.is_active', true)
            ->where('c.is_active', true)
            ->whereRaw('COALESCE(bi.is_available, 1) = 1')
            ->scheduledAt($now ?? now())
            ->orderBy('c.sort_order')->orderBy('c.id')->orderBy('items.sort_order')->orderBy('items.id');
    }

    /** Tanpa jadwal = selalu tampil. Jadwal yang melewati tengah malam (mis. 22:00-02:00) didukung. */
    public function scopeScheduledAt(Builder $query, Carbon $now): Builder
    {
        $time = $now->format('H:i:s');

        return $query->whereRaw(
            '(items.available_from IS NULL OR items.available_until IS NULL
              OR (items.available_from <= items.available_until AND ? >= items.available_from AND ? < items.available_until)
              OR (items.available_from > items.available_until AND (? >= items.available_from OR ? < items.available_until)))',
            [$time, $time, $time, $time]
        );
    }
}

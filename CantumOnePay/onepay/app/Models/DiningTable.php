<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'locked' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(FloorArea::class, 'floor_area_id');
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(24));
    }

    /** Meja yang boleh dipesan: aktif, di cabang aktif. */
    public static function findOrderable(string $token): ?self
    {
        return self::with('branch')
            ->where('token', $token)
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('is_active', true))
            ->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloorArea extends Model
{
    public const DEFAULT_NAME = 'Denah';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(FloorElement::class);
    }
}

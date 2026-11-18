<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloorElement extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function area(): BelongsTo
    {
        return $this->belongsTo(FloorArea::class, 'floor_area_id');
    }
}

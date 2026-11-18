<?php

namespace App\Models;

use App\Enums\PrepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['prep_status' => PrepStatus::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}

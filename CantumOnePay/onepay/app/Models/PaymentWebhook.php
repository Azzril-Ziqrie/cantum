<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['signature_valid' => 'boolean'];
    }
}

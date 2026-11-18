<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kunci gateway milik resto, terenkripsi dengan APP_KEY. Satu baris. */
class GatewaySetting extends Model
{
    public const ROW_ID = 1;

    public const CREATED_AT = null;

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['api_key', 'webhook_secret'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'webhook_secret' => 'encrypted', 'updated_at' => 'datetime'];
    }
}

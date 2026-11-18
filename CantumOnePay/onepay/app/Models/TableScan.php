<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class TableScan extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Pemindaian ulang meja yang sama dalam sesi yang sama dalam rentang ini tidak dihitung lagi. */
    public const DEDUPE_MINUTES = 30;

    public static function record(DiningTable $table, Request $request): void
    {
        $key = 'scan.' . $table->id;
        $last = $request->session()->get($key);
        if ($last !== null && now()->timestamp - $last < self::DEDUPE_MINUTES * 60) {
            return;
        }

        $request->session()->put($key, now()->timestamp);
        self::create(['branch_id' => $table->branch_id, 'dining_table_id' => $table->id, 'scanned_at' => now()]);
    }
}

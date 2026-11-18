<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?User $user, string $action, string $entity, int $entityId, ?string $old, ?string $new): void
    {
        self::create([
            'user_id' => $user?->id, 'action' => $action, 'entity' => $entity,
            'entity_id' => $entityId, 'old_value' => $old, 'new_value' => $new,
        ]);
    }
}

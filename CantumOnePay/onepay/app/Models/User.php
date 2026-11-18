<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /** Cabang yang boleh diakses. Owner melihat semua cabang aktif. */
    public function accessibleBranches(): Collection
    {
        $query = $this->role === Role::Owner ? Branch::query() : $this->branches();
        return $query->where('branches.is_active', true)->orderBy('branches.name')->get();
    }

    public function canAccessBranch(int $branchId): bool
    {
        return $this->accessibleBranches()->contains('id', $branchId);
    }
}

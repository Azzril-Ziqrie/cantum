<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Pengelolaan pengguna dan akses per cabang. Hanya owner. Pengguna dinonaktifkan, tidak pernah dihapus. */
class UserAdminService
{
    public const ROLE_LABELS = [
        'owner' => 'Owner',
        'manager' => 'Manager cabang',
        'cashier' => 'Kasir',
        'kitchen' => 'Dapur / bar',
        'warehouse' => 'Gudang',
    ];

    public function list(User $actor): Collection
    {
        $this->assertOwner($actor);
        return User::query()->with('branches:id,name')->orderByDesc('is_active')->orderBy('name')->get();
    }

    /** @param array{name:string,email:string,password:string,role:string,branch_ids?:array} $data */
    public function create(User $actor, array $data): User
    {
        $this->assertOwner($actor);

        return DB::transaction(function () use ($actor, $data) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                'role' => Role::from($data['role']), 'is_active' => true,
            ]);
            $this->syncBranches($user, $data['branch_ids'] ?? []);
            AuditLog::record($actor, 'user.create', 'user', $user->id, null, $user->role->value);
            return $user;
        });
    }

    /** `password` kosong berarti tidak diubah. */
    public function update(User $actor, User $user, array $data): User
    {
        $this->assertOwner($actor);
        $newRole = Role::from($data['role']);
        if ($newRole !== Role::Owner) {
            $this->assertNotLastOwner($user);
        }

        return DB::transaction(function () use ($actor, $user, $data, $newRole) {
            $oldRole = $user->role->value;
            $attributes = ['name' => $data['name'], 'email' => $data['email'], 'role' => $newRole];
            if (!empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }
            $user->update($attributes);
            $this->syncBranches($user, $data['branch_ids'] ?? []);

            AuditLog::record($actor, 'user.update', 'user', $user->id, $oldRole, $newRole->value);
            if (!empty($data['password'])) {
                AuditLog::record($actor, 'user.password_reset', 'user', $user->id, null, null);
            }
            return $user;
        });
    }

    public function setActive(User $actor, User $user, bool $active): void
    {
        $this->assertOwner($actor);
        if (!$active) {
            $this->assertNotLastOwner($user);
        }
        $user->update(['is_active' => $active]);
        AuditLog::record($actor, 'user.active', 'user', $user->id, $active ? 'nonaktif' : 'aktif', $active ? 'aktif' : 'nonaktif');
    }

    /** Owner aktif terakhir tidak boleh dinonaktifkan atau diturunkan, supaya sistem tidak terkunci. */
    private function assertNotLastOwner(User $user): void
    {
        if ($user->role !== Role::Owner || !$user->is_active) {
            return;
        }
        $otherOwners = User::query()->where('role', Role::Owner)->where('is_active', true)->whereKeyNot($user->id)->count();
        if ($otherOwners === 0) {
            throw new OrderRejected('Owner aktif terakhir tidak boleh dinonaktifkan atau diturunkan.');
        }
    }

    private function syncBranches(User $user, array $branchIds): void
    {
        $user->branches()->sync($user->role === Role::Owner ? [] : array_map('intval', $branchIds));
    }

    private function assertOwner(User $actor): void
    {
        if (!$actor->role->canManageSettings()) {
            throw new OrderRejected('Hanya owner yang boleh mengelola pengguna.');
        }
    }
}

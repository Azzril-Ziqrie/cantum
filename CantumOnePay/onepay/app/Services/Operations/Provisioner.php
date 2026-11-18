<?php

namespace App\Services\Operations;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;

/** Data awal instalasi baru: brand, cabang pertama, dan akun owner. Idempotent: tidak menimpa yang sudah ada. */
class Provisioner
{
    /** @return list<string> daftar yang baru dibuat: 'brand', 'branch', 'owner' */
    public function run(string $brandName, string $branchName, string $ownerName, string $ownerEmail, string $ownerPassword): array
    {
        $created = [];

        if (!Brand::query()->whereKey(1)->exists()) {
            Brand::query()->create(['id' => 1, 'name' => $brandName]);
            $created[] = 'brand';
        }

        if (!Branch::query()->exists()) {
            Branch::query()->create(['name' => $branchName])->defaultArea();
            $created[] = 'branch';
        }

        if (!User::query()->where('role', Role::Owner)->exists()) {
            User::query()->create(['name' => $ownerName, 'email' => $ownerEmail, 'password' => $ownerPassword, 'role' => Role::Owner, 'is_active' => true]);
            $created[] = 'owner';
        }

        return $created;
    }
}

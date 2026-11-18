<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\DiscountTarget;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengelolaan promo oleh admin. Owner mengelola semua promo; manager hanya promo khusus
 * cabang-cabangnya (tidak boleh membuat atau mengubah promo "semua cabang").
 */
class DiscountAdminService
{
    public function listFor(User $user): Collection
    {
        $this->assertRole($user);

        return Discount::query()->with('branches')->orderByDesc('is_active')->orderByDesc('id')->get()
            ->filter(fn (Discount $discount) => $this->canManage($user, $discount))->values();
    }

    /** Cabang yang boleh dipilih untuk promo: owner semua, manager cabangnya. */
    public function branchesFor(User $user): Collection
    {
        return $user->accessibleBranches();
    }

    public function authorizeManage(User $user, Discount $discount): void
    {
        $this->assertRole($user);
        if (!$this->canManage($user, $discount->loadMissing('branches'))) {
            throw new AuthorizationException('Promo ini di luar cabangmu.');
        }
    }

    public function save(User $user, ?Discount $discount, array $data): Discount
    {
        $this->assertRole($user);
        $branchIds = $this->branchIdsFor($user, $data);
        $before = $discount?->exists ? $this->snapshot($discount->loadMissing('branches', 'targets')) : null;
        if ($discount?->exists) {
            $this->authorizeManage($user, $discount);
        }

        $saved = DB::transaction(function () use ($discount, $data, $branchIds) {
            $discount ??= new Discount();
            $discount->fill($this->attributes($data))->save();
            $discount->branches()->sync($data['all_branches'] ? [] : $branchIds);
            $this->syncTargets($discount, $data);
            return $discount;
        });

        $saved->load('branches', 'targets');
        AuditLog::record($user, $before === null ? 'discount.create' : 'discount.update', 'discount', $saved->id, $before, $this->snapshot($saved));
        return $saved;
    }

    public function toggle(User $user, Discount $discount): Discount
    {
        $this->authorizeManage($user, $discount);
        $discount->update(['is_active' => !$discount->is_active]);
        AuditLog::record($user, 'discount.toggle', 'discount', $discount->id, $discount->is_active ? 'nonaktif' : 'aktif', $discount->is_active ? 'aktif' : 'nonaktif');
        return $discount;
    }

    /** Promo yang pernah dipakai tidak dihapus supaya riwayat pesanan tetap utuh; cukup dinonaktifkan. */
    public function delete(User $user, Discount $discount): string
    {
        $this->authorizeManage($user, $discount);
        $wasUsed = $discount->used_count > 0 || Order::query()->where('discount_id', $discount->id)->exists();

        if ($wasUsed) {
            $discount->update(['is_active' => false]);
            AuditLog::record($user, 'discount.deactivate', 'discount', $discount->id, 'aktif', 'nonaktif (sudah pernah dipakai)');
            return 'deactivated';
        }

        $discount->delete();
        AuditLog::record($user, 'discount.delete', 'discount', $discount->id, $discount->name, null);
        return 'deleted';
    }

    private function assertRole(User $user): void
    {
        if (!$user->role->canManageMenu()) {
            throw new AuthorizationException('Tidak punya akses ke promo.');
        }
    }

    private function canManage(User $user, Discount $discount): bool
    {
        if ($user->role->canManageSettings()) {
            return true;
        }
        $accessible = $user->accessibleBranches()->pluck('id');
        $own = $discount->branches->pluck('id');

        return !$discount->all_branches && $own->isNotEmpty() && $own->diff($accessible)->isEmpty();
    }

    /** @return list<int> */
    private function branchIdsFor(User $user, array $data): array
    {
        if ($data['all_branches']) {
            if (!$user->role->canManageSettings()) {
                throw new AuthorizationException('Hanya owner yang boleh membuat promo untuk semua cabang.');
            }
            return [];
        }

        $ids = array_map('intval', $data['branch_ids'] ?? []);
        $allowed = $this->branchesFor($user)->pluck('id')->all();
        if ($ids === [] || array_diff($ids, $allowed) !== []) {
            throw new AuthorizationException('Pilih cabang yang kamu kelola.');
        }
        return $ids;
    }

    private function attributes(array $data): array
    {
        $isPercent = $data['type'] === Discount::TYPE_PERCENT;
        $hasHours = !empty($data['time_from']) && !empty($data['time_until']);

        return [
            'name' => trim($data['name']),
            'code' => filled($data['code'] ?? null) ? Str::upper(trim($data['code'])) : null,
            'type' => $data['type'],
            'value' => (int) $data['value'],
            'max_discount' => $isPercent ? ($data['max_discount'] ?? null) : null,
            'scope' => $data['scope'],
            'min_spend' => (int) ($data['min_spend'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'days' => ($data['days'] ?? []) === [] ? null : array_map('intval', $data['days']),
            'time_from' => $hasHours ? $data['time_from'] : null,
            'time_until' => $hasHours ? $data['time_until'] : null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'all_branches' => $data['all_branches'],
            'is_active' => $data['is_active'],
        ];
    }

    private function syncTargets(Discount $discount, array $data): void
    {
        $discount->targets()->delete();
        if ($data['scope'] === Discount::SCOPE_ORDER) {
            return;
        }

        $type = $data['scope'] === Discount::SCOPE_CATEGORIES ? 'category' : 'item';
        foreach (array_unique(array_map('intval', $data['target_ids'] ?? [])) as $targetId) {
            DiscountTarget::create(['discount_id' => $discount->id, 'target_type' => $type, 'target_id' => $targetId]);
        }
    }

    private function snapshot(Discount $discount): string
    {
        return json_encode([
            'name' => $discount->name, 'code' => $discount->code, 'type' => $discount->type, 'value' => $discount->value,
            'max_discount' => $discount->max_discount, 'scope' => $discount->scope, 'min_spend' => $discount->min_spend,
            'usage_limit' => $discount->usage_limit, 'active' => $discount->is_active,
            'branches' => $discount->all_branches ? 'semua' : $discount->branches->pluck('id')->all(),
            'targets' => $discount->targets->pluck('target_id')->all(),
        ], JSON_UNESCAPED_UNICODE);
    }
}

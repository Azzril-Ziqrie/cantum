<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\FloorElement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Pengelolaan meja dan elemen denah satu cabang. Hanya owner dan manager, dan hanya untuk cabangnya. */
class FloorPlanService
{
    public const MAX_BULK = 60;

    /** Denah datar cabang untuk editor: semua meja (termasuk nonaktif) dan elemen dekoratif. */
    public function layout(User $user, int $branchId): array
    {
        $this->authorize($user, $branchId);

        $tables = DiningTable::query()->where('branch_id', $branchId)->orderBy('label')->get();
        $elements = FloorElement::query()->whereHas('area', fn ($q) => $q->where('branch_id', $branchId))->orderBy('id')->get();

        return [
            'tables' => $tables->map(fn (DiningTable $table) => $table->only('id', 'label', 'seats', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation', 'locked', 'is_active')
                + ['url' => route('menu.show', $table->token)])->all(),
            'elements' => $elements->map->only('id', 'type', 'label', 'pos_x', 'pos_y', 'width', 'height', 'rotation')->all(),
        ];
    }

    /** Salin meja ke sebelahnya dengan nomor berikutnya (A3 jadi A4, atau "A3 salinan" bila tak berakhiran angka). */
    public function duplicateTable(User $user, DiningTable $table): DiningTable
    {
        $this->authorize($user, $table->branch_id);

        return $this->insertTable($table->branch, [
            'label' => $this->nextFreeLabel($table->branch_id, $table->label),
            'seats' => $table->seats, 'shape' => $table->shape,
            'width' => $table->width, 'height' => $table->height, 'rotation' => $table->rotation,
            'pos_x' => $table->pos_x + 24, 'pos_y' => $table->pos_y + 24,
        ]);
    }

    /**
     * Salin seluruh denah (meja dan elemen) dari cabang lain sebagai titik awal.
     * Meja mendapat token baru; pesanan yang sudah ada tidak berubah.
     */
    public function copyLayout(User $user, int $targetBranchId, int $sourceBranchId): int
    {
        $this->authorize($user, $targetBranchId);
        $this->authorize($user, $sourceBranchId);
        if ($targetBranchId === $sourceBranchId) {
            throw new OrderRejected('Pilih cabang sumber yang berbeda.');
        }

        $target = Branch::query()->findOrFail($targetBranchId);
        $tables = DiningTable::query()->where('branch_id', $sourceBranchId)->orderBy('label')->get();
        $elements = FloorElement::query()->whereHas('area', fn ($q) => $q->where('branch_id', $sourceBranchId))->get();
        foreach ($tables as $table) {
            $this->assertLabelFree($targetBranchId, $table->label);
        }

        return DB::transaction(function () use ($target, $tables, $elements) {
            foreach ($tables as $table) {
                $this->insertTable($target, $table->only('label', 'seats', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation', 'locked', 'is_active'));
            }
            foreach ($elements as $element) {
                $target->defaultArea()->elements()->create($element->only('type', 'label', 'pos_x', 'pos_y', 'width', 'height', 'rotation'));
            }
            return $tables->count();
        });
    }

    public function createTable(User $user, Branch $branch, array $attributes): DiningTable
    {
        $this->authorize($user, $branch->id);
        $this->assertLabelFree($branch->id, $attributes['label']);
        return $this->insertTable($branch, $attributes);
    }

    /** Tambah banyak meja sekaligus, mis. A1 sampai A10, tersusun rapi dalam grid. */
    public function createTables(User $user, Branch $branch, string $prefix, int $from, int $to): array
    {
        $this->authorize($user, $branch->id);
        if ($to < $from || $to - $from + 1 > self::MAX_BULK) {
            throw new OrderRejected('Rentang meja tidak valid (maksimal ' . self::MAX_BULK . ' meja).');
        }
        $labels = array_map(fn ($n) => $prefix . $n, range($from, $to));
        foreach ($labels as $label) {
            $this->assertLabelFree($branch->id, $label);
        }

        return DB::transaction(function () use ($branch, $labels) {
            $created = [];
            foreach ($labels as $index => $label) {
                $created[] = $this->insertTable($branch, ['label' => $label, 'pos_x' => 40 + ($index % 6) * 140, 'pos_y' => 120 + intdiv($index, 6) * 140]);
            }
            return $created;
        });
    }

    public function updateTable(User $user, DiningTable $table, array $attributes): DiningTable
    {
        $this->authorize($user, $table->branch_id);
        if (isset($attributes['label']) && $attributes['label'] !== $table->label) {
            $this->assertLabelFree($table->branch_id, $attributes['label']);
        }
        if (array_key_exists('is_active', $attributes) && $attributes['is_active'] !== $table->is_active) {
            AuditLog::record($user, 'table.active', 'table', $table->id, $table->is_active ? 'aktif' : 'nonaktif', $attributes['is_active'] ? 'aktif' : 'nonaktif');
        }
        $table->update($attributes);
        return $table;
    }

    /** Meja yang sudah punya pesanan tidak dihapus permanen, hanya dinonaktifkan. */
    public function deleteTable(User $user, DiningTable $table): string
    {
        $this->authorize($user, $table->branch_id);
        if ($table->orders()->exists()) {
            $this->updateTable($user, $table, ['is_active' => false, 'locked' => true]);
            return 'deactivated';
        }
        $table->delete();
        AuditLog::record($user, 'table.delete', 'table', $table->id, $table->label, null);
        return 'deleted';
    }

    /** Ganti token bila QR bocor: QR lama langsung tidak berlaku. */
    public function regenerateToken(User $user, DiningTable $table): DiningTable
    {
        $this->authorize($user, $table->branch_id);
        $old = substr($table->token, 0, 6) . '…';
        $table->update(['token' => DiningTable::newToken()]);
        AuditLog::record($user, 'table.token', 'table', $table->id, $old, 'diganti');
        return $table;
    }

    public function createElement(User $user, Branch $branch, array $attributes): FloorElement
    {
        $this->authorize($user, $branch->id);
        return $branch->defaultArea()->elements()->create($attributes);
    }

    public function updateElement(User $user, FloorElement $element, array $attributes): FloorElement
    {
        $this->authorize($user, $element->area->branch_id);
        $element->update($attributes);
        return $element;
    }

    public function deleteElement(User $user, FloorElement $element): void
    {
        $this->authorize($user, $element->area->branch_id);
        $element->delete();
    }

    private function insertTable(Branch $branch, array $attributes): DiningTable
    {
        return DiningTable::create($attributes + ['branch_id' => $branch->id, 'floor_area_id' => $branch->defaultArea()->id, 'token' => DiningTable::newToken()]);
    }

    private function nextFreeLabel(int $branchId, string $label): string
    {
        $candidate = $label;
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = preg_match('/^(.*?)(\d+)$/', $candidate, $parts)
                ? $parts[1] . ($parts[2] + 1)
                : $candidate . ' salinan';
            if (!DiningTable::query()->where('branch_id', $branchId)->where('label', $candidate)->exists()) {
                return $candidate;
            }
        }
        throw new OrderRejected('Tidak menemukan nomor meja yang bebas.');
    }

    private function assertLabelFree(int $branchId, string $label): void
    {
        if (DiningTable::query()->where('branch_id', $branchId)->where('label', $label)->exists()) {
            throw new OrderRejected("Meja $label sudah ada di cabang ini.");
        }
    }

    public function authorize(User $user, int $branchId): void
    {
        if (!$user->role->canManageMenu() || !$user->canAccessBranch($branchId)) {
            throw new OrderRejected('Tidak punya akses.');
        }
    }
}

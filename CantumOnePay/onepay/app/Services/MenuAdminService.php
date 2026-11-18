<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Pengelolaan menu. Data induk (menu, kategori, modifier, harga dasar) hanya milik owner;
 * ketersediaan dan harga khusus cabang boleh diubah manager untuk cabangnya sendiri.
 * Setiap perubahan harga dan ketersediaan masuk audit log.
 */
class MenuAdminService
{
    public const PRICE_ROUND_STEP = 100;
    public const MAX_IMPORT_ROWS = 1000;
    public const CSV_COLUMNS = ['category', 'name', 'description', 'price', 'cost', 'station', 'best_seller', 'active', 'sort_order'];

    /* ------------------------------------------------------------------ kategori */

    public function saveCategory(User $user, ?Category $category, array $attributes): Category
    {
        $this->assertOwner($user);
        $category ??= new Category(['sort_order' => (int) Category::query()->max('sort_order') + 1]);
        $category->fill($attributes)->save();
        return $category;
    }

    /** Kategori yang masih punya menu tidak dihapus, hanya dinonaktifkan. */
    public function deleteCategory(User $user, Category $category): string
    {
        $this->assertOwner($user);
        if ($category->items()->exists()) {
            $category->update(['is_active' => false]);
            return 'deactivated';
        }
        $category->delete();
        return 'deleted';
    }

    /* ------------------------------------------------------------------ menu */

    /** @param list<array{item_id:int,qty:int}> $components isi paket (hanya bila is_bundle) */
    public function saveItem(User $user, ?Item $item, array $attributes, array $components = [], ?UploadedFile $photo = null): Item
    {
        $this->assertOwner($user);
        $removePhoto = (bool) ($attributes['remove_photo'] ?? false);
        unset($attributes['remove_photo']);

        return DB::transaction(function () use ($user, $item, $attributes, $components, $photo, $removePhoto) {
            $item ??= new Item(['sort_order' => (int) Item::query()->max('sort_order') + 1]);
            $before = $item->exists ? $item->only('price', 'cost') : [];

            $item->fill($attributes)->save();
            $this->replacePhoto($item, $photo, $removePhoto);
            $this->syncComponents($item, $item->is_bundle ? $components : []);
            $this->auditPriceChange($user, $item, $before);

            return $item;
        });
    }

    /** Menu yang pernah dipesan tidak dihapus permanen supaya riwayat tetap utuh; cukup dinonaktifkan. */
    public function deleteItem(User $user, Item $item): string
    {
        $this->assertOwner($user);
        if (OrderItem::query()->where('item_id', $item->id)->exists()) {
            $item->update(['is_active' => false]);
            return 'deactivated';
        }
        $this->deletePhoto($item);
        $item->delete();
        return 'deleted';
    }

    private function replacePhoto(Item $item, ?UploadedFile $photo, bool $remove): void
    {
        if ($photo === null && !$remove) {
            return;
        }
        $this->deletePhoto($item);
        $item->update(['photo' => $photo?->store('menu', 'public')]);
    }

    private function deletePhoto(Item $item): void
    {
        if ($item->photo) {
            Storage::disk('public')->delete($item->photo);
        }
    }

    private function syncComponents(Item $item, array $components): void
    {
        $item->bundleComponents()->delete();
        foreach ($components as $component) {
            $componentId = (int) $component['item_id'];
            if ($componentId === $item->id) {
                throw new OrderRejected('Paket tidak boleh berisi dirinya sendiri.');
            }
            if (Item::query()->whereKey($componentId)->where('is_bundle', false)->doesntExist()) {
                throw new OrderRejected('Isi paket harus berupa menu biasa.');
            }
            $item->bundleComponents()->updateOrCreate(['component_item_id' => $componentId], ['qty' => max(1, (int) $component['qty'])]);
        }
    }

    private function auditPriceChange(User $user, Item $item, array $before): void
    {
        foreach (['price', 'cost'] as $field) {
            $old = $before[$field] ?? null;
            if ($old !== null && (int) $old !== (int) $item->{$field}) {
                AuditLog::record($user, "item.$field", 'item', $item->id, (string) $old, (string) $item->{$field});
            }
        }
    }

    /* ------------------------------------------------------------------ modifier */

    public function saveGroup(User $user, Item $item, ?ModifierGroup $group, array $attributes): ModifierGroup
    {
        $this->assertOwner($user);
        $group ??= new ModifierGroup(['item_id' => $item->id, 'sort_order' => (int) $item->modifierGroups()->max('sort_order') + 1]);
        $group->fill($attributes)->save();
        return $group;
    }

    public function deleteGroup(User $user, ModifierGroup $group): void
    {
        $this->assertOwner($user);
        $group->delete();
    }

    public function saveModifier(User $user, ModifierGroup $group, ?Modifier $modifier, array $attributes): Modifier
    {
        $this->assertOwner($user);
        $modifier ??= new Modifier(['modifier_group_id' => $group->id, 'sort_order' => (int) $group->modifiers()->max('sort_order') + 1]);
        $modifier->fill($attributes)->save();
        return $modifier;
    }

    public function deleteModifier(User $user, Modifier $modifier): void
    {
        $this->assertOwner($user);
        $modifier->delete();
    }

    /* ------------------------------------------------------------------ per cabang */

    /** Tombol "habis" dan harga khusus cabang. $priceOverride null = pakai harga dasar. */
    public function setBranchItem(User $user, Branch $branch, Item $item, bool $available, ?int $priceOverride): void
    {
        $this->assertBranchAccess($user, $branch->id);
        $current = DB::table('branch_items')->where(['branch_id' => $branch->id, 'item_id' => $item->id])->first();
        $this->upsertBranchItem($branch->id, $item->id, $available, $priceOverride);

        $wasAvailable = $current === null ? true : (bool) $current->is_available;
        if ($wasAvailable !== $available) {
            AuditLog::record($user, 'item.availability', 'item', $item->id, null, "cabang {$branch->id}: " . ($available ? 'tersedia' : 'habis'));
        }
        if (($current->price_override ?? null) !== $priceOverride) {
            AuditLog::record($user, 'item.branch_price', 'item', $item->id, (string) ($current->price_override ?? 'dasar'), "cabang {$branch->id}: " . ($priceOverride ?? 'dasar'));
        }
    }

    /** Salin ketersediaan dan harga khusus dari cabang lain. Menggantikan pengaturan cabang tujuan. */
    public function copyBranchSettings(User $user, Branch $target, Branch $source): int
    {
        $this->assertBranchAccess($user, $target->id);
        $this->assertBranchAccess($user, $source->id);
        if ($target->id === $source->id) {
            throw new OrderRejected('Cabang asal dan tujuan sama.');
        }

        return DB::transaction(function () use ($user, $target, $source) {
            DB::table('branch_items')->where('branch_id', $target->id)->delete();
            $rows = DB::table('branch_items')->where('branch_id', $source->id)->get();
            foreach ($rows as $row) {
                $this->upsertBranchItem($target->id, $row->item_id, (bool) $row->is_available, $row->price_override);
            }
            AuditLog::record($user, 'branch.copy_menu_settings', 'branch', $target->id, null, "disalin dari cabang {$source->id} ({$rows->count()} menu)");
            return $rows->count();
        });
    }

    private function upsertBranchItem(int $branchId, int $itemId, bool $available, ?int $priceOverride): void
    {
        DB::table('branch_items')->upsert(
            [['branch_id' => $branchId, 'item_id' => $itemId, 'is_available' => $available, 'price_override' => $priceOverride]],
            ['branch_id', 'item_id'],
            ['is_available', 'price_override']
        );
    }

    /* ------------------------------------------------------------------ ubah massal harga */

    /** @return list<array{id:int,name:string,old:int,new:int}> */
    public function previewBulkPrice(User $user, ?int $categoryId, string $mode, int $value): array
    {
        $this->assertOwner($user);

        return Item::query()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('category_id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Item $item) => ['id' => $item->id, 'name' => $item->name, 'old' => $item->price, 'new' => $this->adjustedPrice($item->price, $mode, $value)])
            ->filter(fn ($row) => $row['old'] !== $row['new'])
            ->values()->all();
    }

    public function applyBulkPrice(User $user, ?int $categoryId, string $mode, int $value): int
    {
        $changes = $this->previewBulkPrice($user, $categoryId, $mode, $value);

        DB::transaction(function () use ($user, $changes) {
            foreach ($changes as $change) {
                Item::query()->whereKey($change['id'])->update(['price' => $change['new']]);
                AuditLog::record($user, 'item.price', 'item', $change['id'], (string) $change['old'], (string) $change['new']);
            }
        });
        return count($changes);
    }

    /** Persen dibulatkan ke kelipatan Rp 100; nominal dipakai apa adanya. Harga tidak pernah di bawah nol. */
    private function adjustedPrice(int $price, string $mode, int $value): int
    {
        $new = match ($mode) {
            'percent' => (int) (round($price * (1 + $value / 100) / self::PRICE_ROUND_STEP) * self::PRICE_ROUND_STEP),
            'amount' => $price + $value,
            default => throw new OrderRejected('Mode ubah harga tidak dikenal.'),
        };
        return max(0, $new);
    }

    /* ------------------------------------------------------------------ CSV */

    /** @return iterable<list<string|int>> baris pertama adalah header */
    public function exportRows(User $user): iterable
    {
        if (!$user->role->canManageMenu()) {
            throw new OrderRejected('Tidak punya akses.');
        }

        yield self::CSV_COLUMNS;
        foreach (Item::query()->with('category')->orderBy('category_id')->orderBy('sort_order')->orderBy('id')->cursor() as $item) {
            yield [
                $item->category->name, $item->name, $item->description, $item->price, $item->cost, $item->station,
                (int) $item->is_best_seller, (int) $item->is_active, $item->sort_order,
            ];
        }
    }

    /**
     * Impor semua-atau-tidak-sama-sekali. Menu dicocokkan dengan nama kategori + nama menu: ada = diperbarui, belum = dibuat.
     * @return array{created:int,updated:int}
     */
    public function importCsv(User $user, UploadedFile $file): array
    {
        $this->assertOwner($user);
        $rows = $this->parseCsv($file);
        $errors = $this->validationErrors($rows);
        if ($errors !== []) {
            throw new OrderRejected(implode(' ', array_slice($errors, 0, 5)));
        }

        return DB::transaction(function () use ($user, $rows) {
            $counts = ['created' => 0, 'updated' => 0];
            foreach ($rows as $row) {
                $counts[$this->upsertImportedItem($user, $row)]++;
            }
            return $counts;
        });
    }

    /** @return list<array<string,string>> */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $first = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), (array) fgetcsv($handle, 0, $delimiter));
        if (array_diff(['category', 'name', 'price'], $header) !== []) {
            fclose($handle);
            throw new OrderRejected('Header CSV harus memuat kolom: category, name, price.');
        }

        $rows = [];
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($cells === [null]) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }
        fclose($handle);

        if (count($rows) > self::MAX_IMPORT_ROWS) {
            throw new OrderRejected('Maksimal ' . self::MAX_IMPORT_ROWS . ' baris per impor.');
        }
        return $rows;
    }

    private function validationErrors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, [
                'category' => ['required', 'string', 'max:80'],
                'name' => ['required', 'string', 'max:120'],
                'price' => ['required', 'integer', 'min:0'],
                'cost' => ['nullable', 'integer', 'min:0'],
                'station' => ['nullable', 'in:kitchen,bar'],
                'sort_order' => ['nullable', 'integer'],
            ]);
            foreach ($validator->errors()->all() as $message) {
                $errors[] = 'Baris ' . ($index + 2) . ': ' . $message;
            }
        }
        return $errors;
    }

    /** @return 'created'|'updated' */
    private function upsertImportedItem(User $user, array $row): string
    {
        $category = Category::query()->firstOrCreate(['name' => trim($row['category'])], ['sort_order' => (int) Category::query()->max('sort_order') + 1]);
        $item = Item::query()->where('category_id', $category->id)->where('name', trim($row['name']))->first();
        $attributes = [
            'description' => ($row['description'] ?? '') ?: null,
            'price' => (int) $row['price'],
            'cost' => (int) ($row['cost'] ?? 0),
            'station' => ($row['station'] ?? '') ?: 'kitchen',
            'is_best_seller' => $this->truthy($row['best_seller'] ?? '0'),
            'is_active' => $this->truthy($row['active'] ?? '1'),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];

        $before = $item?->only('price', 'cost') ?? [];
        $item ??= new Item(['category_id' => $category->id, 'name' => trim($row['name'])]);
        $existed = $item->exists;
        $item->fill($attributes)->save();
        $this->auditPriceChange($user, $item, $before);

        return $existed ? 'updated' : 'created';
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'ya', 'y', 'yes', 'aktif'], true);
    }

    /* ------------------------------------------------------------------ hak akses */

    private function assertOwner(User $user): void
    {
        if ($user->role !== Role::Owner) {
            throw new OrderRejected('Hanya owner yang boleh mengubah menu dan harga dasar.');
        }
    }

    private function assertBranchAccess(User $user, int $branchId): void
    {
        if (!$user->role->canManageMenu() || !$user->canAccessBranch($branchId)) {
            throw new OrderRejected('Tidak punya akses ke cabang ini.');
        }
    }
}

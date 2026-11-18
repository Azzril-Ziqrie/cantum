<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CsvCell;
use App\Http\Requests\Admin\BranchItemRequest;
use App\Http\Requests\Admin\BulkPriceRequest;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\ImportRequest;
use App\Http\Requests\Admin\ItemRequest;
use App\Http\Requests\Admin\ModifierGroupRequest;
use App\Http\Requests\Admin\ModifierRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Item;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Services\MenuAdminService;
use App\Services\OrderRejected;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Halaman admin menu. Aturan dan hak akses ada di MenuAdminService; controller hanya menerjemahkan HTTP. */
class MenuAdminController extends Controller
{
    private const TABS = ['items', 'categories', 'branch', 'bulk', 'csv'];

    public function __construct(private readonly MenuAdminService $menu)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = $user->accessibleBranches();
        $branch = $branches->firstWhere('id', (int) $request->query('branch')) ?? $branches->first();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'items';

        return view('admin.menu.index', [
            'brand' => Brand::current(),
            'tab' => $tab,
            'isOwner' => $user->role === \App\Enums\Role::Owner,
            'categories' => Category::query()->withCount('items')->orderBy('sort_order')->orderBy('id')->get(),
            'items' => Item::query()->with('category')->orderBy('category_id')->orderBy('sort_order')->orderBy('id')->get(),
            'branches' => $branches,
            'branch' => $branch,
            'branchItems' => $branch ? $this->branchSettings($branch) : [],
            'preview' => session('bulk_preview'),
        ]);
    }

    public function editItem(Item $item): View
    {
        return view('admin.menu.item', [
            'brand' => Brand::current(),
            'item' => $item->load('modifierGroups.modifiers', 'bundleComponents'),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'plainItems' => Item::query()->where('is_bundle', false)->where('id', '!=', $item->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function newItem(): View
    {
        return $this->editItem(new Item(['station' => 'kitchen', 'is_active' => true]));
    }

    /* ---- kategori ---- */

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveCategory($request->user(), null, $request->validated()), 'Kategori ditambahkan.', 'categories');
    }

    public function updateCategory(CategoryRequest $request, Category $category): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveCategory($request->user(), $category, $request->validated()), 'Kategori disimpan.', 'categories');
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        return $this->done(fn () => $this->menu->deleteCategory($request->user(), $category), 'Kategori dihapus (atau dinonaktifkan bila masih punya menu).', 'categories');
    }

    /* ---- menu ---- */

    public function storeItem(ItemRequest $request): RedirectResponse
    {
        $item = null;
        $response = $this->done(function () use ($request, &$item) {
            $item = $this->menu->saveItem($request->user(), null, $request->itemAttributes(), $request->components(), $request->file('photo'));
        }, 'Menu ditambahkan.');

        return $item ? redirect()->route('admin.menu.items.edit', $item)->with('status', 'Menu ditambahkan. Tambahkan pilihan modifier di bawah.') : $response;
    }

    public function updateItem(ItemRequest $request, Item $item): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveItem($request->user(), $item, $request->itemAttributes(), $request->components(), $request->file('photo')), 'Menu disimpan.');
    }

    public function destroyItem(Request $request, Item $item): RedirectResponse
    {
        $result = null;
        $this->done(function () use ($request, $item, &$result) {
            $result = $this->menu->deleteItem($request->user(), $item);
        }, '');

        return redirect()->route('admin.menu')->with('status', $result === 'deactivated' ? 'Menu sudah pernah dipesan, jadi dinonaktifkan.' : 'Menu dihapus.');
    }

    /* ---- modifier ---- */

    public function storeGroup(ModifierGroupRequest $request, Item $item): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveGroup($request->user(), $item, null, $request->validated()), 'Grup pilihan ditambahkan.');
    }

    public function updateGroup(ModifierGroupRequest $request, ModifierGroup $group): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveGroup($request->user(), $group->item, $group, $request->validated()), 'Grup pilihan disimpan.');
    }

    public function destroyGroup(Request $request, ModifierGroup $group): RedirectResponse
    {
        return $this->done(fn () => $this->menu->deleteGroup($request->user(), $group), 'Grup pilihan dihapus.');
    }

    public function storeModifier(ModifierRequest $request, ModifierGroup $group): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveModifier($request->user(), $group, null, $request->validated()), 'Opsi ditambahkan.');
    }

    public function updateModifier(ModifierRequest $request, Modifier $modifier): RedirectResponse
    {
        return $this->done(fn () => $this->menu->saveModifier($request->user(), $modifier->group, $modifier, $request->validated()), 'Opsi disimpan.');
    }

    public function destroyModifier(Request $request, Modifier $modifier): RedirectResponse
    {
        return $this->done(fn () => $this->menu->deleteModifier($request->user(), $modifier), 'Opsi dihapus.');
    }

    /* ---- per cabang ---- */

    /** Dipanggil lewat fetch dari tombol "habis" (JSON) atau form harga cabang (redirect). */
    public function updateBranchItem(BranchItemRequest $request, Branch $branch, Item $item): JsonResponse|RedirectResponse
    {
        try {
            $this->menu->setBranchItem($request->user(), $branch, $item, $request->boolean('is_available'), $request->validated('price_override'));
        } catch (OrderRejected $rejection) {
            return $request->expectsJson()
                ? response()->json(['error' => $rejection->getMessage()], 422)
                : back()->withErrors(['menu' => $rejection->getMessage()]);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Pengaturan cabang disimpan.');
    }

    public function copyBranch(Request $request, Branch $branch): RedirectResponse
    {
        $source = Branch::query()->findOrFail((int) $request->input('source'));
        $count = 0;
        return $this->done(function () use ($request, $branch, $source, &$count) {
            $count = $this->menu->copyBranchSettings($request->user(), $branch, $source);
        }, 'Pengaturan disalin dari ' . $source->name . '.', 'branch', ['branch' => $branch->id]);
    }

    /* ---- ubah massal harga ---- */

    public function previewBulk(BulkPriceRequest $request): RedirectResponse
    {
        $rows = [];
        $response = $this->done(function () use ($request, &$rows) {
            $rows = $this->menu->previewBulkPrice($request->user(), $request->validated('category_id'), $request->validated('mode'), (int) $request->validated('value'));
        }, '', 'bulk');

        return $response->with('bulk_preview', ['rows' => $rows, 'input' => $request->validated()]);
    }

    public function applyBulk(BulkPriceRequest $request): RedirectResponse
    {
        $changed = 0;
        $response = $this->done(function () use ($request, &$changed) {
            $changed = $this->menu->applyBulkPrice($request->user(), $request->validated('category_id'), $request->validated('mode'), (int) $request->validated('value'));
        }, '', 'bulk');

        return $response->with('status', "$changed harga menu diubah.");
    }

    /* ---- CSV ---- */

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->menu->exportRows($request->user());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(CsvCell::safe(...), $row));
            }
            fclose($out);
        }, 'menu-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $counts = ['created' => 0, 'updated' => 0];
        $response = $this->done(function () use ($request, &$counts) {
            $counts = $this->menu->importCsv($request->user(), $request->file('file'));
        }, '', 'csv');

        return $response->with('status', "Impor selesai: {$counts['created']} menu baru, {$counts['updated']} diperbarui.");
    }

    /* ---- util ---- */

    /** Jalankan aksi service; penolakan aturan dikembalikan ke form sebagai pesan, bukan error 500. */
    private function done(callable $action, string $message, ?string $tab = null, array $query = []): RedirectResponse
    {
        $back = $tab ? redirect()->route('admin.menu', ['tab' => $tab] + $query) : back();
        try {
            $action();
        } catch (OrderRejected $rejection) {
            return $back->withErrors(['menu' => $rejection->getMessage()])->withInput();
        }
        return $message === '' ? $back : $back->with('status', $message);
    }

    /** Ketersediaan dan harga khusus tiap menu di satu cabang, diindeks per item_id. */
    private function branchSettings(Branch $branch): array
    {
        return \Illuminate\Support\Facades\DB::table('branch_items')->where('branch_id', $branch->id)->get()->keyBy('item_id')->all();
    }
}

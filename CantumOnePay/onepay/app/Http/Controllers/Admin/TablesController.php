<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\FloorElement;
use App\Services\FloorPlanService;
use App\Services\OrderRejected;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Halaman dan API editor meja dan denah. Hak akses dicek di FloorPlanService. */
class TablesController extends Controller
{
    private const TABLE_RULES = [
        'label' => ['required', 'string', 'max:30'],
        'seats' => ['integer', 'min:1', 'max:50'],
        'shape' => ['in:square,round,long'],
        'pos_x' => ['integer', 'min:0', 'max:900'],
        'pos_y' => ['integer', 'min:0', 'max:620'],
        'width' => ['integer', 'min:30', 'max:400'],
        'height' => ['integer', 'min:30', 'max:400'],
        'rotation' => ['integer', 'min:-360', 'max:360'],
        'locked' => ['boolean'],
        'is_active' => ['boolean'],
    ];

    private const ELEMENT_RULES = [
        'type' => ['required', 'in:wall,door,cashier,bar'],
        'label' => ['nullable', 'string', 'max:30'],
        'pos_x' => ['integer', 'min:0', 'max:900'],
        'pos_y' => ['integer', 'min:0', 'max:620'],
        'width' => ['integer', 'min:10', 'max:900'],
        'height' => ['integer', 'min:8', 'max:620'],
        'rotation' => ['integer', 'min:-360', 'max:360'],
    ];

    public function __construct(private readonly FloorPlanService $plan)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role->canManageMenu(), 403);

        $branches = $user->accessibleBranches();
        $branchId = (int) $request->query('branch', $branches->first()?->id);

        return view('admin.tables', [
            'brand' => Brand::current(),
            'branches' => $branches,
            'config' => [
                'branch' => $branches->contains('id', $branchId) ? $branchId : $branches->first()?->id,
                'layoutUrl' => route('admin.tables.layout'),
                'base' => url('admin/tables'),
                'sheetUrl' => route('admin.tables.sheet'),
                'branches' => $branches->map->only('id', 'name')->values(),
            ],
        ]);
    }

    public function layout(Request $request): JsonResponse
    {
        return $this->guarded(fn () => $this->plan->layout($request->user(), (int) $request->query('branch')));
    }

    public function copyLayout(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'source_branch_id' => ['required', 'integer']]);

        return $this->guarded(fn () => ['copied' => $this->plan->copyLayout($request->user(), $data['branch_id'], $data['source_branch_id'])]);
    }

    public function storeTable(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate(self::TABLE_RULES);

        return $this->guarded(fn () => $this->tablePayload($this->plan->createTable($request->user(), $branch, $data)));
    }

    public function storeTables(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate(['prefix' => ['present', 'nullable', 'string', 'max:10'], 'from' => ['required', 'integer', 'min:0'], 'to' => ['required', 'integer', 'min:0']]);

        return $this->guarded(fn () => ['tables' => array_map(
            fn (DiningTable $table) => $this->tablePayload($table),
            $this->plan->createTables($request->user(), $branch, (string) $data['prefix'], $data['from'], $data['to'])
        )]);
    }

    public function updateTable(Request $request, DiningTable $table): JsonResponse
    {
        $data = $request->validate(array_map(fn ($rules) => ['sometimes', ...array_diff($rules, ['required'])], self::TABLE_RULES));

        return $this->guarded(fn () => $this->tablePayload($this->plan->updateTable($request->user(), $table, $data)));
    }

    public function duplicateTable(Request $request, DiningTable $table): JsonResponse
    {
        return $this->guarded(fn () => $this->tablePayload($this->plan->duplicateTable($request->user(), $table)));
    }

    public function destroyTable(Request $request, DiningTable $table): JsonResponse
    {
        return $this->guarded(fn () => ['result' => $this->plan->deleteTable($request->user(), $table)]);
    }

    public function regenerateToken(Request $request, DiningTable $table): JsonResponse
    {
        return $this->guarded(fn () => $this->tablePayload($this->plan->regenerateToken($request->user(), $table)));
    }

    public function storeElement(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate(self::ELEMENT_RULES);

        return $this->guarded(fn () => $this->plan->createElement($request->user(), $branch, $data)->only('id', 'type', 'label', 'pos_x', 'pos_y', 'width', 'height', 'rotation'));
    }

    public function updateElement(Request $request, FloorElement $element): JsonResponse
    {
        $data = $request->validate(array_map(fn ($rules) => ['sometimes', ...array_diff($rules, ['required'])], self::ELEMENT_RULES));

        return $this->guarded(fn () => $this->plan->updateElement($request->user(), $element, $data)->only('id', 'type', 'label', 'pos_x', 'pos_y', 'width', 'height', 'rotation'));
    }

    public function destroyElement(Request $request, FloorElement $element): JsonResponse
    {
        return $this->guarded(function () use ($request, $element) {
            $this->plan->deleteElement($request->user(), $element);
            return ['ok' => true];
        });
    }

    private function tablePayload(DiningTable $table): array
    {
        return $table->only('id', 'label', 'seats', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation', 'locked', 'is_active');
    }

    /** Pelanggaran aturan dibalas 422 dengan pesan yang aman ditampilkan. */
    private function guarded(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (OrderRejected $rejection) {
            return response()->json(['error' => $rejection->getMessage()], 422);
        }
    }
}

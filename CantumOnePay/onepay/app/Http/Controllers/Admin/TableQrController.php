<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Services\FloorPlanService;
use App\Services\OrderRejected;
use App\Services\TableQrService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** QR per meja (SVG polos atau stiker) dan lembar stiker cetak. */
class TableQrController extends Controller
{
    public function __construct(private readonly FloorPlanService $plan, private readonly TableQrService $qr)
    {
    }

    public function code(Request $request, DiningTable $table): Response
    {
        $this->authorizeOrForbid($request, $table->branch_id);

        return $this->svgResponse($this->qr->svg($table), 'qr-meja-' . $table->label);
    }

    public function sticker(Request $request, DiningTable $table): Response
    {
        $this->authorizeOrForbid($request, $table->branch_id);

        return $this->svgResponse($this->qr->stickerSvg($table, Brand::current()->name), 'stiker-meja-' . $table->label);
    }

    /** Lembar stiker banyak meja sekaligus. ?branch=ID, opsional ?ids[]=meja yang dipilih. */
    public function sheet(Request $request): View
    {
        $branchId = (int) $request->query('branch');
        $this->authorizeOrForbid($request, $branchId);

        $tables = DiningTable::query()->where('branch_id', $branchId)->where('is_active', true)
            ->when($request->query('ids'), fn ($q, $ids) => $q->whereIn('id', (array) $ids))
            ->orderBy('label')->get();

        return view('admin.qr-sheet', [
            'brand' => Brand::current(),
            'stickers' => $tables->map(fn (DiningTable $table) => [
                'label' => $table->label,
                'svg' => $this->qr->stickerSvg($table, Brand::current()->name),
            ]),
        ]);
    }

    /** Halaman dan unduhan menjawab 403 (bukan JSON) bila pengguna tak berhak atas cabang itu. */
    private function authorizeOrForbid(Request $request, int $branchId): void
    {
        try {
            $this->plan->authorize($request->user(), $branchId);
        } catch (OrderRejected) {
            abort(403);
        }
    }

    private function svgResponse(string $svg, string $filename): Response
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $filename) . '.svg"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

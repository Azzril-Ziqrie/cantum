<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\Reports\ReportCsv;
use App\Services\Reports\ReportFilter;
use App\Services\Reports\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan penjualan untuk owner dan manager. Hak akses dicek di ReportService (403 bila ditolak). */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        [$filter, $report] = $this->load($request);

        return view('admin.reports.index', $this->viewData($request, $filter) + ['report' => $report]);
    }

    /** Halaman siap cetak; "Simpan sebagai PDF" lewat dialog cetak peramban (tanpa pustaka PDF di server). */
    public function print(Request $request): View
    {
        [$filter, $report] = $this->load($request);

        return view('admin.reports.print', $this->viewData($request, $filter) + ['report' => $report]);
    }

    public function csv(Request $request): StreamedResponse
    {
        [$filter, $report] = $this->load($request);
        $branchName = $this->branchName($filter);

        return response()->streamDownload(
            fn () => (new ReportCsv())->write(fopen('php://output', 'w'), $report, $filter, $branchName),
            sprintf('laporan-penjualan-%s_%s.csv', $filter->from->toDateString(), $filter->to->toDateString()),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function close(Request $request): View
    {
        $close = $this->reports->dailyClose($request->user(), $this->branchParam($request), $this->dateParam($request, 'date'));

        return view('admin.reports.close', [
            'brand' => Brand::current(),
            'close' => $close,
            'branches' => $request->user()->accessibleBranches(),
            'branchId' => $close['filter']->branchId,
            'branchName' => $this->branchName($close['filter']),
        ]);
    }

    public function closeCsv(Request $request): StreamedResponse
    {
        $close = $this->reports->dailyClose($request->user(), $this->branchParam($request), $this->dateParam($request, 'date'));
        $branchName = $this->branchName($close['filter']);

        return response()->streamDownload(
            fn () => (new ReportCsv())->writeDailyClose(fopen('php://output', 'w'), $close, $branchName),
            'tutup-harian-' . $close['date']->toDateString() . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** @return array{0: ReportFilter, 1: array} */
    private function load(Request $request): array
    {
        $filter = $this->reports->filterFor($request->user(), $this->branchParam($request), $this->dateParam($request, 'from'), $this->dateParam($request, 'to'));

        return [$filter, $this->reports->build($request->user(), $filter)];
    }

    private function viewData(Request $request, ReportFilter $filter): array
    {
        return [
            'brand' => Brand::current(),
            'filter' => $filter,
            'branches' => $request->user()->accessibleBranches(),
            'branchName' => $this->branchName($filter),
        ];
    }

    private function branchName(ReportFilter $filter): string
    {
        if ($filter->branchId === null) {
            return $filter->isMultiBranch() ? 'Semua cabang' : (string) Branch::query()->whereIn('id', $filter->branchIds)->value('name');
        }
        return (string) Branch::query()->whereKey($filter->branchId)->value('name');
    }

    private function branchParam(Request $request): ?string
    {
        return is_scalar($request->query('branch')) ? (string) $request->query('branch') : null;
    }

    private function dateParam(Request $request, string $key): ?string
    {
        return is_scalar($request->query($key)) ? (string) $request->query($key) : null;
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Http\Requests\ReportRequest;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Support\ReportCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(
        ReportRequest $request,
        ReportService $service,
    ): View {
        $filters = $request->filters();

        return view('reports.index', [
            ...$service->build($filters),
            'reportTypes' => ReportCatalog::all(),
            ...$service->filterOptions($filters['report']),
        ]);
    }

    public function downloadPdf(
        ReportRequest $request,
        ReportService $service,
        AuditLogger $auditLogger,
    ): Response {
        $filters = $request->filters();
        $data = $service->build($filters);

        $auditLogger->log(
            event: 'report_downloaded',
            module: 'report',
            newValues: [
                'format' => 'pdf',
                'report' => $filters['report'],
                'period' => $data['periodLabel'],
                'row_count' => count($data['rows']),
            ],
            request: $request,
        );

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($service->filename($filters['report']));
    }

    public function downloadExcel(
        ReportRequest $request,
        ReportService $service,
        AuditLogger $auditLogger,
    ): BinaryFileResponse {
        $filters = $request->filters();
        $data = $service->build($filters);

        $auditLogger->log(
            event: 'report_downloaded',
            module: 'report',
            newValues: [
                'format' => 'xlsx',
                'report' => $filters['report'],
                'period' => $data['periodLabel'],
                'row_count' => count($data['rows']),
            ],
            request: $request,
        );

        return Excel::download(
            new ReportExport($data),
            $service->filename($filters['report'], 'xlsx'),
            ExcelWriter::XLSX,
        );
    }
}

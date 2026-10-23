<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PresentsEndOfTermReports;
use App\Http\Controllers\Controller;
use App\Services\Reports\EndOfTermReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EndOfTermReportController extends Controller
{
    use PresentsEndOfTermReports;

    protected EndOfTermReportService $reports;

    public function __construct(?EndOfTermReportService $reports = null)
    {
        $this->reports = $reports ?? new EndOfTermReportService();
    }

    public function index(Request $request)
    {
        $reports = $this->sharedReports(null, 'admin');

        return view('admin.end-of-term-reports', [
            'reports' => $reports,
            'departments' => app(\App\Repositories\Api\AcademicStructureRepository::class)->departments()->sortBy('code')->values(),
            'summary' => [
                'total_reports' => $reports->count(),
                'departments' => $reports->pluck('department_id')->unique()->count(),
                'students' => (int) $reports->sum('total_monitored'),
            ],
        ]);
    }

    public function preview(Request $request, $reportId)
    {
        $report = $this->findSharedReport($reportId, null, 'admin');

        if (!$report) {
            return redirect()->route('admin.reports')
                ->with('error', 'That report has not been shared with administrators.');
        }

        return view('reports.end-of-term', $this->reportViewData($report, [
            'role' => 'admin',
            'backRoute' => route('admin.reports'),
            'backLabel' => 'Back to reports',
            'canManage' => false,
            'pdfRoute' => route('admin.reports.pdf', $report->id),
            'activeNav' => 'reports',
        ]));
    }

    public function download(Request $request, $reportId)
    {
        $report = $this->findSharedReport($reportId, null, 'admin');

        if (!$report) {
            return redirect()->route('admin.reports')
                ->with('error', 'That report has not been shared with administrators.');
        }

        $snapshot = $this->decodeSnapshot($report);
        $generatorName = app(\App\Repositories\Api\StaffRepository::class)->userName($report->generated_by) ?? 'Academic Head';
        $pdf = $this->reports->renderPdf($snapshot, $generatorName);

        $filename = sprintf(
            'eot-risk-report_%s_%s_%s_%s.pdf',
            strtolower((string) ($snapshot['meta']['department']['code'] ?? 'dept')),
            $report->school_year,
            strtolower($report->grading_period),
            substr((string) $report->digest, 0, 8)
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdf),
            'X-Report-Digest' => (string) $report->digest,
            'X-Report-Computation' => 'deterministic-rule-based',
        ]);
    }
}

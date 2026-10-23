<?php

namespace App\Http\Controllers\Counselor;

use App\Helpers\DepartmentHelper;
use App\Http\Controllers\Concerns\PresentsEndOfTermReports;
use App\Http\Controllers\Controller;
use App\Services\Reports\EndOfTermReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use PresentsEndOfTermReports;

    protected EndOfTermReportService $reports;

    public function __construct(?EndOfTermReportService $reports = null)
    {
        $this->reports = $reports ?? new EndOfTermReportService();
    }

    public function index(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        $reports = $this->sharedReports((int) $departmentId, 'counselor');

        $filters = [
            'school_year' => (string) $request->input('school_year', ''),
            'semester' => (string) $request->input('semester', ''),
            'grading_period' => (string) $request->input('grading_period', ''),
        ];

        $filtered = $reports
            ->when($filters['school_year'] !== '', fn ($rows) => $rows->where('school_year', $filters['school_year']))
            ->when($filters['semester'] !== '', fn ($rows) => $rows->where('semester', $filters['semester']))
            ->when($filters['grading_period'] !== '', fn ($rows) => $rows->where('grading_period', $filters['grading_period']))
            ->values();

        $latest = $filtered->first();

        return view('counselor.reports', [
            'department' => app(\App\Repositories\Api\AcademicStructureRepository::class)->department($departmentId),
            'reports' => $filtered,
            'totalShared' => $reports->count(),
            'filters' => $filters,
            'options' => [
                'school_years' => $this->distinct($reports, 'school_year', true),
                'semesters' => $this->distinct($reports, 'semester'),
                'grading_periods' => $this->orderedPeriods($reports),
            ],
            // Chart series: oldest period first so the bars read left-to-right in
            // time order, which is the opposite of the table's newest-first order.
            'chart' => $filtered->reverse()->values()->map(fn ($report) => [
                'label' => $report->grading_period . ' · ' . $report->school_year,
                'high' => (int) $report->high_count,
                'moderate' => (int) $report->moderate_count,
                'low' => (int) $report->low_count,
            ])->values(),
            'summary' => [
                'count' => $filtered->count(),
                'latest' => $latest,
                'latest_high_percentage' => $latest->high_percentage ?? null,
                'latest_monitored' => $latest->total_monitored ?? null,
                'previous_period' => $latest->previous_grading_period ?? null,
            ],
        ]);
    }

    protected function distinct($reports, string $column, bool $descending = false): array
    {
        $values = $reports->pluck($column)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->unique()
            ->map(fn ($value) => (string) $value)
            ->sort()
            ->values();

        return ($descending ? $values->reverse() : $values)->values()->all();
    }

    protected function orderedPeriods($reports): array
    {
        $rank = \App\Repositories\Local\RiskScoreRepository::PERIOD_ORDER;
        $present = $this->distinct($reports, 'grading_period');

        usort($present, fn ($a, $b) => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));

        return $present;
    }

    public function mine(Request $request)
    {
        $counselor = $this->resolveCounselor($request);

        if (!$counselor) {
            return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
        }

        $departmentId = DepartmentHelper::getDepartmentId($request);
        $service = app(\App\Services\Reports\CounselorActivityReportService::class);
        $filters = $this->reportFilters($request, $filtersApplied);

        $snapshot = $service->buildSnapshot(
            (int) $counselor->id,
            $departmentId ? (int) $departmentId : null,
            $filters,
            now()->toDateString(),
            (string) (\Illuminate\Support\Facades\Auth::user()->name ?? '')
        );

        return view('counselor.my-reports', [
            'snapshot' => $snapshot,
            'digest' => $service->digest($snapshot),
            'counselor' => $counselor,
            'department' => $departmentId
                ? app(\App\Repositories\Api\AcademicStructureRepository::class)->department($departmentId)
                : null,
            'filters' => $filters,
            'filtersApplied' => $filtersApplied,
            'options' => $service->filterOptions(
                (int) $counselor->id,
                $departmentId ? (int) $departmentId : null
            ),
        ]);
    }

    public function minePdf(Request $request)
    {
        $counselor = $this->resolveCounselor($request);

        if (!$counselor) {
            return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
        }

        $departmentId = DepartmentHelper::getDepartmentId($request);
        $service = app(\App\Services\Reports\CounselorActivityReportService::class);
        $filters = $this->reportFilters($request, $filtersApplied);

        $snapshot = $service->buildSnapshot(
            (int) $counselor->id,
            $departmentId ? (int) $departmentId : null,
            $filters,
            now()->toDateString(),
            (string) (\Illuminate\Support\Facades\Auth::user()->name ?? '')
        );

        $pdf = $service->renderPdf($snapshot, (string) (\Illuminate\Support\Facades\Auth::user()->name ?? ''));

        $filename = sprintf(
            'counselor-caseload-activity_%s_%s.pdf',
            preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($counselor->employee_number ?? 'counselor')) ?: 'counselor',
            (string) ($snapshot['meta']['as_of'] ?? date('Y-m-d'))
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdf),
            'X-Report-Digest' => $service->digest($snapshot),
            'X-Report-Computation' => 'deterministic-rule-based',
        ]);
    }

    protected function resolveCounselor(Request $request): ?object
    {
        return \App\Http\Middleware\DepartmentIsolationMiddleware::getCounselor($request)
            ?? app(\App\Repositories\Api\StaffRepository::class)
                ->counselorForUser((int) \Illuminate\Support\Facades\Auth::id());
    }

    protected function reportFilters(Request $request, &$applied = null): array
    {
        $raw = [
            'school_year' => $request->input('school_year'),
            'priority' => $request->input('priority'),
            'status' => $request->input('status'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        $filters = [];
        $applied = false;

        foreach ($raw as $key => $value) {
            $value = trim((string) $value);

            if ($value === '' || $value === 'all') {
                $filters[$key] = '';
                continue;
            }

            $filters[$key] = $value;
            $applied = true;
        }

        return $filters;
    }

    public function preview(Request $request, $reportId)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);
        $report = $this->findSharedReport($reportId, $departmentId ? (int) $departmentId : null, 'counselor');

        if (!$report) {
            return redirect()->route('counselor.reports')
                ->with('error', 'That report is not available to you.');
        }

        return view('reports.end-of-term', $this->reportViewData($report, [
            'role' => 'counselor',
            'backRoute' => route('counselor.reports'),
            'backLabel' => 'Back to reports',
            'canManage' => false,
            'pdfRoute' => route('counselor.reports.pdf', $report->id),
            'activeNav' => 'reports',
        ]));
    }

    public function download(Request $request, $reportId)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);
        $report = $this->findSharedReport($reportId, $departmentId ? (int) $departmentId : null, 'counselor');

        if (!$report) {
            return redirect()->route('counselor.reports')
                ->with('error', 'That report is not available to you.');
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

<?php

namespace App\Http\Controllers\AcademicHead;

use App\Helpers\DepartmentHelper;
use App\Http\Controllers\Concerns\PresentsEndOfTermReports;
use App\Http\Controllers\Controller;
use App\Services\Reports\EndOfTermReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // departments are served by the mock API.
        $department = app(\App\Repositories\Api\AcademicStructureRepository::class)->department($departmentId);

        if (!$department) {
            return redirect()->route('dashboard')->with('error', 'Department record not found.');
        }

        $schoolYear = (string) $request->input('school_year', '2024-2025');
        $semester = (string) $request->input('semester', '1st');

        $userNames = app(\App\Repositories\Api\StaffRepository::class)->userNameIndex();
        $periodRank = \App\Repositories\Local\RiskScoreRepository::PERIOD_ORDER;

        $reports = DB::table('end_of_term_reports')
            ->where('end_of_term_reports.department_id', $departmentId)
            ->get()
            ->map(function ($report) use ($userNames) {
                $row = clone $report;
                $row->generated_by_name = $userNames[(int) $report->generated_by] ?? null;

                return $row;
            })
            ->sortBy(fn ($row) => $periodRank[(string) $row->grading_period] ?? 99)
            ->values();

        $periodsWithData = $this->reports->periodsWithData($departmentId, $schoolYear, $semester);

        $generatedForTerm = $reports
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->pluck('grading_period')
            ->all();

        // Deterministic generation plan: which period can be reported and why.
        $periodPlan = [];
        foreach (EndOfTermReportService::PERIODS as $period) {
            $periodPlan[] = [
                'period' => $period,
                'number' => $this->reports->periodNumber($period),
                'has_data' => in_array($period, $periodsWithData, true),
                'generated' => in_array($period, $generatedForTerm, true),
                'previous' => $this->reports->previousPeriod($period),
            ];
        }

        $schoolYears = DB::table('end_of_term_reports')
            ->where('department_id', $departmentId)
            ->distinct()
            ->pluck('school_year')
            ->all();

        if (!in_array('2024-2025', $schoolYears, true)) {
            $schoolYears[] = '2024-2025';
        }
        sort($schoolYears);

        return view('academic-head.reports', [
            'department' => $department,
            'reports' => $reports,
            'periodPlan' => $periodPlan,
            'schoolYear' => $schoolYear,
            'semester' => $semester,
            'schoolYears' => $schoolYears,
            'periods' => EndOfTermReportService::PERIODS,
        ]);
    }

    public function generate(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        $validated = $request->validate([
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', 'in:1st,2nd'],
            'grading_period' => ['required', 'in:' . implode(',', EndOfTermReportService::PERIODS)],
        ]);

        $user = Auth::user();
        $period = $validated['grading_period'];

        // Captured ONCE, then frozen into the snapshot so the PDF is reproducible.
        $generatedAt = now()->utc()->toDateTimeString();

        try {
            $snapshot = $this->reports->buildSnapshot(
                (int) $departmentId,
                $validated['school_year'],
                $validated['semester'],
                $period,
                ['id' => (int) $user->id, 'name' => (string) $user->name],
                $generatedAt
            );
        } catch (\Exception $e) {
            Log::error('[EOT Report] compilation failed', [
                'department_id' => $departmentId,
                'grading_period' => $period,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Report compilation failed: ' . $e->getMessage());
        }

        $digest = $this->reports->digest($snapshot);

        $payload = [
            'period_number' => $snapshot['meta']['period_number'],
            'total_monitored' => $snapshot['totals']['monitored'],
            'low_count' => $snapshot['counts_by_level']['Low'],
            'moderate_count' => $snapshot['counts_by_level']['Moderate'],
            'high_count' => $snapshot['counts_by_level']['High'],
            'low_percentage' => $snapshot['percentages_by_level']['Low'],
            'moderate_percentage' => $snapshot['percentages_by_level']['Moderate'],
            'high_percentage' => $snapshot['percentages_by_level']['High'],
            'previous_grading_period' => $snapshot['meta']['previous_period'],
            'snapshot' => $this->reports->canonicalJson($snapshot),
            'digest' => $digest,
            'generated_by' => $user->id,
            'generated_at' => $generatedAt,
            'updated_at' => now(),
        ];

        $existing = DB::table('end_of_term_reports')
            ->where('department_id', $departmentId)
            ->where('school_year', $validated['school_year'])
            ->where('semester', $validated['semester'])
            ->where('grading_period', $period)
            ->first();

        if ($existing) {
            DB::table('end_of_term_reports')->where('id', $existing->id)->update($payload);
            $reportId = (int) $existing->id;
            $action = 'EOT_REPORT_REGENERATED';
        } else {
            $reportId = (int) DB::table('end_of_term_reports')->insertGetId(array_merge($payload, [
                'department_id' => $departmentId,
                'school_year' => $validated['school_year'],
                'semester' => $validated['semester'],
                'grading_period' => $period,
                'is_shared' => false,
                'created_at' => now(),
            ]));
            $action = 'EOT_REPORT_GENERATED';
        }

        $this->logActivity($action, sprintf(
            'End-of-term report %s %s %s: %d students monitored (%d High / %d Moderate / %d Low). Digest %s.',
            $validated['school_year'],
            $validated['semester'],
            $period,
            $snapshot['totals']['monitored'],
            $snapshot['counts_by_level']['High'],
            $snapshot['counts_by_level']['Moderate'],
            $snapshot['counts_by_level']['Low'],
            substr($digest, 0, 16)
        ));

        return redirect()
            ->route('academic-head.reports.preview', $reportId)
            ->with('success', $existing
                ? "The {$period} report for {$validated['school_year']} was regenerated from the latest data."
                : "The {$period} report for {$validated['school_year']} was generated.");
    }

    public function preview(Request $request, $reportId)
    {
        $report = $this->findReport($request, $reportId);

        if (!$report) {
            return redirect()->route('academic-head.reports')->with('error', 'Report not found in your department.');
        }

        return view('reports.end-of-term', $this->reportViewData($report, [
            'role' => 'academic_head',
            'insights' => app(\App\Services\Reports\EndOfTermReportInsightService::class)
                ->forReport($this->decodeSnapshot($report)),
            'backRoute' => route('academic-head.reports'),
            'backLabel' => 'Back to reports',
            'canManage' => true,
            'pdfRoute' => route('academic-head.reports.pdf', $report->id),
            'shareRoute' => route('academic-head.reports.share', $report->id),
            'verifyRoute' => route('academic-head.reports.verify', $report->id),
            'activeNav' => 'reports',
        ]));
    }

    public function download(Request $request, $reportId)
    {
        $report = $this->findReport($request, $reportId);

        if (!$report) {
            return redirect()->route('academic-head.reports')->with('error', 'Report not found in your department.');
        }

        $snapshot = $this->decodeSnapshot($report);
        $generatorName = app(\App\Repositories\Api\StaffRepository::class)->userName($report->generated_by) ?? 'Academic Head';

        $insights = app(\App\Services\Reports\EndOfTermReportInsightService::class)->forReport($snapshot);

        $pdf = $this->reports->renderPdf($snapshot, $generatorName, $insights);

        $this->logActivity('EOT_REPORT_DOWNLOADED', sprintf(
            'Downloaded the %s %s %s end-of-term report (digest %s).',
            $report->school_year,
            $report->semester,
            $report->grading_period,
            substr((string) $report->digest, 0, 16)
        ));

        $filename = sprintf(
            'eot-risk-report_%s_%s_%s_%s.pdf',
            strtolower((string) ($snapshot['meta']['department']['code'] ?? 'dept')),
            $report->school_year,
            strtolower($report->grading_period),
            substr((string) $report->digest, 0, 8)
        );

        // The digest is exposed so a downloaded file can be verified externally.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdf),
            'X-Report-Digest' => (string) $report->digest,
            'X-Report-Computation' => 'deterministic-rule-based',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function verify(Request $request, $reportId)
    {
        $report = $this->findReport($request, $reportId);

        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found in your department.'], 404);
        }

        $result = $this->verifyReport($report, $this->reports);
        $result['success'] = true;

        return response()->json($result);
    }

    public function share(Request $request, $reportId)
    {
        $report = $this->findReport($request, $reportId);

        if (!$report) {
            return redirect()->route('academic-head.reports')->with('error', 'Report not found in your department.');
        }

        $validated = $request->validate([
            'share' => ['required', 'in:0,1'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['in:admin,counselor'],
        ]);

        $shouldShare = (string) $validated['share'] === '1';
        $roles = array_values(array_unique($validated['roles'] ?? ['admin', 'counselor']));

        DB::table('end_of_term_reports')
            ->where('id', $report->id)
            ->update([
                'is_shared' => $shouldShare,
                'shared_roles' => $shouldShare ? json_encode($roles) : null,
                'shared_at' => $shouldShare ? now() : null,
                'shared_by' => $shouldShare ? Auth::id() : null,
                'updated_at' => now(),
            ]);

        $this->logActivity(
            $shouldShare ? 'EOT_REPORT_SHARED' : 'EOT_REPORT_UNSHARED',
            sprintf(
                '%s the %s %s %s end-of-term report%s.',
                $shouldShare ? 'Shared' : 'Unshared',
                $report->school_year,
                $report->semester,
                $report->grading_period,
                $shouldShare ? ' with ' . implode(' and ', $roles) : ''
            )
        );

        return redirect()
            ->route('academic-head.reports.preview', $report->id)
            ->with('success', $shouldShare
                ? 'Report shared with ' . implode(' and ', $roles) . '.'
                : 'Report sharing revoked.');
    }

    public function destroy(Request $request, $reportId)
    {
        $report = $this->findReport($request, $reportId);

        if (!$report) {
            return redirect()->route('academic-head.reports')->with('error', 'Report not found in your department.');
        }

        DB::table('end_of_term_reports')->where('id', $report->id)->delete();

        $this->logActivity('EOT_REPORT_DELETED', sprintf(
            'Deleted the %s %s %s end-of-term report.',
            $report->school_year,
            $report->semester,
            $report->grading_period
        ));

        return redirect()->route('academic-head.reports')->with('success', 'Report deleted.');
    }


    protected function findReport(Request $request, $reportId): ?object
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId || !is_numeric($reportId)) {
            return null;
        }

        return DB::table('end_of_term_reports')
            ->where('id', (int) $reportId)
            ->where('department_id', $departmentId)
            ->first();
    }

    protected function logActivity(string $action, string $details): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => 'EndOfTermReport',
            'new_values' => json_encode(['details' => $details]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
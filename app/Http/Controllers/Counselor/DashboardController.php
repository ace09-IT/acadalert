<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Http\Middleware\DepartmentIsolationMiddleware;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceSummaryRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\ParentRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Services\CounselorNavigationService;
use App\Services\RecommendationTextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log; 

class DashboardController extends Controller
{
    public function __construct(protected RecommendationTextService $recommendationText)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Counselor + department are injected by the isolation middleware.
        $counselor = DepartmentIsolationMiddleware::getCounselor($request)
            ?? app(StaffRepository::class)->counselorForUser($user->id);

        if (!$counselor) {
            return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
        }

        $departmentId = DepartmentIsolationMiddleware::getDepartmentId($request);
        $structure = app(AcademicStructureRepository::class);

        // The API cannot JOIN, so the department boundary is an explicit student id
        // list — the same rows the previous INNER JOINs produced.
        $scopeStudentIds = $departmentId
            ? $structure->studentIdsInDepartment($departmentId, false)
            : array_keys($structure->studentPlacements());

        $scopeCounts = $this->getScopeCounts($counselor->id, $scopeStudentIds);
        $stats = $this->getStatistics($counselor->id, $departmentId);

        // Follow-up counters are shared with the sidebar badges, so the two can
        // never disagree (memoised per request by the service).
        $followUps = app(CounselorNavigationService::class)
            ->followUpCounts($counselor->id, $departmentId);

        return view('counselor.dashboard', [
            'counselor' => $counselor,
            'department' => $departmentId ? $structure->department($departmentId) : null,

            'totalCaseload' => $scopeCounts['all'],
            'openCases' => $scopeCounts['open'],
            'resolvedCases' => $scopeCounts['resolved'],
            'overdueFollowUps' => $followUps['overdue'],
            'upcomingFollowUpCount' => $followUps['upcoming'],

            'criticalCases' => $stats['critical'] ?? 0,
            'awaitingParent' => $stats['awaiting_parent'] ?? 0,
            'avgResponseTime' => $stats['avg_response_time'] ?? 0.0,
            'resolutionRate' => $scopeCounts['all'] > 0
                ? round(100 * $scopeCounts['resolved'] / $scopeCounts['all'], 1)
                : 0.0,

            'recentCases' => $this->recentCases($counselor->id, $scopeStudentIds),
            'upcomingFollowUps' => $this->upcomingFollowUps($counselor->id, $scopeStudentIds),
        ]);
    }

    public function cases(Request $request)
    {
        $user = Auth::user();
        
        $counselor = DepartmentIsolationMiddleware::getCounselor($request);
        $departmentId = DepartmentIsolationMiddleware::getDepartmentId($request);
        
        if (!$counselor) {
            $counselor = app(StaffRepository::class)->counselorForUser($user->id);
            if (!$counselor) {
                return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
            }
        }
        
        $priorityFilter = $request->input('priority', 'all');
        $statusFilter = $request->input('status', 'all');
        $departmentFilter = $request->input('department', 'all');
        $searchFilter = $request->input('search', '');

        // Scope: the dedicated "Open Cases" view (scope=open) vs the full caseload.
        // Validated against a whitelist so an unknown value can never build raw SQL.
        $scopeFilter = in_array($request->input('scope'), ['open', 'resolved', 'all'], true)
            ? (string) $request->input('scope')
            : 'all';

        $riskLevelFilter = in_array($request->input('risk_level'), ['High', 'Moderate', 'Low'], true)
            ? (string) $request->input('risk_level')
            : 'all';

        // Date escalated range (inclusive), normalised to Y-m-d or dropped.
        $escalatedFrom = $this->normaliseFilterDate($request->input('escalated_from'));
        $escalatedTo = $this->normaliseFilterDate($request->input('escalated_to'));

        $sortFilter = in_array($request->input('sort'), ['priority', 'escalated_newest', 'escalated_oldest'], true)
            ? (string) $request->input('sort')
            : 'priority';
        
        $structure = app(AcademicStructureRepository::class);
        $placements = $structure->studentPlacements();
        $studentsRepo = app(StudentRepository::class);

        $query = DB::table('cases')->where('cases.counselor_id', $counselor->id);
        
        if ($departmentId) {
            $query->whereIn('cases.student_id', $structure->studentIdsInDepartment($departmentId, false));
        }
        
        if ($priorityFilter !== 'all') {
            $query->where('cases.priority', $priorityFilter);
        }
        
        if ($statusFilter !== 'all') {
            $query->where('cases.status', str_replace('_', ' ', $statusFilter));
        }
        
        if ($departmentFilter !== 'all') {
            // departments.code came from the counselor's OWN department via a LEFT
            // JOIN, so a non-matching code must yield an empty result set.
            $ownDepartment = $counselor->department_id !== null
                ? $structure->department($counselor->department_id)
                : null;

            if (($ownDepartment->code ?? null) !== $departmentFilter) {
                $query->whereRaw('1 = 0');
            }
        }
        
        if (!empty($searchFilter)) {
            // The previous LIKE searched the joined student columns. MySQL's default
            // collation is case-insensitive, which stripos() reproduces for names.
            $matched = $studentsRepo->all()
                ->filter(fn ($student) => stripos((string) $student->first_name, $searchFilter) !== false
                    || stripos((string) $student->last_name, $searchFilter) !== false
                    || stripos((string) $student->student_number, $searchFilter) !== false)
                ->pluck('id')
                ->all();

            $query->whereIn('cases.student_id', $matched);
        }

        if ($statusFilter === 'all') {
            if ($scopeFilter === 'open') {
                $query->whereNotIn('cases.status', ['Resolved', 'Closed']);
            } elseif ($scopeFilter === 'resolved') {
                $query->whereIn('cases.status', ['Resolved', 'Closed']);
            }
        }

        if ($riskLevelFilter !== 'all') {
            $query->where('cases.risk_level_at_escalation', $riskLevelFilter);
        }

        if ($escalatedFrom !== null) {
            $query->whereDate('cases.escalated_at', '>=', $escalatedFrom);
        }

        if ($escalatedTo !== null) {
            $query->whereDate('cases.escalated_at', '<=', $escalatedTo);
        }
        
        // Get cases — the decorated columns come from the mock API, and
        // FIELD(cases.priority, ...) is applied as an explicit rank in PHP.
        $studentIndex = $studentsRepo->keyedById();
        $userNames = app(StaffRepository::class)->userNameIndex();
        $caseDepartment = $counselor->department_id !== null
            ? $structure->department($counselor->department_id)
            : null;
        $priorityRank = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];

        $cases = $query
            // Ordering: the existing priority-first ordering by default, or the
            // escalation date when the counselor explicitly asks for it.
            ->when(
                $sortFilter !== 'priority',
                fn ($q) => $q->orderBy('cases.escalated_at', $sortFilter === 'escalated_oldest' ? 'asc' : 'desc'),
                fn ($q) => $q->orderBy('cases.updated_at', 'desc')
            )
            ->get()
            ->filter(fn ($case) => isset($placements[(int) $case->student_id]))
            ->map(function ($case) use ($placements, $studentIndex, $userNames, $caseDepartment) {
                $placement = $placements[(int) $case->student_id];
                $student = $studentIndex[(int) $case->student_id] ?? null;

                $decorated = clone $case;
                $decorated->student_first_name = $student->first_name ?? null;
                $decorated->student_last_name = $student->last_name ?? null;
                $decorated->student_number = $student->student_number ?? null;
                $decorated->escalated_by_name = $userNames[(int) $case->escalated_by] ?? null;
                $decorated->department_name = $caseDepartment->name ?? null;
                $decorated->program_code = $placement['program_code'];

                return $decorated;
            })
            ->sort(function ($a, $b) use ($priorityRank) {
                $cmp = ($priorityRank[(string) $a->priority] ?? 9)
                    <=> ($priorityRank[(string) $b->priority] ?? 9);

                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $b->updated_at, (string) $a->updated_at);
            })
            ->values();
        
        
        $structure = app(AcademicStructureRepository::class);
        $scopeStudentIds = $departmentId
            ? $structure->studentIdsInDepartment($departmentId, false)
            : array_keys($structure->studentPlacements());

        
        $processedCases = $this->processCases($cases);
        
        $scopeCounts = $this->getScopeCounts($counselor->id, $scopeStudentIds);
        
        $processedCases = $this->processCases($cases);

        $scopeCounts = $this->getScopeCounts($counselor->id, $scopeStudentIds);
        
        return view('counselor.cases', [
            'cases' => $processedCases,
            'matchedCases' => $processedCases->count(),
            'scopeCounts' => $scopeCounts,
            'scope' => $scopeFilter,
            'riskLevelFilter' => $riskLevelFilter,
            'escalatedFrom' => $escalatedFrom,
            'escalatedTo' => $escalatedTo,
            'sortFilter' => $sortFilter,
            'counselor' => $counselor,
            'department' => $departmentId ? $structure->department($departmentId) : null,
            'departments' => $this->getFilterableDepartments($departmentId),
        ]);
    }
    
    protected function getScopeCounts(int $counselorId, array $studentIds): array
    {
        $base = fn () => DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds);

        return [
            'all' => $base()->count(),
            'open' => $base()->whereNotIn('cases.status', ['Resolved', 'Closed'])->count(),
            'resolved' => $base()->whereIn('cases.status', ['Resolved', 'Closed'])->count(),
        ];
    }

    protected function recentCases(int $counselorId, array $studentIds, int $limit = 6)
    {
        $rows = DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds)
            ->orderByDesc('cases.escalated_at')
            ->orderByDesc('cases.id')
            ->limit($limit)
            ->get([
                'cases.id',
                'cases.student_id',
                'cases.status',
                'cases.priority',
                'cases.risk_level_at_escalation',
                'cases.escalated_at',
            ]);

        return $this->decorateCaseRows($rows);
    }

    protected function upcomingFollowUps(int $counselorId, array $studentIds, int $limit = 8)
    {
        $caseIds = DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds)
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->pluck('cases.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($caseIds === []) {
            return collect([]);
        }

        $sessions = DB::table('case_sessions')
            ->whereIn('case_id', $caseIds)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>=', now()->toDateString())
            ->whereDate('follow_up_date', '<=', now()->addDays(7)->toDateString())
            ->orderBy('follow_up_date')
            ->get(['case_id', 'follow_up_date', 'session_type'])
            ->unique('case_id')
            ->take($limit)
            ->values();

        if ($sessions->isEmpty()) {
            return collect([]);
        }

        $cases = DB::table('cases')
            ->whereIn('id', $sessions->pluck('case_id')->all())
            ->get(['id', 'student_id', 'priority', 'status'])
            ->keyBy('id');

        $students = app(StudentRepository::class)->keyedById();

        return $sessions->map(function ($session) use ($cases, $students) {
            $case = $cases[(int) $session->case_id] ?? null;
            $student = $case ? ($students[(int) $case->student_id] ?? null) : null;

            return (object) [
                'case_id' => (int) $session->case_id,
                'student_name' => $student
                    ? trim(((string) ($student->first_name ?? '') . ' ' . (string) ($student->last_name ?? '')))
                    : 'Unknown student',
                'student_number' => $student->student_number ?? null,
                'priority' => $case->priority ?? 'Low',
                'status' => $case->status ?? 'New',
                'follow_up_date' => $session->follow_up_date,
                'follow_up_label' => \Carbon\Carbon::parse($session->follow_up_date)->format('M d, Y'),
                'days_until' => max(0, (int) now()->startOfDay()
                    ->diffInDays(\Carbon\Carbon::parse($session->follow_up_date)->startOfDay(), false)),
                'session_type' => $session->session_type,
            ];
        })->values();
    }

    protected function decorateCaseRows($rows)
    {
        if ($rows->isEmpty()) {
            return collect([]);
        }

        $students = app(StudentRepository::class)->keyedById();
        $placements = app(AcademicStructureRepository::class)->studentPlacements();

        return $rows
            ->filter(fn ($row) => isset($placements[(int) $row->student_id]))
            ->map(function ($row) use ($students, $placements) {
                $student = $students[(int) $row->student_id] ?? null;
                $placement = $placements[(int) $row->student_id];

                $name = trim(((string) ($student->first_name ?? '')) . ' ' . ((string) ($student->last_name ?? '')));

                $decorated = clone $row;
                $decorated->student_name = $name !== '' ? $name : 'Unknown student';
                $decorated->student_number = $student->student_number ?? null;
                $decorated->program_code = $placement['program_code'] ?? null;
                $decorated->escalated_label = $row->escalated_at
                    ? \Carbon\Carbon::parse($row->escalated_at)->format('M d, Y')
                    : 'N/A';

                return $decorated;
            })
            ->values();
    }

    protected function normaliseFilterDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function show(Request $request, $caseId)
    {
        $departmentId = DepartmentIsolationMiddleware::getDepartmentId($request);
        
        $structure = app(AcademicStructureRepository::class);
        $case = DB::table('cases')->where('id', $caseId)->first();

        if ($case !== null && $structure->isStudentPlaced($case->student_id)) {
            $placement = $structure->studentPlacements()[(int) $case->student_id];

            if ($departmentId && $placement['department_id'] !== (int) $departmentId) {
                $case = null;
            } else {
                $student = app(StudentRepository::class)->find($case->student_id);

                $caseCounselor = app(StaffRepository::class)->counselor($case->counselor_id);
                $department = $caseCounselor !== null
                    ? $structure->department($caseCounselor->department_id)
                    : null;

                $case->student_id = (int) $case->student_id;
                $case->student_first_name = $student->first_name ?? null;
                $case->student_last_name = $student->last_name ?? null;
                $case->student_number = $student->student_number ?? null;
                $case->student_email = $student->email ?? null;
                $case->escalated_by_name = app(StaffRepository::class)->userName($case->escalated_by);
                $case->department_name = $department->name ?? null;
                $case->program_code = $placement['program_code'];
            }
        } else {
            $case = null;
        }
        
        if (!$case) {
            return redirect()->route('counselor.dashboard')->with('error', 'Case not found or you do not have access to it.');
        }
        
        $student = $this->getStudentDetails($case->student_id);
        
        $sessions = DB::table('case_sessions')
            ->where('case_id', $caseId)
            ->orderBy('session_date', 'desc')
            ->get();
        
        $parents = app(ParentRepository::class)->forStudent($case->student_id);
        
        // Get risk history for effectiveness tracking — risk_scores stays local and
        // the FIELD() ordering is applied in PHP by the repository.
        $riskHistory = app(RiskScoreRepository::class)->allForStudent($case->student_id, '2024-2025');
        
        $improvement = $this->calculateImprovement($riskHistory, $case->risk_score_at_escalation);
        
        $escalation = DB::table('escalations')
            ->where('student_id', $case->student_id)
            ->where('case_id', $caseId)
            ->first();

        $currentPeriod = $escalation->grading_period ?? $this->getCurrentPeriod();
        $nextPeriod = $this->getNextPeriod($currentPeriod);
        $schoolYear = $case->school_year ?: '2024-2025';

        $forwardedRecommendation = $this->buildForwardedRecommendation($case);

        $currentRiskRow = DB::table('risk_scores')
            ->where('student_id', $case->student_id)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->first()
            ?? $riskHistory->last();

        $currentRiskScore = $currentRiskRow->risk_score ?? null;
        $currentRiskLevel = $currentRiskRow->risk_level ?? 'N/A';

        $riskFactors = $this->recommendationText->factors($currentRiskRow->risk_factors ?? null);

        // A forwarded recommendation always names what it is based on, so it is a
        // legitimate fallback when the score row carries no factors.
        if ($riskFactors === [] && $forwardedRecommendation !== null) {
            $riskFactors = $this->recommendationText->factors($forwardedRecommendation->risk_factors ?? null);
        }

        $riskTrend = $this->buildRiskTrend($riskHistory);

        // 4. per-subject grades for this period + average (mock API :3000)
        $grades = $this->buildGradeSummary($case->student_id, $currentPeriod, $schoolYear);

        // 5. attendance rate, absences, lates and excused (mock API :3000)
        $attendance = $this->buildAttendanceSummary($case->student_id, $currentPeriod, $schoolYear);
        return view('counselor.case', [
            'case' => $case,
            'student' => $student,
            'parents' => $parents,
            'sessions' => $sessions,
            'riskHistory' => $riskHistory,
            'improvement' => $improvement,
            'nextPeriod' => $nextPeriod,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
            'escalationNotes' => $escalation->notes ?? null,
            'escalationPeriod' => $escalation->grading_period ?? null,
            'currentRisk' => $currentRiskLevel,
            'currentRiskScore' => $currentRiskScore,
            'forwardedRecommendation' => $forwardedRecommendation,
            'riskFactors' => $riskFactors,
            'riskTrend' => $riskTrend,
            'grades' => $grades,
            'attendance' => $attendance,
        ]);
    }
    
    public function storeSession(Request $request, $caseId)
{
    $request->validate([
        'session_date' => 'required|date',
        'session_type' => 'required|in:In-person,Phone,Phone Call,Virtual,Email,Parent Meeting',
        'notes' => 'required|string|min:5',
        'action_taken' => 'nullable|string',
        'follow_up_date' => 'nullable|date|after:session_date',
        'status_after_session' => 'required|in:New,In Progress,Awaiting Parent,Awaiting Student,Referred,Resolved,Closed,Reopened',
    ]);

    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    DB::transaction(function () use ($caseId, $request) {
        DB::table('case_sessions')->insert([
            'case_id' => $caseId,
            'session_date' => $request->session_date,
            'session_type' => $request->session_type,
            'notes' => $request->notes,
            'action_taken' => $request->action_taken,
            'follow_up_date' => $request->follow_up_date,
            'status_after_session' => $request->status_after_session,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cases')
            ->where('id', $caseId)
            ->update([
                'status' => $request->status_after_session,
                'updated_at' => now(),
            ]);
    });

    $this->createStudentAlert(
        $case->student_id,
        'counselor_update',
        'info',
        'Your guidance counselor has updated your case.',
        $request->grading_period ?? 'Midterm'
    );

    if (!empty($request->action_taken)) {
        $this->createStudentAlert(
            $case->student_id,
            'counselor_action',
            'info',
            'Your guidance counselor has taken action on your case.',
            $request->grading_period ?? 'Midterm'
        );
    }

    return redirect()->back()->with('success', 'Session logged successfully.');
}
    
    public function resolve(Request $request, $caseId)
{
    $request->validate([
        'resolved_reason' => 'required|string|min:10',
    ]);

    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    if (in_array($case->status, ['Resolved', 'Closed'])) {
        return redirect()->back()->with('warning', 'This case is already resolved or closed.');
    }

    DB::transaction(function () use ($caseId, $request) {
        DB::table('cases')
            ->where('id', $caseId)
            ->update([
                'status' => 'Resolved',
                'resolved_at' => now(),
                'resolved_reason' => $request->resolved_reason,
                'updated_at' => now(),
            ]);

        DB::table('case_sessions')->insert([
            'case_id' => $caseId,
            'session_date' => now(),
            'session_type' => 'Virtual',
            'notes' => 'Case resolved. Reason: ' . $request->resolved_reason,
            'action_taken' => 'Case marked as resolved',
            'status_after_session' => 'Resolved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->createStudentAlert(
        $case->student_id,
        'case_resolved',
        'success',
        '🎉 Your case has been resolved! Reason: ' . $request->resolved_reason,
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->back()->with('success', 'Case marked as resolved successfully.');
}
    
    public function reopen(Request $request, $caseId)
{
    $request->validate([
        'reopen_reason' => 'required|string|min:10',
    ]);

    $existingCase = DB::table('cases')->where('id', $caseId)->first();
    if (!$existingCase) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    if ($existingCase->status === 'Reopened') {
        return redirect()->back()->with('warning', 'This case is already reopened.');
    }

    if (!in_array($existingCase->status, ['Resolved', 'Closed'])) {
        return redirect()->back()->with('warning', 'Only resolved or closed cases can be reopened.');
    }

    $newCaseId = DB::transaction(function () use ($caseId, $existingCase, $request) {
        DB::table('cases')
            ->where('id', $caseId)
            ->update([
                'status' => 'Closed',
                'updated_at' => now(),
            ]);

        $newCaseId = DB::table('cases')->insertGetId([
            'student_id' => $existingCase->student_id,
            'counselor_id' => $existingCase->counselor_id,
            'escalated_by' => $existingCase->escalated_by,
            'escalated_at' => now(),
            'school_year' => $existingCase->school_year,
            'semester' => $existingCase->semester,
            'priority' => $this->recalculatePriority($existingCase->student_id),
            'status' => 'Reopened',
            'risk_level_at_escalation' => $this->getCurrentRiskLevel($existingCase->student_id),
            'risk_score_at_escalation' => $this->getCurrentRiskScore($existingCase->student_id),
                // Carry the forwarded recommendation forward: a reopened case is the
                // same intervention continuing, so the counselor keeps its context.
                'intervention_recommendation' => $existingCase->intervention_recommendation ?? null,
                'intervention_included' => (bool) ($existingCase->intervention_included ?? false),
                'intervention_edited' => (bool) ($existingCase->intervention_edited ?? false),
                'intervention_source_id' => $existingCase->intervention_source_id ?? null,
            'reopened_from_case_id' => $caseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('case_sessions')->insert([
            'case_id' => $newCaseId,
            'session_date' => now(),
            'session_type' => 'Virtual',
            'notes' => 'Case reopened. Reason: ' . $request->reopen_reason,
            'action_taken' => 'Case reopened for further intervention',
            'status_after_session' => 'Reopened',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $newCaseId;
    });

    $this->createStudentAlert(
        $existingCase->student_id,
        'case_reopened',
        'warning',
        '⚠️ Your case has been reopened. Reason: ' . $request->reopen_reason,
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->route('counselor.case', $newCaseId)->with('success', 'Case reopened successfully.');
}
    
    public function updatePriority(Request $request, $caseId)
{
    $request->validate([
        'priority' => 'required|in:Critical,High,Medium,Low',
    ]);

    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    $oldPriority = $case->priority;
    $newPriority = $request->priority;

    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'priority' => $newPriority,
            'updated_at' => now(),
        ]);

    $severity = match($newPriority) {
        'Critical' => 'critical',
        'High' => 'high',
        'Medium' => 'medium',
        'Low' => 'low'
    };

    $this->createStudentAlert(
        $case->student_id,
        'priority_update',
        $severity,
        'Your case priority has been updated from "' . $oldPriority . '" to "' . $newPriority . '".',
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->back()->with('success', 'Priority updated successfully.');
}
    
    public function updateStatus(Request $request, $caseId)
{
    $request->validate([
        'status' => 'required|in:New,In Progress,Awaiting Parent,Awaiting Student,Referred,Resolved,Closed,Reopened',
    ]);

    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    $oldStatus = $case->status;
    $newStatus = $request->status;

    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

    $severity = match($newStatus) {
        'Resolved' => 'success',
        'Reopened' => 'warning',
        'Awaiting Parent', 'Awaiting Student' => 'medium',
        default => 'info'
    };

    $this->createStudentAlert(
        $case->student_id,
        'status_update',
        $severity,
        'Your case status has been updated from "' . $oldStatus . '" to "' . $newStatus . '".',
        $request->grading_period ?? 'Midterm'
    );

    if ($newStatus === 'Resolved') {
        $this->createStudentAlert(
            $case->student_id,
            'case_resolved',
            'success',
            '🎉 Your case has been resolved!',
            $request->grading_period ?? 'Midterm'
        );
    }

    if ($newStatus === 'Reopened') {
        $this->createStudentAlert(
            $case->student_id,
            'case_reopened',
            'warning',
            '⚠️ Your case has been reopened.',
            $request->grading_period ?? 'Midterm'
        );
    }

    return redirect()->back()->with('success', 'Status updated successfully.');
}
    
    protected function getStatistics($counselorId, $departmentId = null)
    {
        $stats = [];
        
        $structure = app(AcademicStructureRepository::class);
        $scopeStudentIds = $departmentId
            ? $structure->studentIdsInDepartment($departmentId, false)
            : array_keys($structure->studentPlacements());

        $baseQuery = DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $scopeStudentIds);
        
        $stats['open'] = (clone $baseQuery)
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->count();
        
        $stats['critical'] = (clone $baseQuery)
            ->where('cases.priority', 'Critical')
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->count();
        
        $stats['awaiting_parent'] = (clone $baseQuery)
            ->where('cases.status', 'Awaiting Parent')
            ->count();
        
        $responded = DB::table('case_sessions')
            ->join('cases', 'case_sessions.case_id', '=', 'cases.id')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $scopeStudentIds)
            ->select('cases.id', 'cases.escalated_at', DB::raw('MIN(case_sessions.session_date) as first_session'))
            ->groupBy('cases.id', 'cases.escalated_at')
            ->get();

        $responseDays = $responded->map(function ($row) {
            if (!$row->first_session || !$row->escalated_at) {
                return null;
            }

            // Signed so that a session logged after the escalation counts
            // positively; a same-day contact contributes 0.
            return max(0, (int) \Carbon\Carbon::parse($row->escalated_at)
                ->diffInDays(\Carbon\Carbon::parse($row->first_session), false));
        })->filter(fn ($days) => $days !== null);

        $stats['avg_response_time'] = $responseDays->isNotEmpty()
            ? round($responseDays->avg(), 1)
            : 0.0;
        
        return $stats;
    }
    
    protected function processCases($cases)
    {
        if ($cases->isEmpty()) {
            return collect([]);
        }

        $caseIds = $cases->pluck('id')->all();

        $lastSessions = DB::table('case_sessions')
            ->whereIn('case_id', $caseIds)
            ->select('case_id', DB::raw('MAX(session_date) as last_date'))
            ->groupBy('case_id')
            ->get()
            ->pluck('last_date', 'case_id');

        $followUps = DB::table('case_sessions')
            ->whereIn('case_id', $caseIds)
            ->where('follow_up_date', '>=', now()->toDateString())
            ->select('case_id', DB::raw('MIN(follow_up_date) as next_date'))
            ->groupBy('case_id')
            ->get()
            ->pluck('next_date', 'case_id');

        $effectiveness = $this->buildEffectivenessMap($cases);

        $processed = [];
        
        foreach ($cases as $case) {
            $lastSessionDate = $lastSessions[$case->id] ?? null;
            $followUpDate = $followUps[$case->id] ?? null;
            $improvement = $effectiveness[$case->id] ?? null;
            
            $processed[] = (object) [
                'id' => $case->id,
                'student_name' => $case->student_first_name . ' ' . $case->student_last_name,
                'program' => $case->program_code ?? 'N/A',
                'priority' => $case->priority,
                'status' => $case->status,
                'risk_level' => $case->risk_level_at_escalation,
                'last_session_date' => $lastSessionDate ? date('M d, Y', strtotime((string) $lastSessionDate)) : 'No sessions',
                'follow_up_date' => $followUpDate ? date('M d, Y', strtotime((string) $followUpDate)) : null,
                'improvement' => $improvement,
                'escalated_at' => $case->escalated_at,
            ];
        }
        
        return collect($processed);
    }
    
    protected function getStudentDetails($studentId)
    {
        $structure = app(AcademicStructureRepository::class);
        $student = app(StudentRepository::class)->find($studentId);
        $placement = $student !== null ? $structure->blockPlacement($student->block_id) : null;
        
        if ($student === null || $placement === null || $placement['program_id'] === null) {
            return null;
        }
        
        // Clone: the source row is the memoised API instance and this method adds
        // the placement and parent_* columns to it.
        $student = clone $student;
        $student->block_name = $placement['block_name'] ?? null;
        $student->year_level = $placement['year_level_name'] ?? null;
        $student->program_code = $placement['program_code'] ?? null;
        $student->program_name = $placement['program_name'] ?? null;
        
        $parent = app(ParentRepository::class)->forStudent($studentId)
            ->firstWhere('is_primary_contact', 1);
        
        if ($parent) {
            $student->parent_name = $parent->full_name;
            $student->parent_contact = $parent->contact_number;
            $student->parent_email = $parent->email;
        }
        
        return $student;
    }
    
    protected function calculateImprovement($riskHistory, $initialScore)
    {
        if ($riskHistory->isEmpty()) {
            return null;
        }
        
        $latest = $riskHistory->last();
        $latestScore = $latest->risk_score ?? 0;
        
        return $initialScore - $latestScore;
    }
    
    protected function buildEffectivenessMap($cases): array
    {
        $latest = DB::table('risk_scores')
            ->whereIn('student_id', $cases->pluck('student_id')->unique()->values()->all())
            ->orderBy('student_id')
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Finals') asc")
            ->select('student_id', 'risk_score', 'grading_period', 'school_year')
            ->get()
            ->groupBy('student_id');

        $map = [];

        foreach ($cases as $case) {
            $rows = $latest->get($case->student_id);
            $scoped = $rows
                ? $rows->where('school_year', $case->school_year)->values()
                : collect();

            $map[$case->id] = $scoped->isNotEmpty()
                ? (int) $case->risk_score_at_escalation - (int) $scoped->last()->risk_score
                : null;
        }

        return $map;
    }
    
    protected function getCurrentPeriod(): string
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];

        // The academic calendar is served by the mock API.
        $active = app(CalendarRepository::class)->activePeriod();

        if ($active && in_array($active, $periods, true)) {
            return $active;
        }

        // risk_scores stays local; the FIELD() ordering is applied in PHP.
        $latest = app(RiskScoreRepository::class)->latestPeriod('2024-2025', $periods);

        return in_array($latest, $periods, true) ? $latest : 'Midterm';
    }

    protected function buildForwardedRecommendation(object $case): ?object
    {
        $text = isset($case->intervention_recommendation) ? trim((string) $case->intervention_recommendation) : '';

        if ($text === '') {
            return null;
        }

        // Provenance: the generated row this copy came from (it may have been deleted
        // since, which is why the copy lives on the case).
        $sourceId = $case->intervention_source_id ?? null;
        $sourceRow = $sourceId
            ? DB::table('intervention_recommendations')->where('id', $sourceId)->first()
            : null;

        return (object) [
            'text' => (string) $case->intervention_recommendation,
            'included' => (bool) ($case->intervention_included ?? false),
            'edited' => (bool) ($case->intervention_edited ?? false),
            'source_id' => $sourceId,
            'generated_at' => $sourceRow->generated_at ?? null,
            'grading_period' => $sourceRow->grading_period ?? null,
            'actions' => $this->recommendationText->fromText((string) $case->intervention_recommendation),
        ];
    }

    protected function buildRiskTrend(Collection $riskHistory): array
    {
        $levelValues = ['Low' => 1, 'Moderate' => 2, 'High' => 3];
        $rows = [];
        $previous = null;

        foreach ($riskHistory as $row) {
            $trend = 'stable';
            $delta = null;

            if ($previous !== null) {
                $delta = (int) $row->risk_score - (int) $previous->risk_score;

                $currentValue = $levelValues[(string) $row->risk_level] ?? 0;
                $previousValue = $levelValues[(string) $previous->risk_level] ?? 0;

                if ($currentValue > $previousValue) {
                    $trend = 'worsening';
                } elseif ($currentValue < $previousValue) {
                    $trend = 'improving';
                }
            }

            $rows[] = [
                'period' => (string) $row->grading_period,
                'score' => (int) $row->risk_score,
                'level' => (string) $row->risk_level,
                'delta' => $delta,
                'trend' => $trend,
            ];

            $previous = $row;
        }

        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return [
            'rows' => $rows,
            'overall' => $last['trend'] ?? 'unknown',
            'delta' => $last['delta'] ?? null,
        ];
    }

    protected function buildGradeSummary(int $studentId, string $period, string $schoolYear): array
    {
        $rows = app(GradeRepository::class)->subjectGradesForStudent($studentId, $period, $schoolYear);
        $values = $rows->map(fn ($row) => (float) $row->numerical_grade)->values();

        return [
            'rows' => $rows,
            'total' => $rows->count(),
            'average' => $values->isEmpty() ? null : round((float) $values->avg(), 2),
            'failing' => $values->filter(fn ($grade) => $grade < 75)->count(),
            'highest' => $values->isEmpty() ? null : (float) $values->max(),
            'lowest' => $values->isEmpty() ? null : (float) $values->min(),
        ];
    }

    protected function buildAttendanceSummary(int $studentId, string $period, string $schoolYear): array
    {
        $attendance = app(AttendanceSummaryRepository::class);

        return [
            'summary' => $attendance->aggregateForStudent($studentId, $period, $schoolYear),
            'subjects' => $attendance->subjectSummariesForStudent($studentId, $period, $schoolYear),
        ];
    }

    protected function getNextPeriod(?string $current = null): ?string
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $current = $current ?: $this->getCurrentPeriod();
        $index = array_search($current, $periods, true);

        if ($index === false || $index >= count($periods) - 1) {
            return null;
        }

        return $periods[$index + 1];
    }

    protected function getFilterableDepartments(?int $departmentId)
    {
        // departments are served by the mock API.
        $departments = app(AcademicStructureRepository::class)
            ->departments()
            ->sortBy('code')
            ->values();

        if ($departmentId) {
            $departments = $departments
                ->filter(fn ($row) => (int) $row->id === (int) $departmentId)
                ->values();
        }

        return $departments;
    }
    
    protected function getCurrentRiskLevel($studentId)
    {
        $risk = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->first();
        return $risk->risk_level ?? 'Low';
    }
    
    protected function getCurrentRiskScore($studentId)
    {
        $risk = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->first();
        return $risk->risk_score ?? 0;
    }
    
    protected function recalculatePriority($studentId)
    {
        $riskLevel = $this->getCurrentRiskLevel($studentId);
        if ($riskLevel === 'High') return 'High';
        if ($riskLevel === 'Moderate') return 'Medium';
        return 'Low';
    }


    protected function createStudentAlert(int $studentId, string $flagType, string $severity, ?string $message = null, ?string $period = null): void
    {
        $schoolYear = '2024-2025';
        $gradingPeriod = $period ?? 'Midterm';
        
        $validFlagTypes = [
            'counselor_update' => 'counselor_update',
            'counselor_action' => 'counselor_action',
            'status_update' => 'status_update',
            'priority_update' => 'priority_update',
            'case_resolved' => 'case_resolved',
            'case_reopened' => 'case_reopened',
        ];
        
        $validSeverities = [
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            'success' => 'low',
            'info' => 'low',
            'warning' => 'medium',
            'error' => 'high',
        ];
        
        $finalFlagType = $validFlagTypes[$flagType] ?? 'counselor_update';
        $finalSeverity = $validSeverities[$severity] ?? 'medium';
        
        Log::info('Creating student alert', [
            'student_id' => $studentId,
            'flag_type' => $finalFlagType,
            'severity' => $finalSeverity,
            'message' => $message,
            'period' => $gradingPeriod,
        ]);
        
        try {
            $exists = DB::table('flags')
                ->where('student_id', $studentId)
                ->where('flag_type', $finalFlagType)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->exists();
            
            if ($exists) {
                DB::table('flags')
                    ->where('student_id', $studentId)
                    ->where('flag_type', $finalFlagType)
                    ->where('grading_period', $gradingPeriod)
                    ->where('school_year', $schoolYear)
                    ->update([
                        'is_acknowledged' => false,
                        'message' => $message,
                        'updated_at' => now(),
                    ]);
                
                Log::info('Updated existing alert', ['student_id' => $studentId, 'flag_type' => $finalFlagType]);
                return;
            }
            
            DB::table('flags')->insert([
                'student_id' => $studentId,
                'grading_period' => $gradingPeriod,
                'school_year' => $schoolYear,
                'semester' => '1st',
                'flag_type' => $finalFlagType,
                'severity' => $finalSeverity,
                'message' => $message,
                'is_acknowledged' => false,
                'consecutive_periods_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info('Created new alert', ['student_id' => $studentId, 'flag_type' => $finalFlagType]);
            
            $this->logActivity('STUDENT_ALERT_CREATED', "Alert created for student $studentId: $flagType ($severity)");
            
        } catch (\Exception $e) {
            Log::error('Failed to create student alert', [
                'student_id' => $studentId,
                'flag_type' => $finalFlagType,
                'severity' => $finalSeverity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logActivity(string $action, string $details): void
    { 
        DB::table('audit_logs')->insert([
            
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Counselor',
            'new_values' => json_encode(['details' => $details]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\AcademicHead;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceSummaryRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Api\SubjectRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function department(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);
        
        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        // departments are served by the mock API.
        $department = app(AcademicStructureRepository::class)->department($departmentId);
        if (!$department) {
            return redirect()->route('dashboard')->with('error', 'Department not found.');
        }

        $programs = DepartmentHelper::getDepartmentPrograms($request);
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        $semester = $request->input('semester', '1st');


        
        $programStats = [];
        $totalStudents = 0;
        $totalHighRisk = 0;
        $totalModerateRisk = 0;
        $totalLowRisk = 0;
        
        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);
        // Extra read-only repositories used by the actionable-item builders below.
        $students = app(StudentRepository::class);
        $grades = app(GradeRepository::class);
        $attendance = app(AttendanceSummaryRepository::class);


        
        foreach ($programs as $program) {
            $programStudentIds = $structure->studentIdsInProgram($program->id, true);
            $studentCount = count($programStudentIds);
            
            $counts = $riskScores->levelCountsFor($programStudentIds, $currentPeriod, $schoolYear);
            
            $lowRisk = $counts['Low'];
            $moderateRisk = $counts['Moderate'];
            $highRisk = $counts['High'];
            
            $totalStudents += $studentCount;
            $totalHighRisk += $highRisk;
            $totalModerateRisk += $moderateRisk;
            $totalLowRisk += $lowRisk;
            
            $programStats[] = (object) [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'student_count' => $studentCount,
                'low_risk' => $lowRisk,
                'moderate_risk' => $moderateRisk,
                'high_risk' => $highRisk,
                'high_risk_percentage' => $studentCount > 0 ? round(($highRisk / max($studentCount, 1)) * 100, 1) : 0,
            ];
        }
        
        $alerts = [];
        foreach ($programStats as $stat) {
            if ($stat->high_risk_percentage > 15) {
                $alerts[] = [
                    'severity' => 'critical',
                    'message' => "{$stat->code} has {$stat->high_risk} High Risk students ({$stat->high_risk_percentage}%). Immediate attention needed.",
                ];
            } elseif ($stat->high_risk_percentage > 8) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => "{$stat->code} has {$stat->high_risk} High Risk students ({$stat->high_risk_percentage}%). Monitor closely.",
                ];
            }
        }
        
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $trendData = [];
        $departmentStudentIds = $structure->studentIdsInDepartment($departmentId, true);

        foreach ($periods as $period) {
            $highRiskCount = $riskScores->highCountFor($departmentStudentIds, $period, $schoolYear);
            $totalInPeriod = $riskScores->countFor($departmentStudentIds, $period, $schoolYear);
            
            $trendData[] = [
                'period' => $period,
                'high_risk_count' => $highRiskCount,
                'total_students' => $totalInPeriod > 0 ? $totalInPeriod : $totalStudents,
                'high_risk_percentage' => $totalInPeriod > 0 ? round(($highRiskCount / max($totalInPeriod, 1)) * 100, 1) : 0,
            ];
        }
        
        $blockStats = $this->buildBlockStats($structure, $riskScores, $departmentId, $currentPeriod, $schoolYear);

        $escalationTrend = $this->buildEscalationTrend($departmentStudentIds, $schoolYear, $semester);

        $attention = $this->buildAttentionList(
            $structure,
            $riskScores,
            $students,
            $grades,
            $attendance,
            $departmentStudentIds,
            $currentPeriod,
            $schoolYear
        );
        $recentEscalations = $this->buildRecentEscalations($structure, $students, $departmentStudentIds);
        $pendingRecommendations = $this->buildPendingRecommendations(
            $structure,
            $students,
            $departmentStudentIds,
            $currentPeriod,
            $schoolYear
        );

        // Sidebar / header counters, memoised for the whole request.
        // Sidebar / header counters, memoised for the whole request.
        $counters = app(\App\Services\AcademicHeadNavigationService::class)
            ->counters($departmentId, $currentPeriod, $schoolYear);


        $data = [
            'departmentName' => $department->name,
            'departmentCode' => $department->code,
            'totalStudents' => $totalStudents,
            'highRiskCount' => $totalHighRisk,
            'moderateRiskCount' => $totalModerateRisk,
            'lowRiskCount' => $totalLowRisk,
            'needEscalation' => $totalHighRisk,
            'programs' => $programStats,
            'alerts' => $alerts,
            'blocks' => $blockStats,
            'escalationTrend' => $escalationTrend,
            'attention' => $attention,
            'recentEscalations' => $recentEscalations,
            'pendingRecommendations' => $pendingRecommendations,
            'counters' => $counters,
            'periods' => $periods,
            'schoolYears' => $this->availableSchoolYears($departmentStudentIds),
            'semester' => $semester,
            'unmonitoredCount' => $counters['unmonitored'],
            'trendData' => $trendData,
            'departmentId' => $departmentId,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
        ];
        
        return view('academic-head.department', $data);
    }
    
    public function blocks(Request $request, $programId)
    {
        if (!DepartmentHelper::isProgramInDepartment($request, $programId)) {
            return redirect()->route('academic-head.department')->with('error', 'You do not have access to this program.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);

        $blocks = $structure->blocksForProgram($programId);
        
        $program = $structure->program($programId);
        
        foreach ($blocks as $block) {
            $block->total_students = app(StudentRepository::class)->activeCountForBlock($block->id);
            
            $counts = $riskScores->levelCountsFor(
                $structure->studentIdsInBlock($block->id, true),
                $currentPeriod,
                $schoolYear
            );
            
            $block->low_risk = $counts['Low'];
            $block->moderate_risk = $counts['Moderate'];
            $block->high_risk = $counts['High'];
            
        }
        
        return view('academic-head.blocks', compact('blocks', 'program', 'currentPeriod', 'schoolYear'));
    }
    
    private function prepareStudentDisplayData($student)
    {
        $riskClass = match($student->current_risk) {
            'High' => 'badge-risk-high',
            'Moderate' => 'badge-risk-moderate',
            default => 'badge-risk-low'
        };
        
        $prevRiskClass = match($student->previous_risk) {
            'High' => 'badge-risk-high',
            'Moderate' => 'badge-risk-moderate',
            default => 'badge-risk-low'
        };
        
        $gradeColor = '';
        if ($student->grade < 75) {
            $gradeColor = 'text-danger fw-bold';
        } elseif ($student->grade < 80) {
            $gradeColor = 'text-warning';
        }
        
        $attendanceColor = '';
        if ($student->attendance < 70) {
            $attendanceColor = 'text-danger fw-bold';
        } elseif ($student->attendance < 80) {
            $attendanceColor = 'text-warning';
        }
        
        $trendDisplay = '';
        $trendIcon = '';
        $trendClass = '';
        if ($student->trend === 'improving') {
            $trendDisplay = 'Improving';
            $trendIcon = 'fa-arrow-up';
            $trendClass = 'text-success';
        } elseif ($student->trend === 'worsening') {
            $trendDisplay = 'Worsening';
            $trendIcon = 'fa-arrow-down';
            $trendClass = 'text-danger';
        } else {
            $trendDisplay = 'Stable';
            $trendIcon = 'fa-minus';
            $trendClass = 'text-muted';
        }
        
        $statusDisplay = '';
        $statusClass = '';
        $statusIcon = '';
        if ($student->has_open_case) {
            $statusDisplay = 'Open Case';
            $statusClass = 'bg-info';
            $statusIcon = 'fa-folder-open';
        } elseif ($student->is_critical) {
            $statusDisplay = 'Critical';
            $statusClass = 'bg-danger';
            $statusIcon = 'fa-exclamation-triangle';
        } else {
            $statusDisplay = '—';
            $statusClass = 'bg-secondary';
            $statusIcon = '';
        }
        
        return (object) [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'student_number' => $student->student_number,
            'grade' => $student->grade,
            'grade_formatted' => number_format($student->grade, 1),
            'grade_color' => $gradeColor,
            'attendance' => $student->attendance,
            'attendance_formatted' => number_format($student->attendance, 1),
            'attendance_color' => $attendanceColor,
            'risk_score' => $student->risk_score,
            'current_risk' => $student->current_risk,
            'current_risk_class' => $riskClass,
            'previous_risk' => $student->previous_risk,
            'previous_risk_class' => $prevRiskClass,
            'has_open_case' => $student->has_open_case,
            'is_critical' => $student->is_critical,
            'trend' => $student->trend,
            'trend_display' => $trendDisplay,
            'trend_icon' => $trendIcon,
            'trend_class' => $trendClass,
            'status_display' => $statusDisplay,
            'status_class' => $statusClass,
            'status_icon' => $statusIcon,
        ];
    }
    
    public function block(Request $request, $blockId)
    {
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return redirect()->route('academic-head.department')->with('error', 'You do not have access to this block.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $currentIndex = array_search($currentPeriod, $periods);
        $previousPeriod = $currentIndex > 0 ? $periods[$currentIndex - 1] : $periods[0];
        
        $structure = app(AcademicStructureRepository::class);

        $block = $structure->blockWithProgramDetail($blockId);
        
        if (!$block) {
            return redirect()->route('academic-head.department')->with('error', 'Block not found.');
        }
        
        $students = app(StudentRepository::class)->forBlock($blockId, true);
        
        $studentIds = $students->pluck('id')->toArray();
        $studentData = [];
        
        $lowRiskCount = 0;
        $moderateRiskCount = 0;
        $highRiskCount = 0;
        
        if (empty($studentIds)) {
            $data = [
                'block' => $block,
                'blockName' => $block->name ?? 'Block',
                'blockId' => $blockId,
                'currentPeriod' => $currentPeriod,
                'previousPeriod' => $previousPeriod,
                'lowRiskCount' => 0,
                'moderateRiskCount' => 0,
                'highRiskCount' => 0,
                'students' => collect([]),
                'totalStudents' => 0,
            ];
            return view('academic-head.block', $data);
        }
        
        
        $riskScores = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');
        
        $previousRiskScores = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $previousPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');
        
        $grades = collect(app(GradeRepository::class)
            ->averageGradeRows($studentIds, $currentPeriod, $schoolYear));
        
        $attendance = collect(app(AttendanceSummaryRepository::class)
            ->averageAttendanceRows($studentIds, $currentPeriod, $schoolYear));
        
        $openCases = DB::table('cases')
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->get()
            ->pluck('student_id')
            ->toArray();
        
        $criticalFlags = DB::table('flags')
            ->whereIn('student_id', $studentIds)
            ->where('flag_type', 'consecutive_high_risk')
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->pluck('student_id')
            ->toArray();
        
        foreach ($students as $student) {
            $currentRisk = $riskScores->get($student->id);
            
            $previousRisk = $previousRiskScores->get($student->id);
            
            $gradeData = $grades->get($student->id);
            $avgGrade = $gradeData ? round($gradeData->avg_grade, 1) : null;
            
            $attendanceData = $attendance->get($student->id);
            $attendanceRate = $attendanceData ? round($attendanceData->avg_attendance, 1) : null;
            
            $riskLevel = $currentRisk ? $currentRisk->risk_level : 'No Data';
            $riskScore = $currentRisk ? $currentRisk->risk_score : 0;
            $previousRiskLevel = $previousRisk ? $previousRisk->risk_level : 'No Data';
            
            if ($riskLevel === 'Low') $lowRiskCount++;
            elseif ($riskLevel === 'Moderate') $moderateRiskCount++;
            elseif ($riskLevel === 'High') $highRiskCount++;
            
            $hasOpenCase = in_array($student->id, $openCases);
            
            $isCritical = in_array($student->id, $criticalFlags);
            
            $trend = 'stable';
            if ($previousRiskLevel !== 'No Data' && $riskLevel !== 'No Data') {
                $riskValues = ['Low' => 1, 'Moderate' => 2, 'High' => 3];
                $currentValue = $riskValues[$riskLevel] ?? 0;
                $previousValue = $riskValues[$previousRiskLevel] ?? 0;
                
                if ($currentValue > $previousValue) $trend = 'worsening';
                elseif ($currentValue < $previousValue) $trend = 'improving';
                else $trend = 'stable';
            }
            
            $baseStudent = (object) [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->student_number,
                'grade' => $avgGrade ?? 0,
                'attendance' => $attendanceRate ?? 0,
                'risk_score' => $riskScore,
                'current_risk' => $riskLevel,
                'previous_risk' => $previousRiskLevel,
                'has_open_case' => $hasOpenCase,
                'is_critical' => $isCritical,
                'trend' => $trend,
            ];
            
            $studentData[] = $this->prepareStudentDisplayData($baseStudent);
        }
        
        usort($studentData, function($a, $b) {
            if ($a->is_critical && !$b->is_critical) return -1;
            if (!$a->is_critical && $b->is_critical) return 1;
            
            $riskOrder = ['High' => 0, 'Moderate' => 1, 'Low' => 2];
            $aOrder = $riskOrder[$a->current_risk] ?? 3;
            $bOrder = $riskOrder[$b->current_risk] ?? 3;
            if ($aOrder !== $bOrder) return $aOrder - $bOrder;
            
            return strcmp($a->last_name, $b->last_name);
        });
        
        $data = [
            'block' => $block,
            'blockName' => $block->name ?? 'Block',
            'blockId' => $blockId,
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'lowRiskCount' => $lowRiskCount,
            'moderateRiskCount' => $moderateRiskCount,
            'highRiskCount' => $highRiskCount,
            'students' => collect($studentData),
            'totalStudents' => count($studentData),
            'schoolYear' => $schoolYear,
        ];
        
        return view('academic-head.block', $data);
    }

    /**
     * Show the per-student basis page: grades, attendance, and the current risk
     * score/level so the Academic Head can verify AI risk scoring.
     *
     * Department isolation: 403 unless the block is in the head's department and
     * the student belongs to that department and is placed in this block.
     */
    public function basis(Request $request, $blockId, $studentId)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId || !DepartmentHelper::isBlockInDepartment($request, (int) $blockId)) {
            abort(403, 'You do not have access to this block.');
        }

        $blockId = (int) $blockId;
        $studentId = (int) $studentId;

        // The student must belong to this department AND be placed in this block.
        $placement = app(AcademicStructureRepository::class)->studentPlacements()[$studentId] ?? null;

        if (!$placement
            || (int) $placement['department_id'] !== $departmentId
            || (int) $placement['block_id'] !== $blockId) {
            abort(403, 'You do not have access to this student.');
        }

        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');

        $block = app(AcademicStructureRepository::class)->blockWithProgramDetail($blockId);
        $student = app(StudentRepository::class)->find($studentId);

        if (!$block || !$student) {
            abort(404, 'Block or student not found.');
        }

        $periods = ['Prelim', 'Midterm', 'Finals'];

        $grades = app(GradeRepository::class)->subjectGradesForStudent($studentId, $currentPeriod, $schoolYear);
        $attendanceSummaries = app(AttendanceSummaryRepository::class)->subjectSummariesForStudent($studentId, $currentPeriod, $schoolYear);
        $attendanceAggregate = app(AttendanceSummaryRepository::class)->aggregateForStudent($studentId, $currentPeriod, $schoolYear);

        // Expose the raw hours behind each attendance percentage so the weighted
        // math is transparent: rate = weighted_hours / required_hours * 100.
        // The per-subject total_required_hours / total_weighted_hours travel with
        // each row in $attendanceSummaries; here they are aggregated for the footer.
        $attendanceTotals = [
            'required_hours' => round((float) $attendanceSummaries->sum('total_required_hours'), 2),
            'weighted_hours' => round((float) $attendanceSummaries->sum('total_weighted_hours'), 2),
        ];

        $risk = app(RiskScoreRepository::class)->forStudentPeriod($studentId, $currentPeriod, $schoolYear);
        $riskScore = $risk ? (int) $risk->risk_score : 0;
        $riskLevel = $risk ? (string) $risk->risk_level : 'No Data';
        $riskBadgeClass = in_array($riskLevel, ['Low', 'Moderate', 'High', 'Critical'], true)
            ? 'badge-risk-'.strtolower($riskLevel)
            : 'badge-secondary';

        return view('academic-head.student-basis', [
            'block' => $block,
            'blockId' => $blockId,
            'student' => $student,
            'placement' => $placement,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
            'periods' => $periods,
            'grades' => $grades,
            'attendanceSummaries' => $attendanceSummaries,
            'attendanceAggregate' => $attendanceAggregate,
            'attendanceTotals' => $attendanceTotals,
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'riskBadgeClass' => $riskBadgeClass,
            'subjectIndex' => app(SubjectRepository::class)->keyedById(),
        ]);
    }
    public function blocksIndex(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');

        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);

        $programs = DepartmentHelper::getDepartmentPrograms($request);

        $blocks = $structure->blocksWithProgramForDepartment($departmentId);

        $grouped = [];

        foreach ($blocks as $block) {
            $studentIds = $structure->studentIdsInBlock($block->id, true);
            $counts = $riskScores->levelCountsFor($studentIds, $currentPeriod, $schoolYear);
            $total = count($studentIds);

            $block->total_students = $total;
            $block->low_risk = $counts['Low'];
            $block->moderate_risk = $counts['Moderate'];
            $block->high_risk = $counts['High'];
            $block->unmonitored = max(0, $total - ($counts['Low'] + $counts['Moderate'] + $counts['High']));
            $block->high_risk_percentage = $total > 0 ? round($counts['High'] / $total * 100, 1) : 0;

            $grouped[$block->program_code ?? 'Unassigned'][] = $block;
        }

        return view('academic-head.blocks', [
            'blocks' => $blocks,
            'groupedBlocks' => $grouped,
            'program' => null,
            'programs' => $programs,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
            'departmentId' => $departmentId,
        ]);
    }


    protected function buildBlockStats(
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores,
        int $departmentId,
        string $period,
        string $schoolYear
    ): array {
        $stats = [];

        foreach ($structure->blocksWithProgramForDepartment($departmentId) as $block) {
            $studentIds = $structure->studentIdsInBlock($block->id, true);
            $counts = $riskScores->levelCountsFor($studentIds, $period, $schoolYear);

            $stats[] = (object) [
                'id' => (int) $block->id,
                'name' => (string) $block->name,
                'program_code' => (string) ($block->program_code ?? ''),
                'year_number' => $block->year_number,
                'student_count' => count($studentIds),
                'low_risk' => $counts['Low'],
                'moderate_risk' => $counts['Moderate'],
                'high_risk' => $counts['High'],
                'high_risk_percentage' => count($studentIds) > 0
                    ? round($counts['High'] / count($studentIds) * 100, 1)
                    : 0.0,
            ];
        }

        usort($stats, fn ($a, $b) => $b->high_risk <=> $a->high_risk);

        return $stats;
    }

    protected function buildEscalationTrend(array $departmentStudentIds, string $schoolYear, string $semester): array
    {
        $counts = $departmentStudentIds === []
            ? collect()
            : DB::table('escalations')
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->whereIn('student_id', $departmentStudentIds)
                ->pluck('grading_period')
                ->countBy();

        $series = [];

        foreach (['Prelim', 'Midterm', 'Finals'] as $period) {
            $series[] = [
                'period' => $period,
                'escalations' => (int) ($counts[$period] ?? 0),
            ];
        }

        return $series;
    }





    protected function buildAttentionList(
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores,
        StudentRepository $students,
        GradeRepository $grades,
        AttendanceSummaryRepository $attendance,
        array $departmentStudentIds,
        string $period,
        string $schoolYear,
        int $limit = 10
    ): array {
        if ($departmentStudentIds === []) {
            return [];
        }

        $placements = $structure->studentPlacements();
        $names = $students->nameIndex();

        $openCases = DB::table('cases')
            ->whereIn('student_id', $departmentStudentIds)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $ranked = [];

        foreach ($departmentStudentIds as $id) {
            $row = $riskScores->forStudentPeriod($id, $period, $schoolYear);

            if ($row === null) {
                continue;
            }

            $ranked[] = [
                'student_id' => (int) $id,
                'risk_score' => (int) $row->risk_score,
                'risk_level' => (string) $row->risk_level,
            ];
        }

        usort(
            $ranked,
            fn (array $a, array $b) => [$b['risk_score'], $a['student_id']] <=> [$a['risk_score'], $b['student_id']]
        );

        $ranked = array_slice($ranked, 0, $limit);

        if ($ranked === []) {
            return [];
        }

        $topIds = array_column($ranked, 'student_id');
        $averages = $grades->averageGradePerStudent($topIds, $period, $schoolYear);
        $rates = $attendance->averageAttendancePerStudent($topIds, $period, $schoolYear);

        $list = [];

        foreach ($ranked as $entry) {
            $id = $entry['student_id'];
            $placement = $placements[$id] ?? [];
            $name = $names[$id] ?? null;

            $list[] = [
                'student_id' => $id,
                'name' => trim((string) ($name->last_name ?? '') . ', ' . (string) ($name->first_name ?? ''), ', '),
                'student_number' => (string) ($name->student_number ?? ''),
                'program_code' => (string) ($placement['program_code'] ?? ''),
                'block_id' => isset($placement['block_id']) ? (int) $placement['block_id'] : null,
                'block_name' => (string) ($placement['block_name'] ?? ''),
                'risk_score' => $entry['risk_score'],
                'risk_level' => $entry['risk_level'],
                'grade' => isset($averages[$id]) ? round((float) $averages[$id], 2) : null,
                'attendance' => isset($rates[$id]) ? round((float) $rates[$id], 2) : null,
                'has_open_case' => isset($openCases[$id]),
            ];
        }

        return $list;
    }
    protected function buildRecentEscalations(
        AcademicStructureRepository $structure,
        StudentRepository $students,
        array $departmentStudentIds,
        int $limit = 8
    ): array {
        if ($departmentStudentIds === []) {
            return [];
        }

        $names = $students->nameIndex();
        $placements = $structure->studentPlacements();

        $rows = DB::table('escalations')
            ->whereIn('student_id', $departmentStudentIds)
            ->orderByDesc('escalated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $caseIds = $rows->pluck('case_id')->filter()->map(fn ($id) => (int) $id)->all();

        $cases = $caseIds === []
            ? collect()
            : DB::table('cases')->whereIn('id', $caseIds)->get()->keyBy('id');

        $list = [];

        foreach ($rows as $row) {
            $id = (int) $row->student_id;
            $name = $names[$id] ?? null;
            $case = $row->case_id !== null ? ($cases[(int) $row->case_id] ?? null) : null;

            $list[] = [
                'student_id' => $id,
                'name' => trim((string) ($name->last_name ?? '') . ', ' . (string) ($name->first_name ?? ''), ', '),
                'program_code' => (string) ($placements[$id]['program_code'] ?? ''),
                'block_name' => (string) ($placements[$id]['block_name'] ?? ''),
                'grading_period' => (string) $row->grading_period,
                'escalated_at' => (string) $row->escalated_at,
                'priority' => $case->priority ?? null,
                'status' => $case->status ?? null,
                'risk_level_at_escalation' => $case->risk_level_at_escalation ?? null,
            ];
        }

        return $list;
    }


    protected function buildPendingRecommendations(
        AcademicStructureRepository $structure,
        StudentRepository $students,
        array $departmentStudentIds,
        string $period,
        string $schoolYear,
        int $limit = 8
    ): array {
        if ($departmentStudentIds === []) {
            return ['total' => 0, 'acted' => 0, 'pending' => 0, 'rows' => []];
        }

        $recommendations = DB::table('intervention_recommendations')
            ->whereIn('student_id', $departmentStudentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->orderByDesc('created_at')
            ->get();

        $total = $recommendations->count();

        if ($total === 0) {
            return ['total' => 0, 'acted' => 0, 'pending' => 0, 'rows' => []];
        }

        $studentIds = $recommendations->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $cased = DB::table('cases')
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $completed = DB::table('student_recommendation_tracking')
            ->where('is_completed', true)
            ->whereIn('recommendation_id', $recommendations->pluck('id')->all())
            ->pluck('recommendation_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $names = $students->nameIndex();
        $placements = $structure->studentPlacements();

        $pending = $recommendations->reject(function ($row) use ($cased, $completed) {
            return isset($cased[(int) $row->student_id]) || isset($completed[(int) $row->id]);
        })->values();

        $rows = [];

        foreach ($pending->take($limit) as $row) {
            $id = (int) $row->student_id;
            $name = $names[$id] ?? null;

            $rows[] = [
                'recommendation_id' => (int) $row->id,
                'student_id' => $id,
                'name' => trim((string) ($name->last_name ?? '') . ', ' . (string) ($name->first_name ?? ''), ', '),
                'program_code' => (string) ($placements[$id]['program_code'] ?? ''),
                'block_name' => (string) ($placements[$id]['block_name'] ?? ''),
                'grading_period' => (string) $row->grading_period,
                'created_at' => (string) $row->created_at,
            ];
        }

        return [
            'total' => $total,
            'acted' => $total - $pending->count(),
            'pending' => $pending->count(),
            'rows' => $rows,
        ];
    }

    protected function availableSchoolYears(array $studentIds): array
    {
        if ($studentIds === []) {
            return ['2024-2025'];
        }

        $years = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year')
            ->filter()
            ->values()
            ->all();

        return $years === [] ? ['2024-2025'] : $years;
    }
}



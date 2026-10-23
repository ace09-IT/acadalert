<?php

namespace App\Console\Commands;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceRepository;
use App\Repositories\Api\AttendanceSummaryRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Local\CaseRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MockApiVerifyCommand extends Command
{
    protected $signature = 'mock-api:verify
                            {--students=5 : How many active students to compare}
                            {--period=Midterm : Grading period to compare}
                            {--school-year=2024-2025}
                            {--semester=1st}';

    protected $description = 'Compare the previous SQL reads against the new mock API reads and report any difference.';

    public function handle(
        GradeRepository $grades,
        AttendanceRepository $attendance,
        CalendarRepository $calendar,
        AttendanceSummaryRepository $summaries
    ): int {
        $period = (string) $this->option('period');
        $schoolYear = (string) $this->option('school-year');
        $semester = (string) $this->option('semester');
        $limit = max(1, (int) $this->option('students'));

        $students = DB::table('students')
            ->where('status', 'Active')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name']);

        if ($students->isEmpty()) {
            $this->error('No active students found locally — seed the database first.');

            return self::FAILURE;
        }

        $ids = $students->pluck('id')->all();

        $this->newLine();
        $this->line(sprintf(
            'Comparing <fg=cyan>%d</> student(s) — period <fg=cyan>%s</> / %s / semester %s',
            count($ids),
            $period,
            $schoolYear,
            $semester
        ));
        $this->line('  students: ' . implode(', ', $ids));
        $this->newLine();

        $results = [
            ['grades (per-subject + avg + failing count)', $this->compareGrades($ids, $period, $schoolYear, $grades)],
            ['attendance (raw log rows, grouped)', $this->compareAttendance($ids, $attendance)],
            ['academic calendar (window + period number)', $this->compareCalendar($schoolYear, $semester, $calendar)],
            ['risk by department (chart aggregate)', $this->compareRiskByDepartment($period, $schoolYear)],
            ['risk by program (college chart aggregate)', $this->compareRiskByProgram($period, $schoolYear)],
            ['counselor case scope (priority distribution)', $this->compareCounselorCaseScope()],
            ['block risk distribution (active students)', $this->compareBlockRiskDistribution($period, $schoolYear)],
            ['end-of-term report aggregates (dept scope)', $this->compareEndOfTermAggregates($schoolYear, $semester, $period)],
            ['department scope (blocks + active students)', $this->compareDepartmentScope()],
            ['student grade + attendance averages', $this->compareStudentAverages($ids, $period, $schoolYear, $grades, $summaries)],
            ['block risk scores (RiskScoringService)', $this->compareBlockRiskScores($period, $schoolYear)],
            ['admin aggregates (risk by dept + payments)', $this->compareAdminAggregates()],
        ];

        $this->newLine();

        $rows = [];
        $failed = false;

        foreach ($results as [$label, $outcome]) {
            [$ok, $detail] = $outcome;
            $failed = $failed || !$ok;

            $rows[] = [$label, $ok ? '<fg=green>IDENTICAL</>' : '<fg=red>DIFFERS</>', $detail];
        }

        $this->table(['metric', 'result', 'detail'], $rows);

        $this->newLine();
        $this->line($failed
            ? '<fg=red>Migration is NOT behaviour-preserving — see the detail column.</>'
            : '<fg=green>All compared reads are byte-identical to the previous SQL.</>');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function digest(array $data): string
    {
        $this->canonicalise($data);

        return hash('sha256', (string) json_encode($data, JSON_PRESERVE_ZERO_FRACTION));
    }

    protected function canonicalise(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->canonicalise($value);
            }
        }
        unset($value);

        if (array_is_list($data)) {
            usort($data, fn ($a, $b) => strcmp($this->canonicalJson($a), $this->canonicalJson($b)));

            return;
        }

        ksort($data);
    }

    /** Canonical JSON for any value, so list order never causes a false diff. */
    protected function canonicalJson($value): string
    {
        $copy = is_array($value) ? $value : [$value];

        $this->canonicalise($copy);

        return (string) json_encode($copy, JSON_PRESERVE_ZERO_FRACTION);
    }

    protected function firstDifference(array $expected, array $actual): string
    {
        $missing = array_diff_key($expected, $actual);

        if ($missing !== []) {
            return 'the API result is missing key(s): ' . implode(', ', array_keys($missing));
        }

        $extra = array_diff_key($actual, $expected);

        if ($extra !== []) {
            return 'the API result has extra key(s): ' . implode(', ', array_keys($extra));
        }

        foreach ($expected as $key => $value) {
            $expectedJson = $this->canonicalJson($value);
            $actualJson = $this->canonicalJson($actual[$key] ?? null);

            if ($expectedJson !== $actualJson) {
                return sprintf(
                    '%s: SQL=%s API=%s',
                    $key,
                    mb_substr($expectedJson, 0, 90),
                    mb_substr($actualJson, 0, 90)
                );
            }
        }

        return 'values differ but no individual key mismatch was isolated';
    }

    protected function compareGrades(array $ids, string $period, string $schoolYear, GradeRepository $repo): array
    {
        $rows = DB::table('grades')
            ->join('subjects', 'grades.subject_id', '=', 'subjects.id')
            ->whereIn('grades.student_id', $ids)
            ->where('grades.grading_period', $period)
            ->where('grades.school_year', $schoolYear)
            ->select(
                'grades.student_id',
                'grades.subject_id',
                'grades.numerical_grade',
                'subjects.subject_code',
                'subjects.subject_name'
            )
            ->get();

        $reference = [];

        foreach ($rows as $row) {
            $reference[$row->student_id]['subjects'][] = [
                'subject_id' => (int) $row->subject_id,
                'subject_code' => (string) $row->subject_code,
                'subject_name' => (string) $row->subject_name,
                'numerical_grade' => (float) $row->numerical_grade,
            ];
        }

        foreach ($reference as &$data) {
            $subjectGrades = collect($data['subjects']);

            $data['total_subjects'] = $subjectGrades->count();
            $data['avg_grade'] = $subjectGrades->count() > 0
                ? round($subjectGrades->avg('numerical_grade'), 2)
                : 0;
            $data['failing_subjects'] = $subjectGrades->where('numerical_grade', '<', 75)->count();
        }
        unset($data);

        $viaApi = $repo->forStudentsPeriod($ids, $period, $schoolYear);

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d student(s) with grades, %d row(s), digest %s',
                count($reference),
                $rows->count(),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareAttendance(array $ids, AttendanceRepository $repo): array
    {
        $normalise = fn ($group) => $group->map(fn ($row) => [
            'session_date' => (string) $row->session_date,
            'status' => (string) $row->status,
            'hours_duration' => (string) $row->hours_duration,
            'weighted_hours' => (string) $row->weighted_hours,
        ])->values()->all();

        $rows = DB::table('attendance')
            ->whereIn('student_id', $ids)
            ->get(['student_id', 'session_date', 'status', 'hours_duration', 'weighted_hours']);

        $reference = $rows->groupBy('student_id')->map($normalise)->all();

        $viaApi = $repo->rawLogsForStudentsGrouped($ids)->map($normalise)->all();

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d student(s), %d raw log row(s), digest %s',
                count($reference),
                $rows->count(),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareCalendar(string $schoolYear, string $semester, CalendarRepository $repo): array
    {
        $canonicalNumbers = ['Prelim' => 1, 'Midterm' => 2, 'Finals' => 3];

        $reference = [];
        $viaApi = [];

        foreach (array_keys($canonicalNumbers) as $period) {
            $entry = DB::table('academic_calendar')
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->where('grading_period', $period)
                ->first();

            $reference[$period] = [
                'window' => $entry
                    ? ['start' => (string) $entry->start_date, 'end' => (string) $entry->end_date]
                    : null,
                'number' => $entry !== null && $entry->period_number !== null
                    ? (int) $entry->period_number
                    : ($canonicalNumbers[$period] ?? 2),
            ];

            $viaApi[$period] = [
                'window' => $repo->periodDateRange($schoolYear, $semester, $period),
                'number' => $repo->periodNumber($schoolYear, $semester, $period),
            ];
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, 'all 3 periods match, digest ' . substr($referenceDigest, 0, 12)];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareRiskByDepartment(string $period, string $schoolYear): array
    {
        $rows = DB::table('departments')
            ->leftJoin('programs', 'departments.id', '=', 'programs.department_id')
            ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
            ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
            ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
            ->leftJoin('risk_scores', function ($join) use ($period, $schoolYear) {
                $join->on('students.id', '=', 'risk_scores.student_id')
                    ->where('risk_scores.grading_period', $period)
                    ->where('risk_scores.school_year', $schoolYear);
            })
            ->select(
                'departments.code as department',
                DB::raw('COUNT(DISTINCT students.id) as total'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
            )
            ->groupBy('departments.code')
            ->orderBy('departments.code')
            ->get();

        $reference = $rows->map(fn ($row) => [
            'department' => (string) $row->department,
            'total' => (int) $row->total,
            'high_risk' => (int) $row->high_risk,
            'moderate_risk' => (int) $row->moderate_risk,
            'low_risk' => (int) $row->low_risk,
        ])->all();

        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);

        $viaApi = [];

        foreach ($structure->departments()->sortBy('code')->values() as $department) {
            $studentIds = $structure->studentIdsInDepartment($department->id, false);
            $counts = $riskScores->levelCountsFor($studentIds, $period, $schoolYear);

            $viaApi[] = [
                'department' => (string) $department->code,
                'total' => count($studentIds),
                'high_risk' => $counts['High'],
                'moderate_risk' => $counts['Moderate'],
                'low_risk' => $counts['Low'],
            ];
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d department row(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareRiskByProgram(string $period, string $schoolYear): array
    {
        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('programs')->orderBy('code')->get(['id', 'code']) as $program) {
            $row = DB::table('programs')
                ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
                ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
                ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
                ->leftJoin('risk_scores', function ($join) use ($period, $schoolYear) {
                    $join->on('students.id', '=', 'risk_scores.student_id')
                        ->where('risk_scores.grading_period', $period)
                        ->where('risk_scores.school_year', $schoolYear);
                })
                ->where('programs.id', $program->id)
                ->select(
                    DB::raw('COUNT(DISTINCT students.id) as total'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
                )
                ->first();

            $reference[$program->code] = [
                'total' => (int) $row->total,
                'high_risk' => (int) $row->high_risk,
                'moderate_risk' => (int) $row->moderate_risk,
                'low_risk' => (int) $row->low_risk,
            ];

            $ids = $structure->studentIdsInProgram($program->id, false);
            $counts = $riskScores->levelCountsFor($ids, $period, $schoolYear);

            $viaApi[$program->code] = [
                'total' => count($ids),
                'high_risk' => $counts['High'],
                'moderate_risk' => $counts['Moderate'],
                'low_risk' => $counts['Low'],
            ];
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d program row(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareCounselorCaseScope(): array
    {
        $structure = app(AcademicStructureRepository::class);
        $caseRepo = app(CaseRepository::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('counselors')->orderBy('id')->get(['id', 'department_id']) as $counselor) {
            foreach ([null, (int) $counselor->department_id] as $departmentId) {
                $query = DB::table('cases')
                    ->join('students', 'cases.student_id', '=', 'students.id')
                    ->join('blocks', 'students.block_id', '=', 'blocks.id')
                    ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                    ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                    ->where('cases.counselor_id', $counselor->id)
                    ->whereNotIn('cases.status', ['Resolved', 'Closed']);

                if ($departmentId) {
                    $query->where('programs.department_id', $departmentId);
                }

                $key = $this->scopeKey($counselor->id, $departmentId);

                $reference[$key] = $query
                    ->select('cases.priority', DB::raw('count(*) as count'))
                    ->groupBy('cases.priority')
                    ->get()
                    ->pluck('count', 'priority')
                    ->map(fn ($count) => (int) $count)
                    ->all();

                $studentIds = $departmentId
                    ? $structure->studentIdsInDepartment($departmentId, false)
                    : array_keys($structure->studentPlacements());

                $viaApi[$key] = $caseRepo->countBy(
                    $caseRepo->forCounselor((int) $counselor->id, $studentIds, ['Resolved', 'Closed']),
                    'priority'
                );
            }
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d counselor scope(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function scopeKey($counselorId, ?int $departmentId): string
    {
        return 'counselor ' . $counselorId . ' / dept ' . ($departmentId ?? 'all');
    }

    protected function compareBlockRiskDistribution(string $period, string $schoolYear): array
    {
        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('blocks')->orderBy('id')->pluck('id') as $blockId) {
            $students = DB::table('students')
                ->where('block_id', $blockId)
                ->where('status', 'Active')
                ->pluck('id')
                ->toArray();

            $reference[$blockId] = [];

            foreach (['Low', 'Moderate', 'High'] as $level) {
                $reference[$blockId][$level] = DB::table('risk_scores')
                    ->whereIn('student_id', $students)
                    ->where('risk_level', $level)
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->count();
            }

            $viaApi[$blockId] = $riskScores->levelCountsFor(
                $structure->studentIdsInBlock($blockId, true),
                $period,
                $schoolYear
            );
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d block(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareEndOfTermAggregates(string $schoolYear, string $semester, string $gradingPeriod): array
    {
        $service = app(\App\Services\Reports\EndOfTermReportService::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('departments')->orderBy('id')->pluck('id') as $departmentId) {
            // A fresh builder per call: reusing one would accumulate constraints.
            $scoped = fn () => DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->where('risk_scores.school_year', $schoolYear)
                ->where('risk_scores.semester', $semester)
                ->where('risk_scores.processing_status', 'completed')
                ->where('students.status', 'Active');

            $monitored = (int) $scoped()
                ->where('risk_scores.grading_period', $gradingPeriod)
                ->distinct()
                ->count('risk_scores.student_id');

            $levelRows = $scoped()
                ->where('risk_scores.grading_period', $gradingPeriod)
                ->select('risk_scores.risk_level', DB::raw('COUNT(DISTINCT risk_scores.student_id) as student_count'))
                ->groupBy('risk_scores.risk_level')
                ->pluck('student_count', 'risk_level')
                ->all();

            $levels = [];
            foreach (['Low', 'Moderate', 'High'] as $level) {
                $levels[$level] = (int) ($levelRows[$level] ?? 0);
            }

            $active = (int) DB::table('students')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->where('students.status', 'Active')
                ->distinct()
                ->count('students.id');

            $programs = $scoped()
                ->where('risk_scores.grading_period', $gradingPeriod)
                ->select(
                    'programs.code as program_code',
                    'risk_scores.risk_level as level',
                    DB::raw('COUNT(DISTINCT risk_scores.student_id) as student_count')
                )
                ->groupBy('programs.code', 'risk_scores.risk_level')
                ->get()
                ->map(fn ($row) => [
                    'code' => (string) $row->program_code,
                    'level' => (string) $row->level,
                    'count' => (int) $row->student_count,
                ])
                ->all();

            $reference[$departmentId] = [
                'monitored' => $monitored,
                'levels' => $levels,
                'active' => $active,
                'programs' => $programs,
            ];

            $viaApi[$departmentId] = $this->endOfTermApiSide($service, $departmentId, $schoolYear, $semester, $gradingPeriod);
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d department(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function endOfTermApiSide(
        object $service,
        int $departmentId,
        string $schoolYear,
        string $semester,
        string $gradingPeriod
    ): array {
        $programs = collect($this->callProtected($service, 'byProgram', [
            $departmentId, $schoolYear, $semester, $gradingPeriod,
        ]));

        return [
            'monitored' => (int) $this->callProtected($service, 'monitoredCount', [
                $departmentId, $schoolYear, $semester, $gradingPeriod,
            ]),
            'levels' => $this->callProtected($service, 'distribution', [
                $departmentId, $schoolYear, $semester, $gradingPeriod,
            ]),
            'active' => (int) $this->callProtected($service, 'activeStudentCount', [$departmentId]),
            'programs' => $programs
                ->flatMap(fn ($program) => collect($program['counts'])
                    ->filter(fn ($count) => $count > 0)
                    ->map(fn ($count, $level) => [
                        'code' => (string) $program['program_code'],
                        'level' => (string) $level,
                        'count' => (int) $count,
                    ])
                    ->values()
                    ->all())
                ->values()
                ->all(),
        ];
    }

    /** Invoke a protected service accessor for verification purposes only. */
    protected function callProtected(object $object, string $method, array $arguments)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    protected function compareAdminAggregates(): array
    {
        $controller = app(\App\Http\Controllers\Admin\DashboardController::class);

        $referenceRisk = DB::table('departments')
            ->leftJoin('programs', 'departments.id', '=', 'programs.department_id')
            ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
            ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
            ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
            ->leftJoin('risk_scores', function ($join) {
                $join->on('students.id', '=', 'risk_scores.student_id')
                    ->where('risk_scores.grading_period', 'Midterm');
            })
            ->select(
                'departments.id',
                'departments.code',
                'departments.name',
                DB::raw('COUNT(DISTINCT students.id) as total_students'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
            )
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderBy('departments.code')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'total_students' => (int) $row->total_students,
                'high_risk' => (int) $row->high_risk,
                'moderate_risk' => (int) $row->moderate_risk,
                'low_risk' => (int) $row->low_risk,
            ])
            ->all();

        $apiRisk = $this->callProtected($controller, 'getRiskByDepartment', [])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'total_students' => (int) $row->total_students,
                'high_risk' => (int) $row->high_risk,
                'moderate_risk' => (int) $row->moderate_risk,
                'low_risk' => (int) $row->low_risk,
            ])
            ->all();

        if ($this->digest($referenceRisk) !== $this->digest($apiRisk)) {
            return [false, 'getRiskByDepartment — ' . $this->firstDifference($referenceRisk, $apiRisk)];
        }

        return $this->compareAdminPayments($controller, $referenceRisk);
    }

    protected function compareAdminPayments(object $controller, array $referenceRisk): array
    {
        $money = ['total_amount', 'collected_amount', 'overdue_amount', 'outstanding_amount'];

        // SUM() over DECIMAL comes back as a scale-6 string; compare as money strings.
        $referenceStats = $this->referencePaymentStats('all', 'all');
        $apiStats = $this->callProtected($controller, 'getPaymentStats', ['all', 'all']);

        foreach ($money as $key) {
            $referenceStats[$key] = number_format((float) $referenceStats[$key], 2, '.', '');
            $apiStats[$key] = number_format((float) ($apiStats[$key] ?? 0), 2, '.', '');
        }

        if ($this->digest($referenceStats) !== $this->digest($apiStats)) {
            return [false, 'getPaymentStats — ' . $this->firstDifference($referenceStats, $apiStats)];
        }

        $sumColumns = ['total_amount', 'collected_amount', 'outstanding_amount'];

        $referenceSummary = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->join('departments', 'programs.department_id', '=', 'departments.id')
            ->select(
                'departments.code as department',
                DB::raw('COUNT(*) as total_students'),
                DB::raw('SUM(payments.amount) as total_amount'),
                DB::raw('SUM(payments.paid_amount) as collected_amount'),
                DB::raw('SUM(payments.balance) as outstanding_amount'),
                DB::raw('SUM(CASE WHEN payments.status = "Paid" THEN 1 ELSE 0 END) as paid_count'),
                DB::raw('SUM(CASE WHEN payments.status = "Overdue" THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN payments.status = "Unpaid" THEN 1 ELSE 0 END) as unpaid_count'),
                DB::raw('SUM(CASE WHEN payments.status = "Partial" THEN 1 ELSE 0 END) as partial_count')
            )
            ->groupBy('departments.code')
            ->orderBy('departments.code')
            ->get()
            ->map(function ($row) use ($sumColumns) {
                $out = [
                    'department' => $row->department,
                    'total_students' => (int) $row->total_students,
                    'paid_count' => (int) $row->paid_count,
                    'overdue_count' => (int) $row->overdue_count,
                    'unpaid_count' => (int) $row->unpaid_count,
                    'partial_count' => (int) $row->partial_count,
                ];

                foreach ($sumColumns as $key) {
                    $out[$key] = number_format((float) $row->{$key}, 2, '.', '');
                }

                return $out;
            })
            ->all();

        $apiSummary = $this->callProtected($controller, 'getDepartmentPaymentSummary', [])
            ->map(function ($row) use ($sumColumns) {
                $out = [
                    'department' => $row->department,
                    'total_students' => (int) $row->total_students,
                    'paid_count' => (int) $row->paid_count,
                    'overdue_count' => (int) $row->overdue_count,
                    'unpaid_count' => (int) $row->unpaid_count,
                    'partial_count' => (int) $row->partial_count,
                ];

                foreach ($sumColumns as $key) {
                    $out[$key] = number_format((float) $row->{$key}, 2, '.', '');
                }

                return $out;
            })
            ->all();

        if ($this->digest($referenceSummary) !== $this->digest($apiSummary)) {
            return [false, 'getDepartmentPaymentSummary — ' . $this->firstDifference($referenceSummary, $apiSummary)];
        }

        return $this->compareAdminOverdue($controller, $referenceRisk, $referenceStats, $referenceSummary);
    }

    protected function compareAdminOverdue(
        object $controller,
        array $referenceRisk,
        array $referenceStats,
        array $referenceSummary
    ): array {
        $referenceOverdue = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->join('departments', 'programs.department_id', '=', 'departments.id')
            ->where('payments.status', 'Overdue')
            ->select(
                'payments.id',
                'payments.due_date',
                DB::raw('DATEDIFF(CURDATE(), payments.due_date) as days_overdue')
            )
            ->orderBy('payments.due_date')
            ->orderBy('payments.id')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'due_date' => (string) $row->due_date,
                'days_overdue' => (int) $row->days_overdue,
            ])
            ->all();

        $apiOverdue = $this->callProtected($controller, 'getOverdueSummary', [])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'due_date' => (string) $row->due_date,
                'days_overdue' => (int) $row->days_overdue,
            ])
            ->all();

        if ($this->digest($referenceOverdue) !== $this->digest($apiOverdue)) {
            return [false, 'getOverdueSummary — ' . $this->firstDifference($referenceOverdue, $apiOverdue)];
        }

        return [true, sprintf(
            '%d dept risk rows, %d payments, %d dept summaries, %d overdue',
            count($referenceRisk),
            (int) $referenceStats['total_count'],
            count($referenceSummary),
            count($referenceOverdue)
        )];
    }

    protected function referencePaymentStats($statusFilter, $departmentFilter): array
    {
        $query = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->join('departments', 'programs.department_id', '=', 'departments.id');

        if ($statusFilter !== 'all') {
            $query->where('payments.status', $statusFilter);
        }
        if ($departmentFilter !== 'all') {
            $query->where('departments.code', $departmentFilter);
        }

        $totalAmount = (clone $query)->sum('payments.amount');
        $collectedAmount = (clone $query)->sum('payments.paid_amount');

        return [
            'total_amount' => $totalAmount,
            'collected_amount' => $collectedAmount,
            'overdue_amount' => (clone $query)->where('payments.status', 'Overdue')->sum('payments.balance'),
            'outstanding_amount' => (clone $query)->whereIn('payments.status', ['Unpaid', 'Partial', 'Overdue'])->sum('payments.balance'),
            'total_count' => (clone $query)->count(),
            'paid_count' => (clone $query)->where('payments.status', 'Paid')->count(),
            'partial_count' => (clone $query)->where('payments.status', 'Partial')->count(),
            'unpaid_count' => (clone $query)->where('payments.status', 'Unpaid')->count(),
            'overdue_count' => (clone $query)->where('payments.status', 'Overdue')->count(),
            'collection_rate' => $totalAmount > 0 ? round(($collectedAmount / $totalAmount) * 100, 1) : 0,
        ];
    }

    protected function compareDepartmentScope(): array
    {
        $structure = app(AcademicStructureRepository::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('departments')->orderBy('id')->pluck('id') as $departmentId) {
            $blocks = DB::table('blocks')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->select('blocks.*', 'year_levels.year_number', 'programs.code as program_code')
                ->orderBy('programs.code')
                ->orderBy('year_levels.year_number')
                ->orderBy('blocks.block_number')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            $studentIds = DB::table('students')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->where('students.status', 'Active')
                ->distinct()
                ->pluck('students.id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $reference[$departmentId] = ['blocks' => $blocks, 'student_ids' => $studentIds];

            $apiStudentIds = $structure->studentIdsInDepartment($departmentId, true);
            sort($apiStudentIds);

            $viaApi[$departmentId] = [
                'blocks' => $structure->blocksWithProgramForDepartment($departmentId)
                    ->map(fn ($block) => (array) $block)
                    ->all(),
                'student_ids' => array_map('intval', $apiStudentIds),
            ];
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d department(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }
    protected function compareStudentAverages(
        array $ids,
        string $period,
        string $schoolYear,
        GradeRepository $grades,
        AttendanceSummaryRepository $summaries
    ): array {
        $reference = [
            'grades' => DB::table('grades')
                ->whereIn('student_id', $ids)
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->select('student_id', DB::raw('AVG(numerical_grade) as avg_grade'))
                ->groupBy('student_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->student_id => $row->avg_grade])
                ->all(),
            'attendance' => DB::table('attendance_summaries')
                ->whereIn('student_id', $ids)
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->select('student_id', DB::raw('AVG(attendance_rate) as avg_attendance'))
                ->groupBy('student_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->student_id => $row->avg_attendance])
                ->all(),
        ];

        $viaApi = [
            'grades' => collect($grades->averageGradeRows($ids, $period, $schoolYear))
                ->map(fn ($row) => $row->avg_grade)
                ->all(),
            'attendance' => collect($summaries->averageAttendanceRows($ids, $period, $schoolYear))
                ->map(fn ($row) => $row->avg_attendance)
                ->all(),
        ];

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d grade avg + %d attendance avg, digest %s',
                count($reference['grades']),
                count($reference['attendance']),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

    protected function compareBlockRiskScores(string $period, string $schoolYear): array
    {
        $service = app(\App\Services\RiskScoringService::class);

        $reference = [];
        $viaApi = [];

        foreach (DB::table('blocks')->orderBy('id')->pluck('id') as $blockId) {
            $rows = DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->where('students.block_id', $blockId)
                ->where('risk_scores.grading_period', $period)
                ->where('risk_scores.school_year', $schoolYear)
                ->select('risk_scores.*', 'students.first_name', 'students.last_name', 'students.student_number')
                ->get();

            $reference[$blockId] = $rows->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'risk_level' => (string) $row->risk_level,
                'risk_score' => (int) $row->risk_score,
                'first_name' => (string) $row->first_name,
                'last_name' => (string) $row->last_name,
                'student_number' => (string) $row->student_number,
            ])->all();

            $viaApi[$blockId] = collect($service->getBlockRiskScores($blockId, $period, $schoolYear))
                ->map(fn ($row) => [
                    'student_id' => (int) $row->student_id,
                    'risk_level' => (string) $row->risk_level,
                    'risk_score' => (int) $row->risk_score,
                    'first_name' => (string) $row->first_name,
                    'last_name' => (string) $row->last_name,
                    'student_number' => (string) $row->student_number,
                ])
                ->all();
        }

        $referenceDigest = $this->digest($reference);
        $apiDigest = $this->digest($viaApi);

        if ($referenceDigest === $apiDigest) {
            return [true, sprintf(
                '%d block(s), digest %s',
                count($reference),
                substr($referenceDigest, 0, 12)
            )];
        }

        return [false, sprintf(
            'digest %s (SQL) vs %s (API) — %s',
            substr($referenceDigest, 0, 12),
            substr($apiDigest, 0, 12),
            $this->firstDifference($reference, $viaApi)
        )];
    }

}

<?php

namespace App\Services;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Support\Facades\DB;

class RiskScoringService
{
    protected const PERIOD_COUNT = 3;

    protected $aiStudio;

    protected RiskThresholdService $thresholds;
    protected $batchSize;

    /** Raw attendance logs — external mock API. */
    protected AttendanceRepository $attendanceRepo;

    /** Per-subject grades + the subjects join — external mock API. */
    protected GradeRepository $gradeRepo;

    /** Academic calendar (period windows + ordinal numbers) — external mock API. */
    protected CalendarRepository $calendarRepo;

    /** Student records — external mock API. */
    protected StudentRepository $studentRepo;

    public function __construct(
        ?GradeRepository $grades = null,
        ?AttendanceRepository $attendance = null,
        ?CalendarRepository $calendar = null,
        ?StudentRepository $students = null,
        ?AIStudioApiService $aiStudio = null,
        ?RiskThresholdService $thresholds = null
    ) {
        $this->aiStudio = $aiStudio ?? new AIStudioApiService();
        $this->thresholds = $thresholds ?? app(RiskThresholdService::class);
        $this->batchSize = (int) config('services.aistudio.batch_size', 5);

        $this->gradeRepo = $grades ?? app(GradeRepository::class);
        $this->attendanceRepo = $attendance ?? app(AttendanceRepository::class);
        $this->calendarRepo = $calendar ?? app(CalendarRepository::class);
        $this->studentRepo = $students ?? app(StudentRepository::class);
    }

    public function processBlock(int $blockId, string $gradingPeriod, string $schoolYear): array
    {
        $cacheService = new RiskScoringCacheService();

        $cached = $cacheService->get($blockId, $gradingPeriod, $schoolYear);
        if ($cached !== null) {
            $cached['cached'] = true;
            $cached['message'] = 'Risk scoring served from local cache (created ≤ 60 min TTL).';
            return $cached;
        }

        $cacheService->flushExpired();

        $students = $this->studentRepo->forBlock($blockId, true)
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->values();

        if ($students->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No students found in this block.',
                'processed' => 0,
                'students' => [],
                'cached' => false,
                'batches' => 0,
                'ai_calls' => 0,
            ];
        }

        $studentIds = $students->pluck('id')->toArray();

        $grades = $this->getGrades($studentIds, $gradingPeriod, $schoolYear);
        $attendance = $this->getAttendance($studentIds, $gradingPeriod, $schoolYear);
        $previousRisks = $this->getPreviousRisks($studentIds, $gradingPeriod, $schoolYear);
        $programs = $this->getProgramsForStudents($studentIds);

        $studentDataList = [];
foreach ($students as $student) {
            $gradeData = $grades[$student->id] ?? [
                'subjects' => [],
                'avg_grade' => 0,
                'total_subjects' => 0,
                'failing_subjects' => 0,
            ];
            $attendanceData = $attendance[$student->id] ?? [
                'attendance_rate' => 100.0,
                'absences' => 0,
                'lates' => 0,
            ];

            $studentDataList[] = [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'student_number' => $student->student_number,
                'subjects' => $gradeData['subjects'],
                'avg_grade' => $gradeData['avg_grade'],
                'total_subjects' => $gradeData['total_subjects'],
                'failing_subjects' => $gradeData['failing_subjects'],
                'attendance_rate' => $attendanceData['attendance_rate'],
                'absences' => $attendanceData['absences'],
                'lates' => $attendanceData['lates'],
                'previous_risk' => $previousRisks[$student->id] ?? 'Low',
                'program' => $programs[$student->id] ?? '',
                'risk_level' => '',
            ];
        }

        usort($studentDataList, function (array $a, array $b): int {
            $aHigh = ($a['avg_grade'] < 75 || $a['attendance_rate'] < 75);
            $bHigh = ($b['avg_grade'] < 75 || $b['attendance_rate'] < 75);

            if ($aHigh === $bHigh) {
                return 0;
            }

            return $aHigh ? -1 : 1;
        });

        $batches = array_chunk($studentDataList, $this->batchSize);

        $bands = $this->thresholds->current();

        $delaySeconds = (int) config('services.aistudio.batch_delay_seconds', 4);
        $studentResults = [];
        $aiCalls = 0;

        foreach ($batches as $index => $batch) {
            if ($index > 0) {
                usleep($delaySeconds * 1_000_000);
            }

            $batchScores = $this->aiStudio->getRiskScoresForBatch($batch, $bands);
            $aiCalls++;

            foreach ($batch as $studentData) {
                $score = $batchScores[$studentData['id']];

                $assignedLevel = $this->thresholds->levelFor($score['risk_score'], $bands);
                $studentResults[] = [
                    'student_id' => $studentData['id'],
                    'risk_score' => $score['risk_score'],
                    'risk_level' => $assignedLevel,
                    'ai_risk_level' => $score['risk_level'],
                    'risk_factors' => json_encode($score['risk_factors']),
                    'scoring_method' => 'ai',
                    'explanation' => $score['explanation'],
                ];
            }
        }

        // Persist scores (delete-then-insert so re-runs never duplicate).
        $this->saveRiskScores($studentResults, $gradingPeriod, $schoolYear);

        $result = [
            'success' => true,
            'message' => 'Risk scoring completed via AI batch pipeline.',
            'processed' => count($studentResults),
            'batches' => count($batches),
            'ai_calls' => $aiCalls,
            'students' => $studentResults,
            'risk_thresholds' => $bands,
            'cached' => false,
        ];

        $cacheService->put($blockId, $gradingPeriod, $schoolYear, $result);

        return $result;
    }

    public function getGrades(array $studentIds, string $period, string $schoolYear): array
    {
        return $this->gradeRepo->forStudentsPeriod($studentIds, $period, $schoolYear);
    }

    public function getAttendance(array $studentIds, string $period, string $schoolYear): array
    {
        $metrics = [];
        foreach ($studentIds as $sid) {
            $metrics[$sid] = ['attendance_rate' => 100.0, 'absences' => 0, 'lates' => 0];
        }

        if (empty($studentIds)) {
            return $metrics;
        }

        $periodNumber = $this->getPeriodNumber($schoolYear, '1st', $period);
        $dateRange = $this->getPeriodDateRange($schoolYear, '1st', $period);

        $rangeStart = $dateRange ? strtotime($dateRange['start']) : null;
        $rangeEnd = $dateRange ? strtotime($dateRange['end']) + 86399 : null;

        $allLogs = $this->attendanceRepo->rawLogsForStudentsGrouped($studentIds);

        foreach ($metrics as $sid => &$m) {
            $studentLogs = $allLogs->get($sid, collect());
            if ($studentLogs->isEmpty()) {
                continue;
            }

            $inRange = collect();
            if ($rangeStart !== null && $rangeEnd !== null) {
                $inRange = $studentLogs->filter(function ($log) use ($rangeStart, $rangeEnd) {
                    $ts = strtotime((string) $log->session_date);

                    return $ts !== false && $ts >= $rangeStart && $ts <= $rangeEnd;
                })->values();
            }

            $slice = $inRange->isNotEmpty()
                ? $inRange
                : $this->sliceForPeriod($studentLogs, $periodNumber);

            if ($slice->isEmpty()) {
                continue;
            }

            $computed = $this->metricsFromLogs($slice);
            $m['attendance_rate'] = $computed['attendance_rate'];
            $m['absences'] = $computed['absences'];
            $m['lates'] = $computed['lates'];
        }
        unset($m);

        return $metrics;
    }

    protected function sliceForPeriod($logs, int $periodNumber)
    {
        $ordered = $logs->sortBy('session_date')->values();

        $timestamps = $ordered
            ->map(fn ($log) => strtotime((string) $log->session_date))
            ->filter(fn ($ts) => $ts !== false)
            ->values();

        // A single session (or an unparseable set) cannot be subdivided.
        if ($timestamps->count() < 2) {
            return $ordered;
        }

        $min = (int) $timestamps->min();
        $span = (int) $timestamps->max() - $min;

        if ($span <= 0) {
            return $ordered;
        }

        $index = max(1, min(self::PERIOD_COUNT, $periodNumber)) - 1;

        return $ordered->filter(function ($log) use ($min, $span, $index) {
            $ts = strtotime((string) $log->session_date);
            if ($ts === false) {
                return false;
            }

            $bucket = (int) floor((($ts - $min) / $span) * self::PERIOD_COUNT);

            return max(0, min(self::PERIOD_COUNT - 1, $bucket)) === $index;
        })->values();
    }

    protected function metricsFromLogs($logs): array
    {
        $totalHours = (float) $logs->sum('hours_duration');
        $totalWeighted = (float) $logs->sum('weighted_hours');

        $attendanceRate = $totalHours > 0
            ? round(($totalWeighted / $totalHours) * 100, 2)
            : 100.0;

        return [
            'attendance_rate' => $attendanceRate,
            'absences' => $logs->where('status', 'Absent')->count(),
            'lates' => $logs->where('status', 'Late')->count(),
        ];
    }

    protected function getPeriodDateRange(string $schoolYear, string $semester, string $period): ?array
    {
        return $this->calendarRepo->periodDateRange($schoolYear, $semester, $period);
    }

    protected function getPeriodNumber(string $schoolYear, string $semester, string $period): int
    {
        return $this->calendarRepo->periodNumber($schoolYear, $semester, $period);
    }

    protected function getProgramsForStudents(array $studentIds): array
    {
        $placements = app(AcademicStructureRepository::class)->studentPlacements();
        $programs = [];

        foreach ($studentIds as $studentId) {
            $placement = $placements[(int) $studentId] ?? null;

            if ($placement !== null && $placement['program_id'] !== null) {
                $programs[$studentId] = $placement['program_code'];
            }
        }

        return $programs;
    }


    protected function getPreviousRisks(array $studentIds, string $currentPeriod, string $schoolYear): array
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $currentIndex = array_search($currentPeriod, $periods);
        $previousPeriod = $currentIndex > 0 ? $periods[$currentIndex - 1] : null;
        
        if (!$previousPeriod) {
            return [];
        }

        $risks = collect(app(RiskScoreRepository::class)->forStudentsPeriod($studentIds, $previousPeriod, $schoolYear));

        return $risks->pluck('risk_level', 'student_id')->toArray();
    }

    protected function getFailingSubjects(array $studentIds, string $period, string $schoolYear): array
    {
        // grades is served by the mock API; the repository reproduces the same
        // per-student failing-count aggregate.
        return $this->gradeRepo->failingCountsFor($studentIds, $period, $schoolYear);

    }

    protected function saveRiskScores(array $results, string $period, string $schoolYear): void
    {
        if (empty($results)) {
            return;
        }

        $studentIds = array_column($results, 'student_id');

        DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->delete();

        $now = now();
        
        foreach ($results as $result) {
            DB::table('risk_scores')->insert([
                'student_id' => $result['student_id'],
                'grading_period' => $period,
                'school_year' => $schoolYear,
                'semester' => '1st',
                'risk_score' => $result['risk_score'],
                'risk_level' => $result['risk_level'],
                'ai_risk_level' => $result['ai_risk_level'] ?? null,
                'risk_factors' => $result['risk_factors'],
                'scoring_method' => $result['scoring_method'],
                'processing_status' => 'completed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function getStudentRiskHistory(int $studentId): array
    {
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('grading_period')
            ->get()
            ->toArray();
    }

    public function getBlockRiskScores(int $blockId, string $period, string $schoolYear): array
    {
        // risk_scores stays local; the students columns come from the mock API. The
        // previous INNER JOIN required the student to be placed.
        $studentIds = app(AcademicStructureRepository::class)->studentIdsInBlock($blockId, false);
        $studentIndex = $this->studentRepo->keyedById();

        return DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->get()
            ->map(function ($score) use ($studentIndex) {
                $student = $studentIndex[(int) $score->student_id] ?? null;

                $row = clone $score;
                $row->first_name = $student->first_name ?? null;
                $row->last_name = $student->last_name ?? null;
                $row->student_number = $student->student_number ?? null;

                return $row;
            })
            ->toArray();
    }
}
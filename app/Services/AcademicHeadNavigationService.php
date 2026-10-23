<?php

namespace App\Services;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Support\Facades\DB;

class AcademicHeadNavigationService
{
    public const OPEN_CASE_STATUSES = ['New', 'In Progress'];

    public const DEFAULT_PERIOD = 'Midterm';

    public const DEFAULT_SCHOOL_YEAR = '2024-2025';

    protected array $cache = [];

    public function __construct(
        protected AcademicStructureRepository $structure,
        protected RiskScoreRepository $riskScores
    ) {
    }

    public function counters(
        ?int $departmentId,
        string $period = self::DEFAULT_PERIOD,
        string $schoolYear = self::DEFAULT_SCHOOL_YEAR,
        ?int $currentBlockId = null
    ): array {
        $period = $period !== '' ? $period : self::DEFAULT_PERIOD;
        $schoolYear = $schoolYear !== '' ? $schoolYear : self::DEFAULT_SCHOOL_YEAR;

        $key = implode(':', [
            (int) $departmentId,
            $period,
            $schoolYear,
            (int) $currentBlockId,
        ]);

        return $this->cache[$key] ??= $this->compute($departmentId, $period, $schoolYear, $currentBlockId);
    }

    public function riskCounters(
        ?int $departmentId,
        string $period = self::DEFAULT_PERIOD,
        string $schoolYear = self::DEFAULT_SCHOOL_YEAR
    ): array {
        $counters = $this->counters($departmentId, $period, $schoolYear);

        return [
            'total_students' => (int) $counters['total_students'],
            'high_risk' => (int) $counters['high_risk'],
            'moderate_risk' => (int) $counters['moderate_risk'],
            'low_risk' => (int) $counters['low_risk'],
            'unmonitored' => (int) $counters['unmonitored'],
        ];
    }

    public function resolveCurrentBlockId(?int $departmentId, ?int $explicitBlockId = null): ?int
    {
        if ($explicitBlockId !== null && $explicitBlockId > 0) {
            return $explicitBlockId;
        }

        if (!$departmentId) {
            return null;
        }

        $blocks = $this->structure->blocksWithProgramForDepartment($departmentId);

        if ($blocks->isEmpty()) {
            return null;
        }

        $blockIds = array_map('intval', $blocks->pluck('id')->all());

        // risk_score_cache is keyed by block, so it names the most recently scored
        // block directly (risk_scores itself has no block_id column).
        $lastScoredBlockId = DB::table('risk_score_cache')
            ->whereIn('block_id', $blockIds)
            ->orderByDesc('updated_at')
            ->value('block_id');

        if ($lastScoredBlockId === null) {
            $placements = $this->structure->studentPlacements();
            $lastStudentId = DB::table('risk_scores')
                ->whereIn('student_id', array_keys($placements))
                ->orderByDesc('updated_at')
                ->value('student_id');

            $lastScoredBlockId = $lastStudentId !== null
                ? ($placements[(int) $lastStudentId]['block_id'] ?? null)
                : null;
        }

        if ($lastScoredBlockId !== null && in_array((int) $lastScoredBlockId, $blockIds, true)) {
            return (int) $lastScoredBlockId;
        }

        return $blockIds[0];
    }

    public function blockLabel(?int $blockId): ?string
    {
        if (!$blockId) {
            return null;
        }

        $block = $this->structure->blockWithProgram($blockId);

        if (!$block) {
            return null;
        }

        $parts = array_filter([
            $block->program_code ?? null,
            isset($block->year_number) && $block->year_number !== null
                ? 'Year ' . $block->year_number
                : null,
            $block->name ?? null,
        ]);

        return $parts === [] ? null : implode(' - ', $parts);
    }

    protected function compute(?int $departmentId, string $period, string $schoolYear, ?int $currentBlockId): array
    {
        $activeIds = $departmentId
            ? $this->structure->studentIdsInDepartment($departmentId, true)
            : [];

        $levels = $activeIds === []
            ? ['Low' => 0, 'Moderate' => 0, 'High' => 0]
            : $this->riskScores->levelCountsFor($activeIds, $period, $schoolYear);

        $scored = $levels['Low'] + $levels['Moderate'] + $levels['High'];

        return [
            'total_students' => count($activeIds),
            'high_risk' => $levels['High'],
            'moderate_risk' => $levels['Moderate'],
            'low_risk' => $levels['Low'],
            'unmonitored' => max(0, count($activeIds) - $scored),
            'pending_escalations' => $this->countOpenCases($activeIds),
            'unacknowledged_flags' => $this->countUnacknowledgedFlags($activeIds, $period, $schoolYear),
            'pending_recommendations' => $this->countWithoutRecommendation($activeIds, $period, $schoolYear),
            'stored_reports' => $this->countStoredReports($departmentId),
            'current_block_id' => $this->resolveCurrentBlockId($departmentId, $currentBlockId),
        ];
    }

    protected function countOpenCases(array $studentIds): int
    {
        if ($studentIds === []) {
            return 0;
        }

        return (int) DB::table('cases')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', self::OPEN_CASE_STATUSES)
            ->count();
    }

    protected function countUnacknowledgedFlags(array $studentIds, string $period, string $schoolYear): int
    {
        if ($studentIds === []) {
            return 0;
        }

        return (int) DB::table('flags')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->where('is_acknowledged', false)
            ->count();
    }

    protected function countWithoutRecommendation(array $studentIds, string $period, string $schoolYear): int
    {
        if ($studentIds === []) {
            return 0;
        }

        $withRecommendation = DB::table('intervention_recommendations')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return max(0, count($studentIds) - count($withRecommendation));
    }

    protected function countStoredReports(?int $departmentId): int
    {
        if (!$departmentId) {
            return 0;
        }

        return (int) DB::table('end_of_term_reports')
            ->where('department_id', $departmentId)
            ->count();
    }

    public function latestReportId(?int $departmentId): ?int
    {
        if (!$departmentId) {
            return null;
        }

        $id = DB::table('end_of_term_reports')
            ->where('department_id', $departmentId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}

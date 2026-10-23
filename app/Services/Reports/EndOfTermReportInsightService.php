<?php

namespace App\Services\Reports;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Services\RiskThresholdService;
use Illuminate\Support\Facades\DB;

class EndOfTermReportInsightService
{
    public const HIGH_RATE_WARNING_PERCENT = 50.0;

    protected array $cache = [];

    public function __construct(
        protected AcademicStructureRepository $structure,
        protected RiskScoreRepository $riskScores,
        protected RiskThresholdService $thresholds
    ) {
    }

    public function forReport(array $snapshot): array
    {
        $meta = $snapshot['meta'] ?? [];
        $departmentId = (int) ($meta['department']['id'] ?? 0);
        $schoolYear = (string) ($meta['school_year'] ?? '');
        $semester = (string) ($meta['semester'] ?? '1st');
        $gradingPeriod = (string) ($meta['grading_period'] ?? '');

        if ($departmentId <= 0 || $schoolYear === '' || $gradingPeriod === '') {
            return $this->emptyPayload();
        }

        $key = implode(':', [$departmentId, $schoolYear, $semester, $gradingPeriod]);

        return $this->cache[$key] ??= $this->build(
            $departmentId,
            $schoolYear,
            $semester,
            $gradingPeriod
        );
    }

    protected function emptyPayload(): array
    {
        return [
            'bands' => [],
            'bands_effective' => [],
            'thresholds' => [],
            'warning' => null,
            'year_levels' => [],
            'intervention' => [],
            'period_series' => [],
        ];
    }

    protected function build(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod): array
    {
        $activeIds = $this->structure->studentIdsInDepartment($departmentId, true);
        $levels = $this->riskScores->levelByStudent($gradingPeriod, $schoolYear);
        $rows = $this->riskScores->rowByStudent($gradingPeriod, $schoolYear);
        $placements = $this->structure->studentPlacements();

        // Only students that are BOTH placed in this department AND scored in this
        // period count as "monitored" — the same intersection the report body uses.
        $monitoredIds = array_values(array_filter(
            $activeIds,
            fn ($id) => isset($levels[(int) $id])
        ));

        $counts = ['Low' => 0, 'Moderate' => 0, 'High' => 0];
        foreach ($monitoredIds as $id) {
            $level = (string) $levels[(int) $id];
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }
        $monitored = count($monitoredIds);
        $thresholds = $this->thresholds->current();

        $intervention = $this->interventionStatus(
            $schoolYear,
            $semester,
            $gradingPeriod,
            $monitoredIds,
            $levels,
            $counts['High']
        );

        return [
            'bands' => RiskThresholdService::bandLabels($thresholds),
            'bands_effective' => RiskThresholdService::effectiveBandLabels($thresholds),
            'thresholds' => $thresholds,
            'warning' => $this->warning($monitored, $counts['High'], $thresholds),
            'year_levels' => $this->yearLevels($monitoredIds, $levels, $placements),
            'intervention' => $intervention,
            'period_series' => $this->periodSeries($activeIds, $schoolYear, $semester),
            'monitored' => $monitored,
        ];
    }

    protected function warning(int $monitored, int $high, array $thresholds): ?array
    {
        if ($monitored <= 0) {
            return null;
        }

        $percentage = round($high / $monitored * 100, 2);

        if ($percentage <= self::HIGH_RATE_WARNING_PERCENT) {
            return null;
        }

        return [
            'percentage' => number_format($percentage, 2) . '%',
            'high' => number_format($high),
            'monitored' => number_format($monitored),
            'bands' => sprintf(
                'Low 0-%d, Moderate %d-%d, High %d-100',
                (int) $thresholds['low_threshold'],
                (int) $thresholds['low_threshold'] + 1,
                (int) $thresholds['moderate_threshold'],
                (int) $thresholds['high_threshold']
            ),
            'message' => sprintf(
                'High risk accounts for %s%% of monitored students (%s of %s). The figures below are '
                . 'correct, but a rate this high usually means the configured risk bands are not '
                . 'discriminating: the current configuration is %s, so the High band begins at score %d. '
                . 'Review the thresholds under Admin &rarr; Risk Settings before acting on the absolute '
                . 'counts alone.',
                number_format($percentage, 2),
                number_format($high),
                number_format($monitored),
                sprintf(
                    'Low 0-%d, Moderate %d-%d, High %d-100',
                    (int) $thresholds['low_threshold'],
                    (int) $thresholds['low_threshold'] + 1,
                    (int) $thresholds['moderate_threshold'],
                    (int) $thresholds['high_threshold']
                ),
                (int) $thresholds['high_threshold']
            ),
        ];
    }

    protected function yearLevels(array $monitoredIds, array $levels, array $placements): array
    {
        $buckets = [];

        foreach ($monitoredIds as $id) {
            $placement = $placements[(int) $id] ?? [];
            $yearNumber = $placement['year_number'] ?? null;
            $bucketKey = $yearNumber === null ? 'Unplaced' : (int) $yearNumber;

            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'year_number' => $yearNumber === null ? null : (int) $yearNumber,
                    'label' => $yearNumber === null
                        ? 'Year level unknown'
                        : $this->ordinal((int) $yearNumber) . ' Year',
                    'monitored' => 0,
                    'counts' => ['High' => 0, 'Moderate' => 0, 'Low' => 0],
                    'percentages' => ['High' => 0.0, 'Moderate' => 0.0, 'Low' => 0.0],
                ];
            }

            $level = (string) $levels[(int) $id];
            $buckets[$bucketKey]['monitored']++;
            $buckets[$bucketKey]['counts'][$level] = ($buckets[$bucketKey]['counts'][$level] ?? 0) + 1;
        }

        foreach ($buckets as &$bucket) {
            foreach (['High', 'Moderate', 'Low'] as $level) {
                $bucket['percentages'][$level] = $bucket['monitored'] > 0
                    ? round($bucket['counts'][$level] / $bucket['monitored'] * 100, 2)
                    : 0.0;
            }
        }
        unset($bucket);

        ksort($buckets);

        return array_values($buckets);
    }

    protected function interventionStatus(
        string $schoolYear,
        string $semester,
        string $gradingPeriod,
        array $monitoredIds,
        array $levels,
        int $highCount
    ): array {
        if ($monitoredIds === []) {
            return [
                'high_risk' => 0,
                'high_escalated' => 0,
                'high_pending' => 0,
                'coverage_percentage' => 0.0,
                'total_escalations' => 0,
                'escalated_students' => 0,
                'open_cases' => 0,
                'resolved_cases' => 0,
                'note' => 'No monitored students to escalate in this period.',
            ];
        }

        // Scoped exactly like the report body (school year + semester + period), so
        // the escalated count can never exceed the population the report describes.
        $escalatedStudentIds = DB::table('escalations')
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->where('grading_period', $gradingPeriod)
            ->whereIn('student_id', $monitoredIds)
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $highEscalated = 0;
        foreach ($escalatedStudentIds as $studentId) {
            if ((string) ($levels[$studentId] ?? '') === 'High') {
                $highEscalated++;
            }
        }

        $caseStatuses = DB::table('cases')
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereIn('student_id', $monitoredIds)
            ->pluck('status')
            ->countBy();

        $openCases = (int) ($caseStatuses['New'] ?? 0)
            + (int) ($caseStatuses['In Progress'] ?? 0)
            + (int) ($caseStatuses['Awaiting Parent'] ?? 0)
            + (int) ($caseStatuses['Awaiting Student'] ?? 0)
            + (int) ($caseStatuses['Referred'] ?? 0)
            + (int) ($caseStatuses['Reopened'] ?? 0);

        $note = $escalatedStudentIds === []
            ? sprintf(
                'No escalation records exist for %s semester, %s. Either the period was never '
                . 'escalated or the hand-off has not started yet.',
                $semester === '2nd' ? 'the second' : 'the first',
                $gradingPeriod
            )
            : null;

        return [
            'high_risk' => $highCount,
            'high_escalated' => $highEscalated,
            'high_pending' => max(0, $highCount - $highEscalated),
            'coverage_percentage' => $highCount > 0
                ? round($highEscalated / $highCount * 100, 2)
                : 0.0,
            'total_escalations' => $escalatedStudentIds === [] ? 0 : (int) DB::table('escalations')
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->where('grading_period', $gradingPeriod)
                ->whereIn('student_id', $monitoredIds)
                ->count(),
            'escalated_students' => count($escalatedStudentIds),
            'open_cases' => $openCases,
            'resolved_cases' => (int) ($caseStatuses['Resolved'] ?? 0) + (int) ($caseStatuses['Closed'] ?? 0),
            'note' => $note,
        ];
    }

    protected function periodSeries(
        array $activeIds,
        string $schoolYear,
        string $semester
    ): array {
        $series = [];

        foreach (EndOfTermReportService::PERIODS as $period) {
            $counts = $activeIds === []
                ? ['Low' => 0, 'Moderate' => 0, 'High' => 0]
                : $this->riskScores->levelCountsFor($activeIds, $period, $schoolYear);

            $monitored = array_sum($counts);

            $escalations = $activeIds === []
                ? 0
                : (int) DB::table('escalations')
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->where('grading_period', $period)
                    ->whereIn('student_id', $activeIds)
                    ->count();

            $series[] = [
                'period' => $period,
                'monitored' => $monitored,
                'high' => $counts['High'],
                'moderate' => $counts['Moderate'],
                'low' => $counts['Low'],
                'high_percentage' => $monitored > 0
                    ? round($counts['High'] / $monitored * 100, 2)
                    : 0.0,
                'escalations' => $escalations,
            ];
        }

        return $series;
    }

    protected function ordinal(int $number): string
    {
        if ($number % 100 >= 11 && $number % 100 <= 13) {
            return $number . 'th';
        }

        return match ($number % 10) {
            1 => $number . 'st',
            2 => $number . 'nd',
            3 => $number . 'rd',
            default => $number . 'th',
        };
    }
}


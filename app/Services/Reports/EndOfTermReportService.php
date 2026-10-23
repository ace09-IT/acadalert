<?php

namespace App\Services\Reports;

use App\Repositories\Api\AcademicStructureRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EndOfTermReportService
{
    public const PERIODS = ['Prelim', 'Midterm', 'Finals'];

    /** Risk categories ordered ASCENDING by severity, which is also the tie-break order. */
    public const RISK_LEVELS = ['Low', 'Moderate', 'High'];

    public const FORMULA_VERSION = '1.0.0';

    /** Only fully processed scores are reportable. */
    protected const SCORE_STATUS = 'completed';


    /** 1-based position of a period, or 0 when the period is not canonical. */
    public function periodNumber(string $period): int
    {
        $index = array_search($period, self::PERIODS, true);

        return $index === false ? 0 : $index + 1;
    }

    public function previousPeriod(?string $period): ?string
    {
        $number = $period === null ? 0 : $this->periodNumber($period);

        return $number > 1 ? self::PERIODS[$number - 2] : null;
    }

    public function riskRank(string $level): int
    {
        $index = array_search($level, self::RISK_LEVELS, true);

        return $index === false ? 0 : $index + 1;
    }

    public function percentageDistribution(array $counts, int $total): array
    {
        $ordered = [];
        foreach (self::RISK_LEVELS as $level) {
            $ordered[$level] = (int) ($counts[$level] ?? 0);
        }

        if ($total <= 0) {
            return array_map(static fn () => 0.0, $ordered);
        }

        $units = 10000;
        $floors = [];
        $remainders = [];
        $allocated = 0;

        foreach ($ordered as $level => $count) {
            $numerator = $count * $units;
            $floors[$level] = intdiv($numerator, $total);
            $remainders[$level] = $numerator % $total;
            $allocated += $floors[$level];
        }

        $leftover = $units - $allocated;

        if ($leftover > 0) {
            $ranking = array_keys($ordered);
            usort($ranking, function ($a, $b) use ($remainders, $ordered) {
                if ($remainders[$a] === $remainders[$b]) {
                    return $ordered[$b] <=> $ordered[$a];
                }

                return $remainders[$b] <=> $remainders[$a];
            });

            for ($i = 0; $i < $leftover; $i++) {
                $floors[$ranking[$i % count($ranking)]]++;
            }
        }

        $percentages = [];
        foreach ($floors as $level => $hundredths) {
            $percentages[$level] = round($hundredths / 100, 2);
        }

        return $percentages;
    }

    protected function round(float $value, int $precision = 2): float
    {
        $factor = 10 ** $precision;

        return ($value < 0 ? -1 : 1) * floor(abs($value) * $factor + 0.5) / $factor;
    }


    protected function scopedScores(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod)
    {
        return $this->scopedScoreRows($departmentId, $schoolYear, $semester)
            ->filter(fn ($row) => $row->grading_period === $gradingPeriod)
            ->values();
    }

    protected function scopedScoreRows(int $departmentId, string $schoolYear, string $semester): Collection
    {
        $scores = DB::table('risk_scores')
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->where('processing_status', self::SCORE_STATUS)
            ->get(['student_id', 'risk_level', 'risk_score', 'grading_period']);

        if ($scores->isEmpty()) {
            return new Collection();
        }

        $structure = app(AcademicStructureRepository::class);

        $inScope = array_flip($structure->studentIdsInDepartment($departmentId, true));
        $placements = $structure->studentPlacements();
        $programs = $structure->programs()->keyBy(fn ($program) => (int) $program->id);

        $rows = [];

        foreach ($scores as $score) {
            $studentId = (int) $score->student_id;

            if (!isset($inScope[$studentId])) {
                continue;
            }

            $placement = $placements[$studentId];
            $program = $programs[$placement['program_id']] ?? null;

            $rows[] = (object) [
                'student_id' => $studentId,
                'risk_level' => (string) $score->risk_level,
                'risk_score' => (int) $score->risk_score,
                'grading_period' => (string) $score->grading_period,
                'program_id' => $placement['program_id'],
                'program_code' => $program->code ?? null,
                'program_name' => $program->name ?? null,
            ];
        }

        return new Collection($rows);
    }

    protected function monitoredCount(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod): int
    {
        return $this->scopedScores($departmentId, $schoolYear, $semester, $gradingPeriod)
            ->pluck('student_id')
            ->unique()
            ->count();
    }

    protected function distribution(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod): array
    {
        $rows = $this->scopedScores($departmentId, $schoolYear, $semester, $gradingPeriod)
            ->groupBy('risk_level')
            ->map(fn ($group) => $group->pluck('student_id')->unique()->count());

        $counts = [];
        foreach (self::RISK_LEVELS as $level) {
            $counts[$level] = (int) ($rows[$level] ?? 0);
        }

        return $counts;
    }

    protected function activeStudentCount(int $departmentId): int
    {
        return count(
            app(AcademicStructureRepository::class)->studentIdsInDepartment($departmentId, true)
        );
    }

    protected function byProgram(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod): array
    {
        $grouped = [];

        foreach ($this->scopedScores($departmentId, $schoolYear, $semester, $gradingPeriod) as $row) {
            if ($row->program_id === null) {
                continue;
            }

            $key = $row->program_id . '|' . $row->risk_level;

            if (!isset($grouped[$key])) {
                $grouped[$key] = (object) [
                    'program_id' => $row->program_id,
                    'program_code' => $row->program_code,
                    'program_name' => $row->program_name,
                    'level' => $row->risk_level,
                    'student_ids' => [],
                ];
            }

            $grouped[$key]->student_ids[$row->student_id] = true;
        }

        $rows = collect($grouped)->map(function ($row) {
            $row->student_count = count($row->student_ids);
            unset($row->student_ids);

            return $row;
        })->values();

        $programs = [];

        foreach ($rows as $row) {
            $id = (int) $row->program_id;

            if (!isset($programs[$id])) {
                $programs[$id] = [
                    'program_id' => $id,
                    'program_code' => (string) $row->program_code,
                    'program_name' => (string) $row->program_name,
                    'monitored' => 0,
                    'counts' => array_fill_keys(self::RISK_LEVELS, 0),
                ];
            }

            if (in_array($row->level, self::RISK_LEVELS, true)) {
                $programs[$id]['counts'][$row->level] = (int) $row->student_count;
                $programs[$id]['monitored'] += (int) $row->student_count;
            }
        }

        $programs = array_values($programs);
        usort($programs, static fn ($a, $b) => strcmp($a['program_code'], $b['program_code']));

        foreach ($programs as &$program) {
            $program['percentages'] = $this->percentageDistribution($program['counts'], (int) $program['monitored']);
        }
        unset($program);

        return $programs;
    }

    protected function scoreStatistics(int $departmentId, string $schoolYear, string $semester, string $gradingPeriod): array
    {
        $rows = $this->scopedScores($departmentId, $schoolYear, $semester, $gradingPeriod);

        $byStudent = [];
        foreach ($rows as $row) {
            $id = (int) $row->student_id;
            $score = (int) $row->risk_score;
            $byStudent[$id] = isset($byStudent[$id]) ? max($byStudent[$id], $score) : $score;
        }

        if ($byStudent === []) {
            return ['count' => 0, 'average' => null, 'minimum' => null, 'maximum' => null, 'median' => null];
        }

        ksort($byStudent);
        $scores = array_values($byStudent);
        sort($scores);

        $count = count($scores);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($scores[$middle - 1] + $scores[$middle]) / 2
            : $scores[$middle];

        return [
            'count' => $count,
            'average' => $this->round(array_sum($scores) / $count, 1),
            'minimum' => $scores[0],
            'maximum' => $scores[$count - 1],
            'median' => $this->round((float) $median, 1),
        ];
    }

    protected function transitions(int $departmentId, string $schoolYear, string $semester, string $currentPeriod, string $previousPeriod): array
    {
        // The previous select aliases are reproduced so the movement loop below is
        // unchanged.
        $toLevelRows = fn ($rows) => $rows
            ->map(fn ($row) => (object) [
                'student_id' => $row->student_id,
                'level' => $row->risk_level,
            ])
            ->keyBy('student_id');

        $current = $toLevelRows($this->scopedScores($departmentId, $schoolYear, $semester, $currentPeriod));

        $previous = $toLevelRows($this->scopedScores($departmentId, $schoolYear, $semester, $previousPeriod));

        $movement = [
            'improved' => 0,
            'worsened' => 0,
            'stable' => 0,
            'newly_monitored' => 0,
            'no_longer_monitored' => 0,
        ];

        foreach ($current as $studentId => $row) {
            if (!$previous->has($studentId)) {
                $movement['newly_monitored']++;
                continue;
            }

            $delta = $this->riskRank((string) $row->level) - $this->riskRank((string) $previous->get($studentId)->level);

            if ($delta < 0) {
                $movement['improved']++;
            } elseif ($delta > 0) {
                $movement['worsened']++;
            } else {
                $movement['stable']++;
            }
        }

        foreach ($previous as $studentId => $row) {
            if (!$current->has($studentId)) {
                $movement['no_longer_monitored']++;
            }
        }

        return $movement;
    }

    protected function trendWord(int $delta): string
    {
        return $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'unchanged');
    }

    protected function buildComparison(
        int $departmentId,
        string $schoolYear,
        string $semester,
        string $currentPeriod,
        array $currentCounts,
        int $currentTotal,
        array $currentPercentages
    ): array {
        $emptyMovement = [
            'improved' => 0,
            'worsened' => 0,
            'stable' => 0,
            'newly_monitored' => 0,
            'no_longer_monitored' => 0,
        ];

        $previousPeriod = $this->previousPeriod($currentPeriod);

        if ($previousPeriod === null) {
            return [
                'previous_period' => null,
                'available' => false,
                'previous_total' => 0,
                'previous_counts' => array_fill_keys(self::RISK_LEVELS, 0),
                'previous_percentages' => array_fill_keys(self::RISK_LEVELS, 0.0),
                'count_delta' => array_fill_keys(self::RISK_LEVELS, 0),
                'percentage_point_delta' => array_fill_keys(self::RISK_LEVELS, 0.0),
                'total_delta' => 0,
                'transitions' => $emptyMovement,
                'headline' => $currentPeriod . ' is the baseline grading period of the academic year, so no earlier period exists to compare against.',
                'lines' => [
                    'This report establishes the reference point that subsequent end-of-term reports will be measured against.',
                ],
            ];
        }

        $previousCounts = $this->distribution($departmentId, $schoolYear, $semester, $previousPeriod);
        $previousTotal = $this->monitoredCount($departmentId, $schoolYear, $semester, $previousPeriod);
        $previousPercentages = $this->percentageDistribution($previousCounts, $previousTotal);

        $countDelta = [];
        $pointDelta = [];

        foreach (self::RISK_LEVELS as $level) {
            $countDelta[$level] = $currentCounts[$level] - $previousCounts[$level];
            $pointDelta[$level] = $this->round($currentPercentages[$level] - $previousPercentages[$level], 2);
        }

        $movement = $this->transitions($departmentId, $schoolYear, $semester, $currentPeriod, $previousPeriod);
        $totalDelta = $currentTotal - $previousTotal;

        $lines = [];

        foreach (array_reverse(self::RISK_LEVELS) as $level) {
            $lines[] = sprintf(
                '%s Risk: %d students (%.2f%%), %s from %d (%.2f%%) in %s (%s%d students, %s%.2f percentage points).',
                $level,
                $currentCounts[$level],
                $currentPercentages[$level],
                $this->trendWord($countDelta[$level]),
                $previousCounts[$level],
                $previousPercentages[$level],
                $previousPeriod,
                $countDelta[$level] >= 0 ? '+' : '',
                $countDelta[$level],
                $pointDelta[$level] >= 0 ? '+' : '',
                $pointDelta[$level]
            );
        }

        $lines[] = sprintf(
            'Students monitored %s from %d in %s to %d in %s (%s%d).',
            $totalDelta > 0 ? 'increased' : ($totalDelta < 0 ? 'decreased' : 'was unchanged'),
            $previousTotal,
            $previousPeriod,
            $currentTotal,
            $currentPeriod,
            $totalDelta >= 0 ? '+' : '',
            $totalDelta
        );

        $lines[] = sprintf(
            'Individual movement between %s and %s: %d improved, %d worsened, %d held their classification, %d newly monitored, %d no longer monitored.',
            $previousPeriod,
            $currentPeriod,
            $movement['improved'],
            $movement['worsened'],
            $movement['stable'],
            $movement['newly_monitored'],
            $movement['no_longer_monitored']
        );

        $net = $movement['worsened'] - $movement['improved'];

        $lines[] = $net > 0
            ? sprintf('Net position: %d more students moved into a more severe category than out of one.', $net)
            : ($net < 0
                ? sprintf('Net position: %d more students moved into a less severe category than into a more severe one.', abs($net))
                : 'Net position: inward and outward category movement balanced exactly.');

        return [
            'previous_period' => $previousPeriod,
            'available' => true,
            'previous_total' => $previousTotal,
            'previous_counts' => $previousCounts,
            'previous_percentages' => $previousPercentages,
            'count_delta' => $countDelta,
            'percentage_point_delta' => $pointDelta,
            'total_delta' => $totalDelta,
            'transitions' => $movement,
            'headline' => sprintf(
                '%s vs %s: High %s%d students (%s%.2f pp), Moderate %s%d (%s%.2f pp), Low %s%d (%s%.2f pp).',
                $currentPeriod,
                $previousPeriod,
                $countDelta['High'] >= 0 ? '+' : '',
                $countDelta['High'],
                $pointDelta['High'] >= 0 ? '+' : '',
                $pointDelta['High'],
                $countDelta['Moderate'] >= 0 ? '+' : '',
                $countDelta['Moderate'],
                $pointDelta['Moderate'] >= 0 ? '+' : '',
                $pointDelta['Moderate'],
                $countDelta['Low'] >= 0 ? '+' : '',
                $countDelta['Low'],
                $pointDelta['Low'] >= 0 ? '+' : '',
                $pointDelta['Low']
            ),
            'lines' => $lines,
        ];
    }


    public function buildSnapshot(
        int $departmentId,
        string $schoolYear,
        string $semester,
        string $gradingPeriod,
        ?array $generatedBy = null,
        ?string $generatedAt = null
    ): array {
        // departments now come from the mock API.
        $department = app(AcademicStructureRepository::class)->department($departmentId);

        $counts = $this->distribution($departmentId, $schoolYear, $semester, $gradingPeriod);
        $total = $this->monitoredCount($departmentId, $schoolYear, $semester, $gradingPeriod);
        $percentages = $this->percentageDistribution($counts, $total);
        $activeStudents = $this->activeStudentCount($departmentId);

        // Presentation order is most-severe-first: High, Moderate, Low.
        $distribution = [];
        foreach (array_reverse(self::RISK_LEVELS) as $level) {
            $distribution[] = [
                'risk_level' => $level,
                'count' => $counts[$level],
                'percentage' => $percentages[$level],
            ];
        }

        return [
            'meta' => [
                'formula_version' => self::FORMULA_VERSION,
                'computation' => 'deterministic-rule-based-aggregation',
                'ai_used' => false,
                'external_service_calls' => 0,
                'periods' => self::PERIODS,
                'risk_levels' => self::RISK_LEVELS,
                'department' => [
                    'id' => $departmentId,
                    'code' => (string) ($department->code ?? ''),
                    'name' => (string) ($department->name ?? ''),
                ],
                'school_year' => $schoolYear,
                'semester' => $semester,
                'grading_period' => $gradingPeriod,
                'period_number' => $this->periodNumber($gradingPeriod),
                'period_count' => count(self::PERIODS),
                'previous_period' => $this->previousPeriod($gradingPeriod),
                'generated_at' => $generatedAt,
                'generated_by' => $generatedBy,
                'source' => [
                    'table' => 'risk_scores',
                    'processing_status' => self::SCORE_STATUS,
                    'student_status' => 'Active',
                ],
            ],
            'totals' => [
                'monitored' => $total,
                'active_students' => $activeStudents,
                'coverage_percentage' => $activeStudents > 0 ? $this->round($total / $activeStudents * 100, 2) : 0.0,
                'without_score' => max(0, $activeStudents - $total),
            ],
            'distribution' => $distribution,
            'counts_by_level' => $counts,
            'percentages_by_level' => $percentages,
            'comparison' => $this->buildComparison(
                $departmentId,
                $schoolYear,
                $semester,
                $gradingPeriod,
                $counts,
                $total,
                $percentages
            ),
            'programs' => $this->byProgram($departmentId, $schoolYear, $semester, $gradingPeriod),
            'score_statistics' => $this->scoreStatistics($departmentId, $schoolYear, $semester, $gradingPeriod),
        ];
    }

    public function canonicalJson(array $snapshot): string
    {
        return (string) json_encode(
            $this->canonicalize($snapshot),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    protected function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        $ordered = [];
        foreach ($value as $key => $item) {
            $ordered[$key] = $this->canonicalize($item);
        }

        return $ordered;
    }

    /** SHA-256 of the canonical JSON — the report's reproducibility fingerprint. */
    public function digest(array $snapshot): string
    {
        return hash('sha256', $this->canonicalJson($snapshot));
    }

    public function periodsWithData(int $departmentId, string $schoolYear, string $semester): array
    {
        $present = $this->scopedScoreRows($departmentId, $schoolYear, $semester)
            ->pluck('grading_period')
            ->intersect(self::PERIODS)
            ->unique()
            ->all();

        return array_values(array_filter(
            self::PERIODS,
            static fn ($period) => in_array($period, $present, true)
        ));
    }


    public function renderPdf(array $snapshot, string $generatedByName = 'Academic Head', ?array $insights = null): string
    {
        $meta = $snapshot['meta'];
        $department = $meta['department'];
        $period = (string) $meta['grading_period'];

        $pdf = new PdfReportWriter(
            ($department['code'] !== '' ? $department['code'] : 'Department') . ' End-of-Term Academic Risk Report',
            $generatedByName,
            $period . ' - ' . $meta['school_year'],
            $this->reportTimestamp($meta['generated_at'] ?? null)
        );

        /* CHANGED: supply the existing logo files to the PDF writer's repeated report header. */
        $pdf->setHeaderLogos(
            public_path('images/logo/udd-logo.png'),
            public_path('images/logo/acadalert_logo.png')
        );

        $pdf->setFooter(sprintf(
            'AcadAlert deterministic report engine v%s | %s | Generative AI used: NO',
            $meta['formula_version'],
            $this->departmentLabel($department)
        ));

        $pdf->heading('End-of-Term Academic Risk Report');
        $pdf->keyValues([
            'Department' => $this->departmentLabel($department),
            'School Year' => (string) $meta['school_year'],
            'Semester' => $this->semesterLabel((string) $meta['semester']),
            'Grading Period' => sprintf('%s (period %d of %d)', $period, $meta['period_number'], $meta['period_count']),
            'Generated' => $this->formatTimestamp($meta['generated_at'] ?? null),
            'Generated By' => $generatedByName,
            'Computation Method' => 'Deterministic rule-based aggregation of stored risk scores',
        ]);

        $this->renderTotalsSection($pdf, $snapshot);
        $this->renderDistributionSection($pdf, $snapshot);
        $this->renderComparisonSection($pdf, $snapshot);
        $this->renderProgramsSection($pdf, $snapshot);
        $this->renderStatisticsSection($pdf, $snapshot);
        $this->renderProvenanceSection($pdf, $snapshot);

        if (is_array($insights) && $insights !== []) {
            $this->renderAppendix($pdf, $snapshot, $insights);
        }

        return $pdf->output();
    }

    protected function renderTotalsSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('1. Students Monitored');
        $pdf->table(
            ['Metric', 'Value'],
            [
                ['Total students monitored this grading period', number_format((int) $snapshot['totals']['monitored'])],
                ['Active students enrolled in the department', number_format((int) $snapshot['totals']['active_students'])],
                ['Score coverage', number_format((float) $snapshot['totals']['coverage_percentage'], 2) . '%'],
                ['Active students without a completed score', number_format((int) $snapshot['totals']['without_score'])],
            ],
            [3.0, 1.0],
            ['left', 'right']
        );
    }

    protected function renderDistributionSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('2. Risk Classification Distribution');

        $rows = [];
        foreach ($snapshot['distribution'] as $row) {
            $rows[] = [
                $row['risk_level'] . ' Risk',
                number_format((int) $row['count']),
                number_format((float) $row['percentage'], 2) . '%',
            ];
        }
        $rows[] = ['Total', number_format((int) $snapshot['totals']['monitored']), '100.00%'];

        $pdf->table(['Risk Category', 'Students', 'Percentage'], $rows, [2.0, 1.0, 1.0], ['left', 'right', 'right']);
        $pdf->paragraph('Percentages are apportioned with the largest-remainder method, so the three categories always total exactly 100.00%.');
    }

    protected function renderComparisonSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('3. Comparative Summary vs Previous Grading Period');

        $comparison = $snapshot['comparison'];

        if (!$comparison['available']) {
            $pdf->paragraph($comparison['headline']);

            foreach ($comparison['lines'] as $line) {
                $pdf->paragraph($line);
            }

            return;
        }

        $pdf->keyValues([
            'Previous grading period' => (string) $comparison['previous_period'],
            'Students monitored (previous)' => number_format((int) $comparison['previous_total']),
            'Change in students monitored' => $this->signed((int) $comparison['total_delta']),
        ]);

        $rows = [];
        foreach (array_reverse(self::RISK_LEVELS) as $level) {
            $rows[] = [
                $level . ' Risk',
                number_format((int) $snapshot['counts_by_level'][$level]),
                number_format((float) $snapshot['percentages_by_level'][$level], 2) . '%',
                number_format((int) $comparison['previous_counts'][$level]),
                number_format((float) $comparison['previous_percentages'][$level], 2) . '%',
                $this->signed((int) $comparison['count_delta'][$level]),
                $this->signed((float) $comparison['percentage_point_delta'][$level]) . ' pp',
            ];
        }

        $pdf->table(
            ['Risk Category', 'Now', '%', (string) $comparison['previous_period'], '%', 'Change', 'Delta %'],
            $rows,
            [1.7, 0.9, 0.9, 1.0, 0.9, 0.9, 1.0],
            ['left', 'right', 'right', 'right', 'right', 'right', 'right']
        );

        $pdf->subheading('Per-student movement');
        $movement = $comparison['transitions'];
        $pdf->table(
            ['Movement', 'Students'],
            [
                ['Improved (moved to a less severe category)', number_format((int) $movement['improved'])],
                ['Worsened (moved to a more severe category)', number_format((int) $movement['worsened'])],
                ['Held the same classification', number_format((int) $movement['stable'])],
                ['Newly monitored this period', number_format((int) $movement['newly_monitored'])],
                ['No longer monitored', number_format((int) $movement['no_longer_monitored'])],
            ],
            [3.0, 1.0],
            ['left', 'right']
        );

        $pdf->subheading('Narrative summary');
        $pdf->paragraph($comparison['headline']);

        foreach ($comparison['lines'] as $line) {
            $pdf->paragraph($line);
        }
    }

    protected function renderProgramsSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('4. Program Breakdown');

        $rows = [];
        foreach ($snapshot['programs'] as $program) {
            $rows[] = [
                (string) $program['program_code'],
                (string) $program['program_name'],
                number_format((int) $program['monitored']),
                number_format((int) $program['counts']['High']),
                number_format((int) $program['counts']['Moderate']),
                number_format((int) $program['counts']['Low']),
                number_format((float) $program['percentages']['High'], 2) . '%',
            ];
        }

        $pdf->table(
            ['Code', 'Program', 'Monitored', 'High', 'Moderate', 'Low', 'High %'],
            $rows,
            [0.8, 3.0, 1.0, 0.8, 1.0, 0.8, 1.0],
            ['left', 'left', 'right', 'right', 'right', 'right', 'right']
        );
    }

    protected function renderStatisticsSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('5. Risk Score Statistics');

        $stats = $snapshot['score_statistics'];

        $pdf->table(
            ['Statistic', 'Value'],
            [
                ['Students scored', number_format((int) $stats['count'])],
                ['Average risk score', $stats['average'] === null ? 'n/a' : number_format((float) $stats['average'], 1)],
                ['Median risk score', $stats['median'] === null ? 'n/a' : number_format((float) $stats['median'], 1)],
                ['Lowest risk score', $stats['minimum'] === null ? 'n/a' : number_format((int) $stats['minimum'])],
                ['Highest risk score', $stats['maximum'] === null ? 'n/a' : number_format((int) $stats['maximum'])],
            ],
            [3.0, 1.0],
            ['left', 'right']
        );
    }

    protected function renderProvenanceSection(PdfReportWriter $pdf, array $snapshot): void
    {
        $pdf->heading('6. Report Provenance');

        $pdf->table(
            ['Attribute', 'Value'],
            [
                ['Computation method', (string) $snapshot['meta']['computation']],
                ['Formula version', 'v' . (string) $snapshot['meta']['formula_version']],
                ['Generative AI used', $snapshot['meta']['ai_used'] ? 'YES' : 'NO'],
                ['External service calls', number_format((int) $snapshot['meta']['external_service_calls'])],
                ['Source table', (string) $snapshot['meta']['source']['table']],
                ['Score status filter', (string) $snapshot['meta']['source']['processing_status']],
                ['Snapshot SHA-256', $this->digest($snapshot)],
            ],
            [2.0, 4.0],
            ['left', 'left'],
            8.5
        );

        $pdf->paragraph('This report was compiled by deterministic, rule-based aggregation of stored risk score records. No generative AI, language model or external service was invoked in its production, and re-running the same aggregation over the same data reproduces this document byte-for-byte.');
    }


    protected function signed(int|float $value): string
    {
        return ($value >= 0 ? '+' : '') . (is_float($value) ? number_format($value, 2) : number_format($value));
    }

    protected function departmentLabel(array $department): string
    {
        $code = (string) ($department['code'] ?? '');
        $name = (string) ($department['name'] ?? '');

        return $name === '' ? $code : $code . ' - ' . $name;
    }

    protected function semesterLabel(string $semester): string
    {
        return $semester === '2nd' ? 'Second Semester' : 'First Semester';
    }

    protected function formatTimestamp(?string $value): string
    {
        $timestamp = $this->reportTimestamp($value);

        return $timestamp->format('F j, Y g:i A') . ' UTC';
    }

    /** Parse a stored timestamp into a deterministic UTC DateTimeImmutable. */
    protected function reportTimestamp(?string $value): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');

        if ($value === null || trim($value) === '') {
            return new \DateTimeImmutable('2000-01-01 00:00:00', $utc);
        }

        try {
            return new \DateTimeImmutable($value, $utc);
        } catch (\Exception $e) {
            return new \DateTimeImmutable('2000-01-01 00:00:00', $utc);
        }
    }

    protected function renderAppendix(PdfReportWriter $pdf, array $snapshot, array $insights): void
    {
        $pdf->spacer(14);
        $pdf->rule();
        $pdf->heading('Appendix - Departmental Analysis');

        $warning = $insights['warning'] ?? null;

        if (is_array($warning)) {
            $pdf->heading('A1. Configuration Warning', 11.0);
            foreach ($pdf->wrap(
                'High risk accounts for ' . $warning['percentage'] . ' of monitored students ('
                . $warning['high'] . ' of ' . $warning['monitored'] . '). The figures in this report are '
                . 'correct, but a rate this high normally means the configured risk bands are not '
                . 'discriminating. Active configuration: ' . $warning['bands'] . '. Review the thresholds '
                . 'under Admin > Risk Settings before acting on the absolute counts alone.',
                PdfReportWriter::contentWidth()
            ) as $line) {
                $pdf->paragraph($line);
            }
        }

        $this->renderBandsSection($pdf, $insights);
        $this->renderYearLevelSection($pdf, $insights);
        $this->renderInterventionSection($pdf, $insights);
        $this->renderPeriodSeriesSection($pdf, $insights);
    }

    protected function renderBandsSection(PdfReportWriter $pdf, array $insights): void
    {
        $bands = $insights['bands'] ?? [];
        $effective = $insights['bands_effective'] ?? [];

        if ($bands === []) {
            return;
        }

        $pdf->heading('A2. Risk Band Definitions', 11.0);
        $pdf->paragraph('A risk level is assigned deterministically from the AI risk score (0-100) using the '
            . 'configured bands. The same score always maps to the same band.');

        $rows = [];
        foreach ($bands as $level => $range) {
            $rows[] = [
                $level . ' Risk',
                (string) $range,
                (string) ($effective[$level] ?? $range),
            ];
        }

        $pdf->table(['Band', 'Configured score range', 'Effective score range'], $rows, [1.2, 1.0, 1.0], null, 9.0);
    }

    protected function renderYearLevelSection(PdfReportWriter $pdf, array $insights): void
    {
        $years = $insights['year_levels'] ?? [];

        if ($years === []) {
            return;
        }

        $pdf->spacer(10);
        $pdf->heading('A3. Breakdown by Year Level', 11.0);

        $rows = [];
        foreach ($years as $year) {
            $rows[] = [
                (string) $year['label'],
                number_format((int) $year['monitored']),
                number_format((int) $year['counts']['High']),
                number_format((float) $year['percentages']['High'], 2) . '%',
                number_format((int) $year['counts']['Moderate']),
                number_format((int) $year['counts']['Low']),
            ];
        }

        $pdf->table(
            ['Year level', 'Monitored', 'High', 'High %', 'Moderate', 'Low'],
            $rows,
            [1.6, 0.9, 0.8, 0.8, 0.9, 0.8],
            ['left', 'right', 'right', 'right', 'right', 'right'],
            9.0
        );
    }

    protected function renderInterventionSection(PdfReportWriter $pdf, array $insights): void
    {
        $intervention = $insights['intervention'] ?? [];

        if ($intervention === []) {
            return;
        }

        $pdf->spacer(10);
        $pdf->heading('A4. Intervention Status (escalation hand-off)', 11.0);

        $pdf->table(
            ['Measure', 'Value'],
            [
                ['High-risk students in this period', number_format((int) $intervention['high_risk'])],
                ['High-risk students escalated to a Counselor', number_format((int) $intervention['high_escalated'])],
                ['High-risk students still awaiting escalation', number_format((int) $intervention['high_pending'])],
                ['Escalation coverage of the High-risk group', number_format((float) $intervention['coverage_percentage'], 2) . '%'],
                ['Escalation records raised this period', number_format((int) $intervention['total_escalations'])],
                ['Distinct students escalated', number_format((int) $intervention['escalated_students'])],
                ['Open cases (not yet Resolved/Closed)', number_format((int) $intervention['open_cases'])],
                ['Resolved or closed cases', number_format((int) $intervention['resolved_cases'])],
            ],
            [3.0, 1.0],
            ['left', 'right'],
            9.0
        );

        if (!empty($intervention['note'])) {
            $pdf->paragraph((string) $intervention['note']);
        }
    }

    protected function renderPeriodSeriesSection(PdfReportWriter $pdf, array $insights): void
    {
        $series = $insights['period_series'] ?? [];

        if ($series === []) {
            return;
        }

        $pdf->spacer(10);
        $pdf->heading('A5. Term Progression (Prelim - Midterm - Finals)', 11.0);

        $rows = [];
        foreach ($series as $entry) {
            $rows[] = [
                (string) $entry['period'],
                number_format((int) $entry['monitored']),
                number_format((int) $entry['high']),
                number_format((int) $entry['moderate']),
                number_format((int) $entry['low']),
                number_format((float) $entry['high_percentage'], 2) . '%',
                number_format((int) $entry['escalations']),
            ];
        }

        $pdf->table(
            ['Period', 'Monitored', 'High', 'Moderate', 'Low', 'High %', 'Escalations'],
            $rows,
            [1.0, 0.9, 0.7, 0.9, 0.7, 0.8, 1.0],
            ['left', 'right', 'right', 'right', 'right', 'right', 'right'],
            9.0
        );
    }
}

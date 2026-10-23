<?php

namespace App\Repositories\Local;

use App\Services\RiskThresholdService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskScoreRepository
{
    /** Canonical ordering, replacing orderByRaw("FIELD(grading_period, ...)"). */
    public const PERIOD_ORDER = ['Prelim' => 1, 'Midterm' => 2, 'Finals' => 3];

    /** period|school_year => rows, memoised per request. */
    protected array $periodMemo = [];

    public function forPeriod(string $period, string $schoolYear): Collection
    {
        $key = $period . '|' . $schoolYear;

        if (array_key_exists($key, $this->periodMemo)) {
            return $this->periodMemo[$key];
        }

        return $this->periodMemo[$key] = DB::table('risk_scores')
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->get();
    }

    public function levelByStudent(string $period, string $schoolYear): array
    {
        return $this->forPeriod($period, $schoolYear)
            ->mapWithKeys(fn ($row) => [(int) $row->student_id => (string) $row->risk_level])
            ->all();
    }

    public function rowByStudent(string $period, string $schoolYear): array
    {
        return $this->forPeriod($period, $schoolYear)
            ->keyBy(fn ($row) => (int) $row->student_id)
            ->all();
    }

    public function levelCountsFor(array $studentIds, string $period, string $schoolYear): array
    {
        $lookup = array_flip(array_map('intval', $studentIds));
        $counts = ['Low' => 0, 'Moderate' => 0, 'High' => 0];

        foreach ($this->forPeriod($period, $schoolYear) as $row) {
            if (!isset($lookup[(int) $row->student_id])) {
                continue;
            }

            $level = (string) $row->risk_level;
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        return $counts;
    }

    public function levelCountsForPeriodAllYears(array $studentIds, string $period): array
    {
        $lookup = array_flip(array_map('intval', $studentIds));
        $counts = ['Low' => 0, 'Moderate' => 0, 'High' => 0];

        $rows = DB::table('risk_scores')
            ->where('grading_period', $period)
            ->select('student_id', 'risk_level')
            ->get();

        foreach ($rows as $row) {
            if (!isset($lookup[(int) $row->student_id])) {
                continue;
            }

            $level = (string) $row->risk_level;
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        return $counts;
    }

    public function countFor(array $studentIds, string $period, string $schoolYear): int
    {
        $lookup = array_flip(array_map('intval', $studentIds));

        return $this->forPeriod($period, $schoolYear)
            ->filter(fn ($row) => isset($lookup[(int) $row->student_id]))
            ->count();
    }

    public function highCountFor(array $studentIds, string $period, string $schoolYear): int
    {
        return $this->levelCountsFor($studentIds, $period, $schoolYear)['High'];
    }

    public function reclassifyByBands(array $thresholds): int
    {
        $bands = RiskThresholdService::normalize($thresholds);

        $low = $bands['low_threshold'];
        $moderate = $bands['moderate_threshold'];
        $high = $bands['high_threshold'];

        $q = chr(39);

        $case = "CASE"
            . " WHEN risk_score <= {$low} THEN {$q}Low{$q}"
            . " WHEN risk_score <= {$moderate} THEN {$q}Moderate{$q}"
            . " WHEN risk_score >= {$high} THEN {$q}High{$q}"
            . " ELSE {$q}Moderate{$q} END";

        return DB::table('risk_scores')->update(['risk_level' => DB::raw($case)]);
    }

    public function forStudentPeriod($studentId, string $period, string $schoolYear): ?object
    {
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->first();
    }

    public function forStudentsPeriod(array $studentIds, string $period, string $schoolYear): Collection
    {
        if ($studentIds === []) {
            return new Collection();
        }

        return DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->get();
    }

    public function previousLevels(array $studentIds, string $period, string $schoolYear): array
    {
        $order = array_keys(self::PERIOD_ORDER);
        $index = array_search($period, $order, true);

        if ($index === false || $index === 0 || $studentIds === []) {
            return [];
        }

        return DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $order[$index - 1])
            ->where('school_year', $schoolYear)
            ->select('student_id', 'risk_level')
            ->get()
            ->pluck('risk_level', 'student_id')
            ->toArray();
    }

    public function historyForStudent($studentId, string $schoolYear): Collection
    {
        return $this->sortedByPeriod(
            DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('school_year', $schoolYear)
                ->select('grading_period', 'risk_score', 'risk_level')
                ->get()
        );
    }

    public function allForStudent($studentId, string $schoolYear): Collection
    {
        return $this->sortedByPeriod(
            DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('school_year', $schoolYear)
                ->get()
        );
    }

    public function latestPeriod(string $schoolYear, array $order): ?string
    {
        $present = DB::table('risk_scores')
            ->where('school_year', $schoolYear)
            ->pluck('grading_period')
            ->unique();

        $latest = null;

        foreach ($order as $period) {
            if ($present->contains($period)) {
                $latest = $period;
            }
        }

        return $latest;
    }

    public function sortedByPeriod(Collection $rows, bool $descending = false): Collection
    {
        $sorted = $rows->sortBy(
            fn ($row) => self::PERIOD_ORDER[(string) $row->grading_period] ?? 99
        );

        return ($descending ? $sorted->reverse() : $sorted)->values();
    }
}

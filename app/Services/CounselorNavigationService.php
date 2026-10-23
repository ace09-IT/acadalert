<?php

namespace App\Services;

use App\Repositories\Api\AcademicStructureRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorNavigationService
{
    public const CLOSED_STATUSES = ['Resolved', 'Closed'];

    public const FOLLOW_UP_HORIZON_DAYS = 7;

    protected array $cache = [];

    public function __construct(protected AcademicStructureRepository $structure)
    {
    }

    public function scopedStudentIds(?int $departmentId): array
    {
        return $departmentId
            ? $this->structure->studentIdsInDepartment($departmentId, false)
            : array_keys($this->structure->studentPlacements());
    }

    public function counters($counselorId, ?int $departmentId = null): array
    {
        $counselorId = (int) $counselorId;

        return $this->cache[$counselorId . ':' . (int) $departmentId]
            ??= $this->compute($counselorId, $departmentId);
    }

    public function followUpCounts($counselorId, ?int $departmentId = null): array
    {
        $counters = $this->counters($counselorId, $departmentId);

        return [
            'overdue' => $counters['overdue'],
            'upcoming' => $counters['upcoming'],
        ];
    }

    protected function compute(int $counselorId, ?int $departmentId): array
    {
        $studentIds = $this->scopedStudentIds($departmentId);

        $base = fn () => DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds);

        $openCaseIds = $base()
            ->whereNotIn('cases.status', self::CLOSED_STATUSES)
            ->pluck('cases.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        [$overdue, $upcoming] = $this->followUpBreakdown($openCaseIds);

        return [
            'total' => $base()->count(),
            'open' => count($openCaseIds),
            'resolved' => $base()->whereIn('cases.status', self::CLOSED_STATUSES)->count(),
            'overdue' => $overdue,
            'upcoming' => $upcoming,
        ];
    }

    protected function followUpBreakdown(array $openCaseIds): array
    {
        if ($openCaseIds === []) {
            return [0, 0];
        }

        $latestFollowUp = DB::table('case_sessions')
            ->whereIn('case_id', $openCaseIds)
            ->whereNotNull('follow_up_date')
            ->select('case_id', DB::raw('MAX(follow_up_date) as due_on'))
            ->groupBy('case_id')
            ->pluck('due_on', 'case_id');

        if ($latestFollowUp->isEmpty()) {
            return [0, 0];
        }

        $latestSession = DB::table('case_sessions')
            ->whereIn('case_id', $openCaseIds)
            ->select('case_id', DB::raw('MAX(session_date) as last_on'))
            ->groupBy('case_id')
            ->pluck('last_on', 'case_id');

        $today = now()->toDateString();
        $horizon = now()->addDays(self::FOLLOW_UP_HORIZON_DAYS)->toDateString();

        $overdue = 0;
        $upcoming = 0;

        foreach ($latestFollowUp as $caseId => $dueOn) {
            $due = $this->toDate($dueOn);

            if ($due === null) {
                continue;
            }

            if ($due > $today) {
                if ($due <= $horizon) {
                    $upcoming++;
                }

                continue;
            }

            // Due today or earlier: overdue unless a session happened after it.
            $last = $this->toDate($latestSession[$caseId] ?? null);

            if ($last === null || $last <= $due) {
                $overdue++;
            }
        }

        return [$overdue, $upcoming];
    }

    /** Normalise any stored date/datetime to Y-m-d, or null when unparseable. */
    protected function toDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

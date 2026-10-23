<?php

namespace App\Repositories\Local;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CaseRepository
{
    public function forCounselor(
        $counselorId,
        ?array $studentIds = null,
        ?array $excludeStatuses = null,
        ?array $onlyStatuses = null,
        $escalatedSince = null
    ): Collection {
        // An empty id set means "no student in scope", which the previous INNER
        // JOIN also produced — never treat it as "unrestricted".
        if ($studentIds !== null && $studentIds === []) {
            return new Collection();
        }

        $query = DB::table('cases')->where('cases.counselor_id', $counselorId);

        if ($studentIds !== null) {
            $query->whereIn('cases.student_id', $studentIds);
        }

        if ($excludeStatuses !== null) {
            $query->whereNotIn('cases.status', $excludeStatuses);
        }

        if ($onlyStatuses !== null) {
            $query->whereIn('cases.status', $onlyStatuses);
        }

        if ($escalatedSince !== null) {
            $query->where('cases.escalated_at', '>=', $escalatedSince);
        }

        return $query->get([
            'cases.id',
            'cases.student_id',
            'cases.status',
            'cases.priority',
            'cases.escalated_at',
        ]);
    }

    public function existsForCounselorAndStudent($counselorId, $studentId): bool
    {
        return DB::table('cases')
            ->where('counselor_id', $counselorId)
            ->where('student_id', $studentId)
            ->exists();
    }

    public function countBy(Collection $cases, string $column): array
    {
        $counts = [];

        foreach ($cases as $case) {
            $value = (string) $case->{$column};
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }
}

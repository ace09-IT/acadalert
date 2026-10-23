<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class ParentRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function forStudent($studentId): Collection
    {
        return $this->api->where('parents', ['student_id' => $studentId]);
    }

    public function primaryForStudent($studentId): ?object
    {
        $parents = $this->forStudent($studentId);

        return $parents->firstWhere('is_primary_contact', 1) ?? $parents->first();
    }

    public function forStudentsGrouped(array $studentIds): Collection
    {
        return $this->api->whereIn('parents', 'student_id', $studentIds)
            ->groupBy('student_id');
    }

    public function contactIndex(array $studentIds): array
    {
        return $this->forStudentsGrouped($studentIds)
            ->map(fn ($group) => $group->firstWhere('is_primary_contact', 1) ?? $group->first())
            ->all();
    }
}

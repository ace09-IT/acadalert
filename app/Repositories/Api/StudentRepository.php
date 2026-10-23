<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class StudentRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function all(): Collection
    {
        return $this->api->all('students');
    }

    public function find($id): ?object
    {
        return $this->api->find('students', $id);
    }

    public function findByEmail(?string $email): ?object
    {
        if ($email === null || $email === '') {
            return null;
        }

        return $this->api->first('students', ['email' => $email]);
    }

    public function forBlock(int $blockId, bool $activeOnly = true): Collection
    {
        $students = $this->api->where('students', ['block_id' => $blockId]);

        if ($activeOnly) {
            $students = $students->filter(fn ($student) => (string) $student->status === 'Active');
        }

        return $students
            ->sortBy([
                ['last_name', 'asc'],
                ['first_name', 'asc'],
            ])
            ->values();
    }

    public function forIds(array $ids): Collection
    {
        return $this->api->whereIn('students', 'id', $ids);
    }

    /** Active students only, from an id list. */
    public function activeForIds(array $ids): Collection
    {
        return $this->forIds($ids)->filter(fn ($student) => (string) $student->status === 'Active')->values();
    }

    public function active(): Collection
    {
        return $this->api->all('students')
            ->filter(fn ($student) => (string) $student->status === 'Active')
            ->values();
    }

    public function keyedById(): array
    {
        return $this->api->all('students')->keyBy(fn ($student) => (int) $student->id)->all();
    }

    public function activeCountForBlock(int $blockId): int
    {
        return $this->api->count('students', [
            'block_id' => $blockId,
            'status' => 'Active',
        ]);
    }

    public function keyedByEmail(): array
    {
        return $this->api->all('students')
            ->keyBy(fn ($student) => (string) $student->email)
            ->all();
    }

    public function nameIndex(): array
    {
        return $this->api->all('students')
            ->keyBy(fn ($student) => (int) $student->id)
            ->map(fn ($student) => (object) [
                'id' => (int) $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->student_number,
                'email' => $student->email,
            ])
            ->all();
    }
}

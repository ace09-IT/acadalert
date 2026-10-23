<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;

class StaffRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function academicHeadForUser($userId): ?object
    {
        return $this->api->first('academic_heads', ['user_id' => $userId]);
    }

    public function counselorForUser($userId): ?object
    {
        return $this->api->first('counselors', ['user_id' => $userId]);
    }

    public function academicHead($id): ?object
    {
        return $this->api->find('academic_heads', $id);
    }

    public function counselor($id): ?object
    {
        return $this->api->find('counselors', $id);
    }

    public function firstCounselorInDepartment($departmentId): ?object
    {
        return $this->api->first('counselors', ['department_id' => $departmentId]);
    }

    public function counselors(): \Illuminate\Support\Collection
    {
        return $this->api->all('counselors');
    }

    public function userName($userId): ?string
    {
        $user = $this->api->find('users', $userId);

        return $user->name ?? null;
    }

    /** The whole users directory (mock API). */
    public function users(): \Illuminate\Support\Collection
    {
        return $this->api->all('users');
    }

    public function academicHeadsByUserId(): array
    {
        return $this->api->all('academic_heads')
            ->keyBy(fn ($row) => (int) $row->user_id)
            ->all();
    }

    public function counselorsByUserId(): array
    {
        return $this->api->all('counselors')
            ->keyBy(fn ($row) => (int) $row->user_id)
            ->all();
    }

    public function user($userId): ?object
    {
        return $this->api->find('users', $userId);
    }

    public function userNameIndex(): array
    {
        return $this->api->all('users')
            ->keyBy(fn ($user) => (int) $user->id)
            ->map(fn ($user) => (string) $user->name)
            ->all();
    }
}

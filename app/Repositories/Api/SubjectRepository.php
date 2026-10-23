<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class SubjectRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function all(): Collection
    {
        return $this->api->all('subjects');
    }

    public function keyedById(): array
    {
        return $this->all()->keyBy(fn ($subject) => $subject->id)->all();
    }

    public function forProgram(int $programId): Collection
    {
        return $this->api->where('subjects', ['program_id' => $programId]);
    }

    public function find($id): ?object
    {
        return $this->api->find('subjects', $id);
    }
}

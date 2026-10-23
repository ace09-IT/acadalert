<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class AttendanceRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function rawLogsForStudentsGrouped(array $studentIds): Collection
    {
        $rows = $this->api->whereIn('attendance', 'student_id', $studentIds);

        if ($rows->isEmpty()) {
            return new Collection();
        }

        return $rows
            ->map(fn ($row) => (object) [
                'student_id' => $row->student_id,
                'session_date' => $row->session_date,
                'status' => $row->status,
                'hours_duration' => $row->hours_duration,
                'weighted_hours' => $row->weighted_hours,
            ])
            ->groupBy('student_id');
    }

    public function rawLogsForStudent(int $studentId): Collection
    {
        return new Collection($this->api->get('attendance', [
            'student_id' => $studentId,
        ]));
    }

    public function countForStudent(int $studentId): int
    {
        return $this->api->count('attendance', ['student_id' => $studentId]);
    }
}

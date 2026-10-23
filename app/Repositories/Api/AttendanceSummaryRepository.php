<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class AttendanceSummaryRepository
{
    public function __construct(
        protected MockApiClient $api,
        protected SubjectRepository $subjects,
    ) {
    }

    public function subjectSummariesForStudent(int $studentId, string $period, string $schoolYear): Collection
    {
        $rows = $this->api->where('attendance_summaries', [
            'student_id' => $studentId,
            'grading_period' => $period,
            'school_year' => $schoolYear,
        ]);

        if ($rows->isEmpty()) {
            return $rows;
        }

        $subjectIndex = $this->subjects->keyedById();

        return $rows
            ->map(function ($row) use ($subjectIndex) {
                $subject = $subjectIndex[$row->subject_id] ?? null;

                $decorated = clone $row;
                $decorated->subject_code = $subject->subject_code ?? null;
                $decorated->subject_name = $subject->subject_name ?? null;

                return $decorated;
            })
            ->sortBy('attendance_rate')
            ->values();
    }

    public function forStudentsPeriod(array $studentIds, string $period, string $schoolYear): Collection
    {
        return $this->api->whereIn('attendance_summaries', 'student_id', $studentIds)
            ->filter(fn ($row) => (string) $row->grading_period === $period
                && (string) $row->school_year === $schoolYear);
    }

    public function aggregateForStudent(int $studentId, string $period, string $schoolYear): ?object
    {
        $rows = $this->api->where('attendance_summaries', [
            'student_id' => $studentId,
            'grading_period' => $period,
            'school_year' => $schoolYear,
        ]);

        if ($rows->isEmpty()) {
            return (object) [
                'overall_attendance' => null,
                'total_absences' => null,
                'total_lates' => null,
                'total_excused' => null,
                'subject_count' => 0,
            ];
        }

        return (object) [
            // AVG over DECIMAL(5,2) carries MySQL's extra four digits of scale.
            'overall_attendance' => number_format((float) $rows->avg('attendance_rate'), 6, '.', ''),
            'total_absences' => (int) $rows->sum('total_absences'),
            'total_lates' => (int) $rows->sum('total_lates'),
            'total_excused' => (int) $rows->sum('total_excused'),
            'subject_count' => $rows->count(),
        ];
    }

    public function averageAttendancePerStudent(array $studentIds, string $period, string $schoolYear): array
    {
        return $this->forStudentsPeriod($studentIds, $period, $schoolYear)
            ->groupBy('student_id')
            ->map(fn ($rows) => number_format((float) $rows->avg('attendance_rate'), 6, '.', ''))
            ->all();
    }

    public function averageAttendanceRows(array $studentIds, string $period, string $schoolYear): array
    {
        $rows = [];

        foreach ($this->averageAttendancePerStudent($studentIds, $period, $schoolYear) as $studentId => $average) {
            $rows[$studentId] = (object) [
                'student_id' => (int) $studentId,
                'avg_attendance' => $average,
            ];
        }

        return $rows;
    }
}

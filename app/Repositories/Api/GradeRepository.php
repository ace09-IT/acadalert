<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class GradeRepository
{
    public function __construct(
        protected MockApiClient $api,
        protected SubjectRepository $subjects,
    ) {
    }

    public function forStudentsPeriod(array $studentIds, string $period, string $schoolYear): array
    {
        $rows = $this->api->whereIn('grades', 'student_id', $studentIds)
            ->filter(fn ($row) => (string) $row->grading_period === $period
                && (string) $row->school_year === $schoolYear)
            ->sortBy([
                ['student_id', 'asc'],
                ['numerical_grade', 'asc'],
            ])
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $subjectIndex = $this->subjects->keyedById();

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $subject = $subjectIndex[$row->subject_id] ?? null;

            $results[$studentId]['subjects'][] = [
                'subject_id' => (int) $row->subject_id,
                'subject_code' => $subject->subject_code ?? '',
                'subject_name' => $subject->subject_name ?? '',
                'numerical_grade' => (float) $row->numerical_grade,
            ];
        }

        foreach ($results as &$data) {
            $grades = collect($data['subjects']);

            $data['total_subjects'] = $grades->count();
            $data['avg_grade'] = $grades->count() > 0
                ? round($grades->avg('numerical_grade'), 2)
                : 0;
            $data['failing_subjects'] = $grades->where('numerical_grade', '<', 75)->count();
        }
        unset($data);

        return $results;
    }

    public function countForSubject(int $subjectId): int
    {
        return $this->api->count('grades', ['subject_id' => $subjectId]);
    }

    public function subjectGradesForStudent(int $studentId, string $period, string $schoolYear): Collection
    {
        $rows = $this->api->where('grades', [
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
            ->sortBy('numerical_grade')
            ->values();
    }

    public function failingCountsFor(array $studentIds, string $period, string $schoolYear): array
    {
        return $this->api->whereIn('grades', 'student_id', $studentIds)
            ->filter(fn ($row) => (string) $row->grading_period === $period
                && (string) $row->school_year === $schoolYear
                && (float) $row->numerical_grade < 75)
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->count())
            ->all();
    }

    public function averageGradeRows(array $studentIds, string $period, string $schoolYear): array
    {
        $rows = [];

        foreach ($this->averageGradePerStudent($studentIds, $period, $schoolYear) as $studentId => $average) {
            $rows[$studentId] = (object) [
                'student_id' => (int) $studentId,
                'avg_grade' => $average,
            ];
        }

        return $rows;
    }

    public function averageGradePerStudent(array $studentIds, string $period, string $schoolYear): array
    {
        return $this->api->whereIn('grades', 'student_id', $studentIds)
            ->filter(fn ($row) => (string) $row->grading_period === $period
                && (string) $row->school_year === $schoolYear)
            ->groupBy('student_id')
            ->map(fn ($rows) => number_format((float) $rows->avg('numerical_grade'), 6, '.', ''))
            ->all();
    }
}

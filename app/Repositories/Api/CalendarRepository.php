<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class CalendarRepository
{
    public function __construct(protected MockApiClient $api)
    {
    }

    public function periodDateRange(string $schoolYear, string $semester, string $period): ?array
    {
        $entry = $this->entry($schoolYear, $semester, $period);

        if ($entry === null) {
            return null;
        }

        return ['start' => $entry->start_date, 'end' => $entry->end_date];
    }

    public function periodNumber(string $schoolYear, string $semester, string $period): int
    {
        $entry = $this->entry($schoolYear, $semester, $period);

        if ($entry !== null && is_numeric($entry->period_number)) {
            return (int) $entry->period_number;
        }

        $canonical = ['Prelim' => 1, 'Midterm' => 2, 'Finals' => 3];

        return $canonical[$period] ?? 2;
    }

    public function entry(string $schoolYear, string $semester, string $period): ?object
    {
        return $this->api->first('academic_calendar', [
            'school_year' => $schoolYear,
            'semester' => $semester,
            'grading_period' => $period,
        ]);
    }

    public function activePeriod(): ?string
    {
        $entry = $this->api->where('academic_calendar', ['is_active' => 1])
            ->sortBy(fn ($row) => (int) $row->period_number)
            ->first();

        return $entry->grading_period ?? null;
    }

    public function all(): Collection
    {
        return $this->api->all('academic_calendar');
    }

    public function schoolYears(): Collection
    {
        return $this->api->all('school_years');
    }

    public function activeSchoolYear(): ?object
    {
        return $this->api->first('school_years', ['is_active' => 1]);
    }

    /** One school_years row by primary key, or null. */
    public function findSchoolYear(int $id): ?object
    {
        return $this->api->find('school_years', $id);
    }

    public function semesters(): Collection
    {
        return $this->api->all('semesters');
    }
}

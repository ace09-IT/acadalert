<?php

namespace Tests\Feature;

use App\Repositories\Api\AttendanceRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Services\Api\MirrorWriter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mock_api', [
            'base_url' => 'http://bulk.test',
            'core_url' => 'http://core.test',
            'timeout' => 5,
            'retry_times' => 1,
            'retry_sleep_ms' => 0,
            'cache_ttl_seconds' => 0,
            'concurrency' => 5,
            'attendance_chunk' => 25,
            'writes_enabled' => true,
            'core_resources' => ['departments', 'programs', 'students', 'users', 'school_years'],
        ]);
    }

    protected function gradeFixtures(): array
    {
        return [
            ['id' => '1', 'student_id' => '1', 'subject_id' => '1', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '78.00'],
            ['id' => '2', 'student_id' => '1', 'subject_id' => '2', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '70.00'],
            // Different period — must be filtered out.
            ['id' => '3', 'student_id' => '1', 'subject_id' => '3', 'grading_period' => 'Prelim', 'school_year' => '2024-2025', 'numerical_grade' => '99.00'],
        ];
    }

    protected function subjectFixtures(): array
    {
        return [
            ['id' => '1', 'subject_code' => 'IT101', 'subject_name' => 'Intro to Computing'],
            ['id' => '2', 'subject_code' => 'IT102', 'subject_name' => 'Data Structures'],
            ['id' => '3', 'subject_code' => 'IT103', 'subject_name' => 'Networks'],
        ];
    }


    public function test_it_reproduces_the_risk_engine_grade_aggregate_shape(): void
    {
        Http::fake([
            'bulk.test/grades*' => Http::response($this->gradeFixtures(), 200),
            'bulk.test/subjects*' => Http::response($this->subjectFixtures(), 200),
        ]);

        $result = app(GradeRepository::class)->forStudentsPeriod([1], 'Midterm', '2024-2025');

        $this->assertSame([1], array_keys($result));
        $this->assertSame(2, $result[1]['total_subjects']);
        $this->assertSame(74.0, $result[1]['avg_grade']);
        $this->assertSame(1, $result[1]['failing_subjects']);

        // Ordered by numerical_grade ascending, subjects joined in PHP.
        $this->assertSame('IT102', $result[1]['subjects'][0]['subject_code']);
        $this->assertSame(70.0, $result[1]['subjects'][0]['numerical_grade']);
        $this->assertSame('IT101', $result[1]['subjects'][1]['subject_code']);
        $this->assertSame(78.0, $result[1]['subjects'][1]['numerical_grade']);
    }

    public function test_it_returns_no_grade_rows_when_a_student_has_none(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->assertSame([], app(GradeRepository::class)->forStudentsPeriod([9], 'Midterm', '2024-2025'));
    }

    public function test_it_joins_subject_names_for_one_student(): void
    {
        Http::fake([
            'bulk.test/grades*' => Http::response([
                ['id' => '1', 'student_id' => '1', 'subject_id' => '2', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '88.00'],
            ], 200),
            'bulk.test/subjects*' => Http::response([
                ['id' => '2', 'subject_code' => 'IT102', 'subject_name' => 'Data Structures'],
            ], 200),
        ]);

        $grades = app(GradeRepository::class)->subjectGradesForStudent(1, 'Midterm', '2024-2025');

        $this->assertCount(1, $grades);
        $this->assertSame('IT102', $grades[0]->subject_code);
        $this->assertSame('Data Structures', $grades[0]->subject_name);
        $this->assertSame('88.00', $grades[0]->numerical_grade);
    }

    public function test_it_computes_average_grade_per_student(): void
    {
        Http::fake([
            'bulk.test/grades*' => Http::response([
                ['id' => '1', 'student_id' => '1', 'subject_id' => '1', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '80.00'],
                ['id' => '2', 'student_id' => '1', 'subject_id' => '2', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '90.00'],
                ['id' => '3', 'student_id' => '2', 'subject_id' => '1', 'grading_period' => 'Midterm', 'school_year' => '2024-2025', 'numerical_grade' => '75.00'],
            ], 200),
        ]);

        $averages = app(GradeRepository::class)->averageGradePerStudent([1, 2], 'Midterm', '2024-2025');

        $this->assertSame('85.000000', $averages[1]);
        $this->assertSame('75.000000', $averages[2]);
    }


    public function test_it_groups_raw_attendance_logs_by_student(): void
    {
        Http::fake([
            'bulk.test/attendance*' => Http::response([
                ['id' => '1', 'student_id' => '1', 'session_date' => '2024-09-01', 'status' => 'Present', 'hours_duration' => '2.0', 'weighted_hours' => '2.0', 'remarks' => 'x'],
                ['id' => '2', 'student_id' => '1', 'session_date' => '2024-09-02', 'status' => 'Absent', 'hours_duration' => '2.0', 'weighted_hours' => '0.0', 'remarks' => 'y'],
                ['id' => '3', 'student_id' => '2', 'session_date' => '2024-09-01', 'status' => 'Late', 'hours_duration' => '2.0', 'weighted_hours' => '1.0', 'remarks' => 'z'],
            ], 200),
        ]);

        $grouped = app(AttendanceRepository::class)->rawLogsForStudentsGrouped([1, 2]);

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped->get(1));
        $this->assertCount(1, $grouped->get(2));
        $this->assertSame('Absent', $grouped->get(1)[1]->status);

        // Only the five columns the original select list returned are retained.
        $this->assertSame(
            ['student_id', 'session_date', 'status', 'hours_duration', 'weighted_hours'],
            array_keys((array) $grouped->get(1)[0])
        );
    }

    public function test_it_does_not_call_the_api_when_there_are_no_students(): void
    {
        Http::fake();

        $this->assertCount(0, app(AttendanceRepository::class)->rawLogsForStudentsGrouped([]));
        Http::assertNothingSent();
    }


    public function test_it_resolves_the_period_window_and_number_from_the_calendar(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'academic_calendar')
                && str_contains($request->url(), 'grading_period=Midterm')) {
                return Http::response([[
                    'id' => '1',
                    'school_year' => '2024-2025',
                    'semester' => '1st',
                    'grading_period' => 'Midterm',
                    'period_number' => '2',
                    'start_date' => '2024-12-01',
                    'end_date' => '2024-12-20',
                ]], 200);
            }

            return Http::response([], 200);
        });

        $calendar = app(CalendarRepository::class);

        $this->assertSame(
            ['start' => '2024-12-01', 'end' => '2024-12-20'],
            $calendar->periodDateRange('2024-2025', '1st', 'Midterm')
        );
        $this->assertSame(2, $calendar->periodNumber('2024-2025', '1st', 'Midterm'));
    }

    public function test_it_falls_back_to_canonical_period_numbers_without_a_calendar_row(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $calendar = app(CalendarRepository::class);

        $this->assertNull($calendar->periodDateRange('2024-2025', '1st', 'Finals'));
        $this->assertSame(1, $calendar->periodNumber('2024-2025', '1st', 'Prelim'));
        $this->assertSame(3, $calendar->periodNumber('2024-2025', '1st', 'Finals'));
        // Deprecated/unknown period never binds to a non-existent slot.
        $this->assertSame(2, $calendar->periodNumber('2024-2025', '1st', 'Semifinal'));
    }


    public function test_it_ignores_collections_the_application_owns_outright(): void
    {
        Http::fake();

        // risk_scores / flags / cases are local-only and have no API counterpart.
        $mirror = app(MirrorWriter::class);
        $mirror->created('risk_scores', 1, ['risk_score' => 70]);
        $mirror->deleted('flags', 5);

        Http::assertNothingSent();
    }

    public function test_it_mirrors_a_create_with_the_local_id(): void
    {
        Http::fake(['core.test/departments*' => Http::response(['id' => '6'], 201)]);

        app(MirrorWriter::class)->created('departments', 6, ['code' => 'CCS', 'name' => 'College of Computing']);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'http://core.test/departments'
                && $body['id'] === '6'
                && $body['code'] === 'CCS';
        });
    }

    public function test_deferred_mirrors_are_only_sent_on_flush(): void
    {
        Http::fake(['*' => Http::response(['id' => '1'], 200)]);

        $mirror = app(MirrorWriter::class);
        $mirror->defer();
        $mirror->created('departments', 6, ['code' => 'CCS']);
        $mirror->updated('departments', 7, ['code' => 'CEA']);

        HTTP::assertNothingSent();
        $this->assertSame(2, $mirror->pendingCount());

        $mirror->flush();

        Http::assertSentCount(2);
        $this->assertSame(0, $mirror->pendingCount());
    }

    public function test_a_failed_mirror_is_logged_but_never_fails_the_local_write(): void
    {
        config()->set('services.mock_api.retry_times', 1);
        Http::fake(['*' => Http::response('boom', 500)]);
        Log::spy();

        // Must not throw: the local write already committed, so a transport
        // failure has to be logged and queued rather than surfaced to the user.
        app(MirrorWriter::class)->created('departments', 6, ['code' => 'CCS']);

        Log::shouldHaveReceived('error')->atLeast()->once();
    }
}

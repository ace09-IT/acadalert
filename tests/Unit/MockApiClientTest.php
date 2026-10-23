<?php

namespace Tests\Unit;

use App\Exceptions\MockApiException;
use App\Services\Api\MockApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MockApiClientTest extends TestCase
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
            'attendance_chunk' => 2,
            'writes_enabled' => true,
            'core_resources' => ['departments', 'students', 'payments'],
        ]);
    }

    protected function client(): MockApiClient
    {
        return new MockApiClient();
    }


    public function test_it_coerces_values_to_the_types_pdo_used_to_return(): void
    {
        Http::fake([
            'bulk.test/grades*' => Http::response([
                'id' => '7',
                'student_id' => '1',
                'subject_id' => '3',
                'grading_period' => 'Midterm',
                'school_year' => '2024-2025',
                'numerical_grade' => '78.00',
                'letter_grade' => 'C',
                'is_active' => '1',
            ], 200),
        ]);

        $grade = $this->client()->find('grades', 7);

        $this->assertSame(7, $grade->id);
        $this->assertSame(1, $grade->student_id);
        $this->assertSame(3, $grade->subject_id);

        // DECIMAL keeps its string form and scale: Blade prints several of these
        // raw ("{{ $grade->numerical_grade }}%"), so "78.00" must not become 78.
        $this->assertSame('78.00', $grade->numerical_grade);

        // TINYINT(1) comes back as 0/1.
        $this->assertSame(1, $grade->is_active);

        $this->assertSame('Midterm', $grade->grading_period);
        $this->assertSame('C', $grade->letter_grade);
    }

    public function test_decimal_strings_still_compare_and_sort_numerically(): void
    {
        Http::fake([
            'bulk.test/grades*' => Http::response([
                ['id' => '1', 'numerical_grade' => '9.00'],
                ['id' => '2', 'numerical_grade' => '10.00'],
                ['id' => '3', 'numerical_grade' => '78.00'],
            ], 200),
        ]);

        $grades = $this->client()->where('grades', ['student_id' => 1]);

        // Lexicographic ordering would put "78.00" first and "9.00" last;
        // numeric-string semantics must order them correctly.
        $this->assertSame(
            ['9.00', '10.00', '78.00'],
            $grades->sortBy('numerical_grade')->pluck('numerical_grade')->values()->all()
        );

        $this->assertSame(2, $grades->where('numerical_grade', '<', 75)->count());
    }


    public function test_it_routes_writable_collections_to_core_and_reads_to_bulk(): void
    {
        $client = $this->client();

        $this->assertTrue($client->isWritable('students'));
        $this->assertSame('http://core.test/students', $client->urlFor('students'));
        $this->assertSame('http://core.test/students/12', $client->urlFor('students', 12));

        $this->assertFalse($client->isWritable('grades'));
        $this->assertSame('http://bulk.test/grades', $client->urlFor('grades'));
        $this->assertSame('http://bulk.test/grades/12', $client->urlFor('grades', 12));
    }


    public function test_it_chunks_where_in_and_merges_every_chunk(): void
    {
        Http::fake(['*' => Http::response([['id' => '1', 'student_id' => '1']], 200)]);

        $rows = $this->client()->whereIn('attendance', 'student_id', [1, 2, 3, 4, 5]);

        $this->assertCount(3, $rows);
        Http::assertSentCount(3);
    }

    public function test_where_in_uses_json_server_compatible_array_encoding(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->client()->whereIn('grades', 'student_id', [1, 2]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'student_id%5B0%5D=1')
                && str_contains($request->url(), 'student_id%5B1%5D=2');
        });
    }

    public function test_where_in_returns_nothing_without_asking_the_api(): void
    {
        Http::fake();

        $this->assertCount(0, $this->client()->whereIn('grades', 'student_id', []));
        Http::assertNothingSent();
    }

    public function test_it_counts_using_the_total_count_header(): void
    {
        Http::fake([
            'bulk.test/attendance*' => Http::response([['id' => '1']], 200, ['X-Total-Count' => '158624']),
        ]);

        $this->assertSame(158624, $this->client()->count('attendance'));
    }

    public function test_it_paginates_with_page_and_limit(): void
    {
        Http::fake([
            'bulk.test/parents*' => Http::response(
                [['id' => '1', 'student_id' => '1'], ['id' => '2', 'student_id' => '1']],
                200,
                ['X-Total-Count' => '3170']
            ),
        ]);

        $page = $this->client()->paginate('parents', ['student_id' => 1], 2, 3);

        $this->assertSame(3170, $page->total());
        $this->assertSame(3, $page->currentPage());
        $this->assertSame(2, $page->perPage());
        $this->assertCount(2, $page->items());

        Http::assertSent(fn ($request) => str_contains($request->url(), '_page=3')
            && str_contains($request->url(), '_limit=2'));
    }

    public function test_a_failed_request_throws_and_is_never_silently_empty(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(MockApiException::class);

        $this->client()->get('grades');
    }

    public function test_it_retries_transient_server_errors(): void
    {
        config()->set('services.mock_api.retry_times', 3);
        config()->set('services.mock_api.retry_sleep_ms', 0);

        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts < 3
                ? Http::response('unavailable', 503)
                : Http::response([['id' => '1']], 200);
        });

        $this->assertCount(1, $this->client()->get('grades'));
        $this->assertSame(3, $attempts);
    }


    public function test_it_refuses_to_write_to_a_read_only_collection(): void
    {
        Http::fake();

        try {
            $this->client()->create('grades', 1, ['numerical_grade' => '78.00']);
            $this->fail('Expected MockApiException for a read-only collection.');
        } catch (MockApiException $e) {
            $this->assertStringContainsString('read-only', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_refuses_writes_when_disabled_by_config(): void
    {
        config()->set('services.mock_api.writes_enabled', false);
        Http::fake();

        $this->expectException(MockApiException::class);

        $this->client()->create('students', 1, ['first_name' => 'A']);
    }

    public function test_it_creates_with_an_explicit_id_via_post(): void
    {
        Http::fake(['core.test/students*' => Http::response(['id' => '1586', 'first_name' => 'A'], 201)]);

        $row = $this->client()->create('students', 1586, ['first_name' => 'A', 'block_id' => 3]);

        $this->assertSame(1586, $row->id);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'http://core.test/students'
                // The explicit id keeps the mock aligned with the local
                // AUTO_INCREMENT id every foreign key already points at.
                && $body['id'] === '1586'
                && $body['block_id'] === '3';
        });
    }

    public function test_it_replaces_via_put(): void
    {
        Http::fake(['core.test/students/5*' => Http::response(['id' => '5', 'first_name' => 'B'], 200)]);

        $this->assertSame(5, $this->client()->replace('students', 5, ['first_name' => 'B'])->id);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'http://core.test/students/5');
    }

    public function test_replace_falls_back_to_create_when_put_returns_404(): void
    {
        // json-server PUT does NOT create — it 404s when the row is absent.
        Http::fake(function ($request) {
            return $request->method() === 'PUT'
                ? Http::response('Not Found', 404)
                : Http::response(['id' => '9', 'first_name' => 'C'], 201);
        });

        $this->assertSame(9, $this->client()->replace('students', 9, ['first_name' => 'C'])->id);

        Http::assertSent(fn ($request) => $request->method() === 'PUT');
        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_remove_is_idempotent_when_the_row_is_already_gone(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $this->assertTrue($this->client()->remove('students', 9));
    }

    public function test_forget_clears_the_request_scoped_memo(): void
    {
        Http::fake(['*' => Http::response([['id' => '1']], 200)]);

        $client = $this->client();

        // Identical reads are memoised...
        $client->get('grades');
        $client->get('grades');
        Http::assertSentCount(1);

        $client->forget('grades');
        $client->get('grades');
        Http::assertSentCount(2);
    }
}

<?php

namespace App\Services\Api;

use App\Exceptions\MockApiException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MockApiClient
{
    /** Numeric ids, foreign keys and integer business columns. */
    protected const INT_KEYS = [
        'id', 'user_id', 'department_id', 'program_id', 'year_level_id', 'block_id',
        'student_id', 'subject_id', 'counselor_id', 'academic_head_id', 'case_id',
        'flag_id', 'recommendation_id', 'escalated_by', 'generated_by', 'shared_by',
        'updated_by', 'performed_by', 'recorded_by', 'acknowledged_by',
        'reopened_from_case_id', 'year_number', 'year_level', 'semester',
        'semester_number', 'period_number', 'units', 'max_students', 'max_caseload',
        'block_number', 'risk_score', 'risk_score_at_escalation',
        'consecutive_periods_count', 'api_attempt_count', 'batch', 'total_students',
        'total_monitored', 'low_count', 'moderate_count', 'high_count',
        'total_absences', 'total_lates', 'total_excused', 'low_threshold',
        'moderate_threshold', 'high_threshold',
    ];

    protected const DECIMAL_KEYS = [
        'numerical_grade', 'raw_score', 'hours_duration', 'weighted_hours',
        'total_required_hours', 'total_weighted_hours', 'attendance_rate',
        'amount', 'paid_amount', 'balance', 'grade_weight', 'attendance_weight',
        'low_percentage', 'moderate_percentage', 'high_percentage', 'amount_changed',
    ];

    protected const BOOL_KEYS = [
        'is_active', 'is_archived', 'is_acknowledged', 'is_completed',
        'is_dropped', 'warning_issued', 'is_primary_contact',
        'lives_with_student', 'is_shared',
    ];

    protected string $bulkUrl;
    protected string $coreUrl;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleepMs;
    protected int $cacheTtl;
    protected int $concurrency;
    protected int $attendanceChunk;
    protected bool $writesEnabled;

    protected array $coreResources;

    /** Request-scoped memo: "resource|query" => cast rows. */
    protected array $memo = [];

    public function __construct()
    {
        $this->bulkUrl = rtrim((string) config('services.mock_api.base_url'), '/');
        $this->coreUrl = rtrim((string) config('services.mock_api.core_url'), '/');
        $this->timeout = (int) config('services.mock_api.timeout', 15);
        $this->retryTimes = max(1, (int) config('services.mock_api.retry_times', 3));
        $this->retrySleepMs = (int) config('services.mock_api.retry_sleep_ms', 150);
        $this->cacheTtl = (int) config('services.mock_api.cache_ttl_seconds', 0);
        $this->concurrency = max(1, (int) config('services.mock_api.concurrency', 10));
        $this->attendanceChunk = max(1, (int) config('services.mock_api.attendance_chunk', 25));
        $this->writesEnabled = (bool) config('services.mock_api.writes_enabled', true);
        $this->coreResources = (array) config('services.mock_api.core_resources', []);
    }


    public function isWritable(string $resource): bool
    {
        return in_array($resource, $this->coreResources, true);
    }

    public function baseUrlFor(string $resource): string
    {
        return $this->isWritable($resource) ? $this->coreUrl : $this->bulkUrl;
    }

    public function urlFor(string $resource, $id = null): string
    {
        $url = $this->baseUrlFor($resource) . '/' . ltrim($resource, '/');

        return $id === null ? $url : $url . '/' . rawurlencode((string) $id);
    }

    public function forget(?string $resource = null): void
    {
        if ($resource === null) {
            $this->memo = [];

            return;
        }

        foreach (array_keys($this->memo) as $key) {
            if (str_starts_with((string) $key, $resource . '|')) {
                unset($this->memo[$key]);
            }
        }

        if ($this->cacheTtl > 0) {
            Cache::increment($this->versionKey($resource));
        }
    }

    protected function versionKey(string $resource): string
    {
        return 'mock_api_version_' . $resource;
    }


    public function get(string $resource, array $query = []): array
    {
        return $this->fetch($resource, $query);
    }

    public function all(string $resource): Collection
    {
        return new Collection($this->fetch($resource, []));
    }

    /** A single row by id, or null when it does not exist. */
    public function find(string $resource, $id): ?object
    {
        $response = $this->raw('GET', $this->urlFor($resource, $id));

        if ($response->status() === 404) {
            return null;
        }

        $this->guard($response, 'GET', $resource, $id);

        $json = $response->json();

        return is_array($json) ? $this->castRow($json) : null;
    }

    public function first(string $resource, array $filters): ?object
    {
        $rows = $this->fetch($resource, array_merge($filters, ['_limit' => 1]));

        return $rows[0] ?? null;
    }

    public function where(string $resource, array $filters): Collection
    {
        return new Collection($this->fetch($resource, $filters));
    }

    public function existsWhere(string $resource, array $filters): bool
    {
        return $this->first($resource, $filters) !== null;
    }

    public function whereIn(string $resource, string $column, array $values, ?int $chunkSize = null): Collection
    {
        $values = $this->normaliseIdList($values);

        if ($values === []) {
            return new Collection();
        }

        $chunks = array_chunk($values, $chunkSize ?? $this->attendanceChunk);

        if (count($chunks) === 1) {
            return new Collection($this->fetch($resource, [$column => $chunks[0]]));
        }

        return new Collection($this->fetchConcurrently($resource, $column, $chunks));
    }

    public function count(string $resource, array $filters = []): int
    {
        $response = $this->raw('GET', $this->urlFor($resource), array_merge($filters, [
            '_page' => 1,
            '_limit' => 1,
        ]));

        $this->guard($response, 'GET', $resource);

        return $this->totalFrom($response, $resource);
    }

    public function paginate(
        string $resource,
        array $filters,
        int $perPage,
        int $page,
        ?string $sort = null,
        string $order = 'asc'
    ): LengthAwarePaginator {
        $query = array_merge($filters, [
            '_page' => max(1, $page),
            '_limit' => max(1, $perPage),
        ]);

        if ($sort !== null) {
            $query['_sort'] = $sort;
            $query['_order'] = strtolower($order) === 'desc' ? 'desc' : 'asc';
        }

        $response = $this->raw('GET', $this->urlFor($resource), $query);
        $this->guard($response, 'GET', $resource);

        $rows = new Collection($this->decodeCollection($response, $resource));
        $total = $response->header('X-Total-Count');

        return new LengthAwarePaginator(
            $rows,
            $total !== null && $total !== '' ? (int) $total : $rows->count(),
            max(1, $perPage),
            max(1, $page),
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    protected function totalFrom(Response $response, string $resource): int
    {
        $header = $response->header('X-Total-Count');

        if ($header !== null && $header !== '') {
            return (int) $header;
        }

        return count($this->decodeCollection($response, $resource));
    }


    public function create(string $resource, $id, array $attributes): object
    {
        $this->assertWritable($resource);

        $response = $this->raw(
            'POST',
            $this->urlFor($resource),
            [],
            array_merge($attributes, ['id' => $id])
        );

        $this->guard($response, 'POST', $resource, $id);
        $this->forget($resource);

        return $this->castRow($this->decodeRow($response, $resource));
    }

    public function replace(string $resource, $id, array $attributes): object
    {
        $this->assertWritable($resource);

        $response = $this->raw(
            'PUT',
            $this->urlFor($resource, $id),
            [],
            array_merge($attributes, ['id' => $id])
        );

        if ($response->status() === 404) {
            return $this->create($resource, $id, $attributes);
        }

        $this->guard($response, 'PUT', $resource, $id);
        $this->forget($resource);

        return $this->castRow($this->decodeRow($response, $resource));
    }

    public function remove(string $resource, $id): bool
    {
        $this->assertWritable($resource);

        $response = $this->raw('DELETE', $this->urlFor($resource, $id));

        if ($response->status() === 404) {
            return true;
        }

        $this->guard($response, 'DELETE', $resource, $id);
        $this->forget($resource);

        return true;
    }

    public function removeMany(string $resource, array $ids): int
    {
        $this->assertWritable($resource);

        $ids = $this->normaliseIdList($ids);

        if ($ids === []) {
            return 0;
        }

        $responses = Http::pool(function (Pool $pool) use ($resource, $ids) {
            $requests = [];

            foreach ($ids as $index => $id) {
                $requests[] = $pool->as((string) $index)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->delete($this->urlFor($resource, $id));
            }

            return $requests;
        });

        $removed = 0;
        $failed = [];

        foreach ($ids as $index => $id) {
            $response = $responses[(string) $index] ?? null;

            if ($response instanceof Response
                && ($response->successful() || $response->status() === 404)) {
                $removed++;
                continue;
            }

            $failed[] = $id;
        }

        foreach ($failed as $id) {
            $this->remove($resource, $id);
            $removed++;
        }

        $this->forget($resource);

        return $removed;
    }

    protected function assertWritable(string $resource): void
    {
        if (!$this->writesEnabled) {
            throw new MockApiException(
                "Mock API writes are disabled (MOCK_API_WRITES_ENABLED=false); refused to write [{$resource}]."
            );
        }

        if (!$this->isWritable($resource)) {
            throw new MockApiException(
                "Mock API collection [{$resource}] is read-only (served by the BULK server); " .
                'it is not listed in services.mock_api.core_resources.'
            );
        }
    }


    protected function fetch(string $resource, array $query): array
    {
        $key = $resource . '|' . json_encode($query);

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $loader = function () use ($resource, $query): array {
            $response = $this->raw('GET', $this->urlFor($resource), $query);
            $this->guard($response, 'GET', $resource);

            return $this->decodeCollection($response, $resource);
        };

        if ($this->cacheTtl > 0) {
            $version = (int) Cache::get($this->versionKey($resource), 0);
            $cacheKey = 'mock_api:' . $resource . ':v' . $version . ':' . md5((string) json_encode($query));

            return $this->memo[$key] = Cache::remember($cacheKey, $this->cacheTtl, $loader);
        }

        return $this->memo[$key] = $loader();
    }

    protected function fetchConcurrently(string $resource, string $column, array $chunks): array
    {
        $responses = Http::pool(function (Pool $pool) use ($resource, $column, $chunks) {
            $url = $this->urlFor($resource);
            $requests = [];

            foreach ($chunks as $index => $chunk) {
                $requests[] = $pool->as((string) $index)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->get($url, [$column => $chunk]);
            }

            return $requests;
        });

        $rows = [];
        $failed = [];

        foreach ($chunks as $index => $chunk) {
            $response = $responses[(string) $index] ?? null;

            if (!$response instanceof Response || !$response->successful()) {
                $failed[] = $chunk;
                continue;
            }

            foreach ($this->decodeCollection($response, $resource) as $row) {
                $rows[] = $row;
            }
        }

        foreach ($failed as $chunk) {
            foreach ($this->fetch($resource, [$column => $chunk]) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function raw(string $method, string $url, array $query = [], ?array $payload = null): Response
    {
        $method = strtoupper($method);
        $attempts = $this->retryTimes;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $pending = Http::timeout($this->timeout)->acceptJson();

                if ($payload !== null) {
                    $pending = $pending->asJson();
                }

                $response = match ($method) {
                    'GET' => $pending->get($url, $query),
                    'POST' => $pending->post($url, $this->forStorage($payload ?? [])),
                    'PUT' => $pending->put($url, $this->forStorage($payload ?? [])),
                    'PATCH' => $pending->patch($url, $this->forStorage($payload ?? [])),
                    'DELETE' => $pending->delete($url, $query),
                    default => throw new MockApiException("Mock API: unsupported HTTP method [{$method}]."),
                };
            } catch (MockApiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($attempt >= $attempts) {
                    throw new MockApiException(
                        "Mock API {$method} {$url} failed after {$attempts} attempt(s): {$e->getMessage()}",
                        0,
                        $e
                    );
                }

                usleep($this->retrySleepMs * 1000);

                continue;
            }

            if (($response->serverError() || $response->status() === 429) && $attempt < $attempts) {
                usleep($this->retrySleepMs * 1000);

                continue;
            }

            return $response;
        }

        throw new MockApiException("Mock API {$method} {$url} failed after {$attempts} attempt(s).");
    }

    protected function guard(Response $response, string $method, string $resource, $id = null): void
    {
        if ($response->successful()) {
            return;
        }

        $target = $id === null ? $resource : $resource . '/' . $id;
        $snippet = mb_substr(trim((string) $response->body()), 0, 300);

        Log::error('Mock API request failed', [
            'method' => $method,
            'resource' => $target,
            'status' => $response->status(),
            'body' => $snippet,
        ]);

        throw new MockApiException(
            "Mock API {$method} {$target} returned HTTP {$response->status()}. {$snippet}"
        );
    }

    protected function decodeCollection(Response $response, string $resource): array
    {
        $json = $response->json();

        if (!is_array($json)) {
            throw new MockApiException(
                "Mock API GET {$resource} returned a non-JSON body (HTTP {$response->status()})."
            );
        }

        if ($json !== [] && !array_is_list($json)) {
            $json = [$json];
        }

        $rows = [];

        foreach ($json as $row) {
            if (is_array($row)) {
                $rows[] = $this->castRow($row);
            }
        }

        return $rows;
    }

    protected function decodeRow(Response $response, string $resource): array
    {
        $json = $response->json();

        if (!is_array($json)) {
            throw new MockApiException(
                "Mock API returned a non-JSON body for {$resource} (HTTP {$response->status()})."
            );
        }

        if (!array_is_list($json)) {
            return $json;
        }

        return is_array($json[0] ?? null) ? $json[0] : [];
    }

    protected function castRow(array $row): object
    {
        $cast = [];

        foreach ($row as $key => $value) {
            $cast[(string) $key] = $this->castValue((string) $key, $value);
        }

        return (object) $cast;
    }

    protected function castValue(string $key, $value)
    {
        if ($value === null) {
            return null;
        }

        // MySQL returns TINYINT(1) as a PHP int, so booleans stay 0/1.
        if (in_array($key, self::BOOL_KEYS, true)) {
            return in_array($value, [1, '1', true, 'true', 'yes'], true) ? 1 : 0;
        }

        if (in_array($key, self::INT_KEYS, true)) {
            return is_numeric($value) ? (int) $value : $value;
        }

        // MySQL returns DECIMAL as a PHP string — preserved on purpose (see const).
        if (in_array($key, self::DECIMAL_KEYS, true)) {
            return (string) $value;
        }

        return $value;
    }

    protected function normaliseIdList(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $clean[(string) $value] = $value;
        }

        return array_values($clean);
    }

    protected function forStorage(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $out[$key] = null;
                continue;
            }

            if (is_bool($value)) {
                $out[$key] = $value ? '1' : '0';
                continue;
            }

            $out[$key] = is_scalar($value) ? (string) $value : $value;
        }

        return $out;
    }
}

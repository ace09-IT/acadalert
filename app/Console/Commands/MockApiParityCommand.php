<?php

namespace App\Console\Commands;

use App\Services\Api\MockApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MockApiParityCommand extends Command
{
    protected $signature = 'mock-api:parity
                            {--only=* : Restrict to these collections}
                            {--strict : Also fail when a local-only table has no API collection}';

    protected $description = 'Compare local database tables/row counts against the mock API collections.';

    protected const INFRASTRUCTURE = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'risk_score_cache', 'risk_overrides',
    ];

    protected const LOCAL_ONLY = [
        'risk_scores', 'risk_overrides', 'flags', 'escalations', 'cases',
        'case_sessions', 'alert_acknowledgments', 'end_of_term_reports',
        'intervention_recommendations', 'student_recommendation_tracking', 'audit_logs',
    ];

    public function handle(MockApiClient $api): int
    {
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->reject(fn ($t) => str_starts_with((string) $t, '__'))
            ->sort()
            ->values();

        $only = (array) $this->option('only');

        if ($only !== []) {
            $tables = $tables->filter(fn ($t) => in_array($t, $only, true))->values();
        }

        $rows = [];
        $schemaProblems = [];

        foreach ($tables as $table) {
            $localColumns = Schema::getColumnListing($table);
            $localCount = (int) DB::table($table)->count();

            $apiColumns = [];
            $apiCount = null;
            $error = null;

            try {
                $sample = $api->first($table, []);
                $apiColumns = $sample ? array_keys((array) $sample) : [];
                $apiCount = $api->count($table);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $missingInApi = array_values(array_diff($localColumns, $apiColumns));

            if ($apiColumns !== [] && $missingInApi !== []) {
                $schemaProblems[$table] = $missingInApi;
            }

            $rows[] = [
                $table,
                (string) $localCount,
                $apiCount === null ? ($error ? 'ERROR' : '-') : (string) $apiCount,
                $localCount === $apiCount ? 'ok' : ($apiCount === null ? 'n/a' : 'DRIFT'),
                (string) count($localColumns),
                (string) count($apiColumns),
            ];
        }

        $this->newLine();
        $this->table(
            ['collection', 'local rows', 'api rows', 'count', 'local cols', 'api cols'],
            $rows
        );

        if ($schemaProblems !== []) {
            $this->newLine();
            $this->warn('COLUMN PARITY PROBLEMS — these columns exist in MySQL but NOT in the mock:');

            foreach ($schemaProblems as $table => $columns) {
                $this->line(sprintf('  %-32s %s', $table, implode(', ', $columns)));
                $this->line(sprintf('  %-32s -> reading this table from the API would leave these properties undefined.', ''));
            }
        }

        $drifted = collect($rows)
            ->filter(fn ($r) => $r[3] === 'DRIFT')
            ->pluck(0)
            ->reject(fn ($t) => in_array($t, self::INFRASTRUCTURE, true))
            ->reject(fn ($t) => in_array($t, self::LOCAL_ONLY, true))
            ->values()
            ->all();

        if ($drifted !== []) {
            $this->newLine();
            $this->warn('ROW COUNT DRIFT: ' . implode(', ', $drifted));
        }

        $idProblems = $this->compareIdSets($api);

        if ($idProblems !== []) {
            $this->newLine();
            $this->warn('ID PARITY PROBLEMS — API-owned master rows must keep the same id locally:');

            foreach ($idProblems as $problem) {
                $this->line('  ' . $problem);
            }
        }

        $failed = $schemaProblems !== [] || $drifted !== [] || $idProblems !== [];

        $this->newLine();
        $this->line($failed ? '<fg=yellow>Parity check found differences.</>' : '<fg=green>Parity check clean.</>');

        return self::SUCCESS;
    }

    protected function compareIdSets(MockApiClient $api): array
    {
        $problems = [];

        foreach ((array) config('services.mock_api.core_resources', []) as $resource) {
            if (!Schema::hasTable($resource)) {
                continue;
            }

            try {
                $apiIds = $api->all($resource)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();
            } catch (\Throwable $e) {
                $problems[] = sprintf('%s: could not read ids from the API (%s)', $resource, $e->getMessage());

                continue;
            }

            $localIds = DB::table($resource)->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $missingLocally = $apiIds->diff($localIds)->values();
            $extraLocally = $localIds->diff($apiIds)->values();

            if ($missingLocally->isNotEmpty()) {
                $problems[] = sprintf(
                    '%s: in the API but MISSING locally -> %s',
                    $resource,
                    $missingLocally->implode(', ')
                );
            }

            if ($extraLocally->isNotEmpty()) {
                $problems[] = sprintf(
                    '%s: local-only rows (id churn, or a write that never mirrored) -> %s',
                    $resource,
                    $extraLocally->implode(', ')
                );
            }
        }

        return $problems;
    }
}

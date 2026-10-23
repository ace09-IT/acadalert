<?php

namespace App\Console\Commands;

use App\Exceptions\MockApiException;
use App\Services\Api\MockApiClient;
use Illuminate\Console\Command;

class MockApiProbeCommand extends Command
{
    protected $signature = 'mock-api:probe
        {resource : The collection to read}
        {--expect-failure : Assert the read fails loudly (run with the server stopped)}';

    protected $description = 'Probe one mock API collection, asserting failures are loud and never silent';

    public function handle(MockApiClient $api): int
    {
        $resource = (string) $this->argument('resource');
        $expectFailure = (bool) $this->option('expect-failure');
        $server = $api->isWritable($resource) ? 'core (:3001)' : 'bulk (:3000)';

        $this->newLine();
        $this->line(sprintf('Probing <fg=cyan>%s</> on the <fg=cyan>%s</> server', $resource, $server));
        $this->newLine();

        $rows = null;
        $exception = null;

        try {
            $rows = $api->all($resource);
        } catch (MockApiException $e) {
            $exception = $e;
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($expectFailure) {
            if ($exception === null) {
                $this->error(sprintf(
                    'MISBEHAVED — the read returned %d row(s) with the server stopped. A failure must never be silent.',
                    $rows?->count() ?? 0
                ));

                return self::FAILURE;
            }

            if (!$exception instanceof MockApiException) {
                $this->error(sprintf(
                    'MISBEHAVED — failed with %s instead of MockApiException, so callers cannot catch it deliberately.',
                    get_class($exception)
                ));
                $this->line('  ' . $exception->getMessage());

                return self::FAILURE;
            }

            $this->table(
                ['check', 'result', 'detail'],
                [['failure is loud and typed', '<fg=green>PASS</>', $exception->getMessage()]]
            );
            $this->newLine();
            $this->line('<fg=green>Failure surfaced as a MockApiException — no silent fallback.</>');

            return self::SUCCESS;
        }

        if ($exception !== null) {
            $this->error(sprintf('Read failed with %s: %s', get_class($exception), $exception->getMessage()));

            return self::FAILURE;
        }

        $this->table(
            ['check', 'result', 'detail'],
            [['read succeeds', '<fg=green>PASS</>', sprintf('%d row(s) from %s', $rows->count(), $server)]]
        );

        return self::SUCCESS;
    }
}

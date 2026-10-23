<?php

namespace App\Services\Api;

use App\Exceptions\MockApiException;
use App\Jobs\MirrorApiWriteJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MirrorWriter
{
    protected array $pending = [];

    protected bool $deferring = false;

    public function __construct(protected MockApiClient $api)
    {
    }

    public function defer(): void
    {
        $this->deferring = true;
    }

    public function flush(): void
    {
        $this->deferring = false;

        $queued = $this->pending;
        $this->pending = [];

        foreach ($queued as $operation) {
            $this->send($operation);
        }
    }

    /** How many mirrors are waiting to be flushed. */
    public function pendingCount(): int
    {
        return count($this->pending);
    }

    public function discard(): void
    {
        $this->deferring = false;
        $this->pending = [];
    }

    public function created(string $table, $id, array $attributes): void
    {
        $this->enqueue('create', $table, $id, $attributes);
    }

    public function updated(string $table, $id, array $attributes): void
    {
        $this->enqueue('update', $table, $id, $attributes);
    }

    /** Mirror a completed local DELETE. */
    public function deleted(string $table, $id): void
    {
        $this->enqueue('delete', $table, $id, []);
    }

    public function purged(string $table, array $ids): void
    {
        if (!$this->api->isWritable($table) || $ids === []) {
            return;
        }

        $operation = [
            'op' => 'purge',
            'table' => $table,
            'id' => $ids,
            'attributes' => [],
        ];

        if ($this->deferring) {
            $this->pending[] = $operation;

            return;
        }

        $this->send($operation);
    }

    public function createdFromLocal(string $table, $id): void
    {
        if (!$this->api->isWritable($table)) {
            return;
        }

        $row = DB::table($table)->where('id', $id)->first();

        if ($row !== null) {
            $this->created($table, $id, (array) $row);
        }
    }

    public function updatedFromLocal(string $table, $id): void
    {
        if (!$this->api->isWritable($table)) {
            return;
        }

        $row = DB::table($table)->where('id', $id)->first();

        if ($row !== null) {
            $this->updated($table, $id, (array) $row);
        }
    }

    protected function enqueue(string $op, string $table, $id, array $attributes): void
    {
        if (!$this->api->isWritable($table)) {
            return;
        }

        if ($id === null || $id === '') {
            Log::warning('Mock API mirror skipped: no id to address the remote row', [
                'operation' => $op,
                'collection' => $table,
            ]);

            return;
        }

        $operation = [
            'op' => $op,
            'table' => $table,
            'id' => $id,
            'attributes' => $attributes,
        ];

        if ($this->deferring) {
            $this->pending[] = $operation;

            return;
        }

        $this->send($operation);
    }

    public function send(array $operation): void
    {
        try {
            match ($operation['op']) {
                'create' => $this->api->create($operation['table'], $operation['id'], $operation['attributes']),
                'update' => $this->api->replace($operation['table'], $operation['id'], $operation['attributes']),
                'delete' => $this->api->remove($operation['table'], $operation['id']),
                'purge' => $this->api->removeMany($operation['table'], (array) $operation['id']),
                default => throw new MockApiException("Unknown mirror operation [{$operation['op']}]."),
            };
        } catch (\Throwable $e) {
            $this->reportFailure($operation, $e);
        }
    }

    protected function reportFailure(array $operation, \Throwable $e): void
    {
        Log::error('Mock API mirror write FAILED — local write committed, API copy is stale', [
            'operation' => $operation['op'],
            'collection' => $operation['table'],
            'id' => $operation['id'],
            'error' => $e->getMessage(),
        ]);

        try {
            MirrorApiWriteJob::dispatch($operation);
        } catch (\Throwable $dispatchError) {
            Log::error('Mock API mirror retry could not be queued', [
                'collection' => $operation['table'],
                'id' => $operation['id'],
                'error' => $dispatchError->getMessage(),
            ]);
        }
    }
}

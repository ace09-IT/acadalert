<?php

namespace App\Jobs;

use App\Services\Api\MockApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MirrorApiWriteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(protected array $operation)
    {
    }

    public function handle(MockApiClient $api): void
    {
        $attributes = $this->operation['attributes'] ?? [];

        match ($this->operation['op']) {
            'create' => $api->create($this->operation['table'], $this->operation['id'], $attributes),
            'update' => $api->replace($this->operation['table'], $this->operation['id'], $attributes),
            'delete' => $api->remove($this->operation['table'], $this->operation['id']),
            default => null,
        };
    }

    /** The mirror is still stale after every retry — say so explicitly. */
    public function failed(\Throwable $exception): void
    {
        Log::error('MirrorApiWriteJob exhausted its retries — the mock API copy remains stale', [
            'operation' => $this->operation['op'] ?? null,
            'collection' => $this->operation['table'] ?? null,
            'id' => $this->operation['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}

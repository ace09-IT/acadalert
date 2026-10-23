<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\RiskScoringService;

class ProcessRiskScoringJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $blockId;
    protected $gradingPeriod;
    protected $schoolYear;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(int $blockId, string $gradingPeriod, string $schoolYear)
    {
        $this->blockId = $blockId;
        $this->gradingPeriod = $gradingPeriod;
        $this->schoolYear = $schoolYear;
    }

    public function handle(RiskScoringService $riskService): void
    {
        $result = $riskService->processBlock(
            $this->blockId,
            $this->gradingPeriod,
            $this->schoolYear
        );

        \Illuminate\Support\Facades\Cache::put(
            'risk_scoring_' . $this->blockId . '_' . $this->gradingPeriod,
            $result,
            3600
        );
    }

    public function getResult(): ?array
    {
        return \Illuminate\Support\Facades\Cache::get(
            'risk_scoring_' . $this->blockId . '_' . $this->gradingPeriod
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessRiskScoringJob failed — AI scoring not completed', [
            'block_id' => $this->blockId,
            'grading_period' => $this->gradingPeriod,
            'school_year' => $this->schoolYear,
            'error' => $exception->getMessage(),
        ]);
    }
}
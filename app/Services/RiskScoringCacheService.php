<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RiskScoringCacheService
{
    protected $ttlMinutes;

    public function __construct()
    {
        $this->ttlMinutes = (int) config('services.aistudio.cache_ttl_minutes', 60);
    }

    public function get(int $blockId, string $gradingPeriod, string $schoolYear): ?array
    {
        $row = DB::table('risk_score_cache')
            ->where('block_id', $blockId)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->first();

        if (!$row) {
            return null;
        }

        $createdAt = \Carbon\Carbon::parse($row->created_at);

        if ($createdAt->diffInMinutes(now()) > $this->ttlMinutes) {
            $this->delete($blockId, $gradingPeriod, $schoolYear);
            return null;
        }

        $payload = json_decode($row->payload, true);

        return is_array($payload) ? $payload : null;
    }

    public function put(int $blockId, string $gradingPeriod, string $schoolYear, array $result): void
    {
        DB::table('risk_score_cache')->updateOrInsert(
            [
                'block_id' => $blockId,
                'grading_period' => $gradingPeriod,
                'school_year' => $schoolYear,
            ],
            [
                'payload' => json_encode($result),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function forget(int $blockId, string $gradingPeriod, string $schoolYear): void
    {
        $this->delete($blockId, $gradingPeriod, $schoolYear);
    }

    public function flushAll(): int
    {
        return DB::table('risk_score_cache')->delete();
    }

    public function flushExpired(): int
    {
        return DB::table('risk_score_cache')
            ->where('created_at', '<', now()->subMinutes($this->ttlMinutes))
            ->delete();
    }

    protected function delete(int $blockId, string $gradingPeriod, string $schoolYear): void
    {
        DB::table('risk_score_cache')
            ->where('block_id', $blockId)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();
    }
}
<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Lift THIS request's PHP execution-time ceiling for long, synchronous AI
     * work (risk-scoring a block, generating a block's recommendations).
     *
     * Why this exists: a fresh PHP install ships max_execution_time = 30s, but
     * one block can legitimately run far longer. The risk pipeline pauses 4s
     * between five-student batches, and on Windows that pause is counted as
     * real elapsed time — so a 20-student block (4 batches) is already ~12s of
     * sleep before any AI latency, HTTP round-trips or retries. The original
     * machine had a hand-tuned php.ini; a clean one does not.
     *
     * set_time_limit() is request-scoped by design, so every other page keeps
     * the host's php.ini value — nothing is raised globally. The ceiling is
     * configurable through services.aistudio.max_execution_seconds
     * (AI_STUDIO_MAX_EXECUTION_SECONDS, default 300 = 5 minutes).
     */
    protected function extendAiExecutionTime(): void
    {
        $seconds = (int) config('services.aistudio.max_execution_seconds', 300);

        if ($seconds <= 0) {
            return;
        }

        // set_time_limit() restarts the timer, so calling both is belt-and-
        // braces (ini_set covers a host that has the function disabled). Both
        // are @-suppressed: a hardened host must not turn this guard itself
        // into a fatal error. Under the CLI SAPI set_time_limit() is a no-op,
        // so `php artisan test` and `php artisan serve` are unaffected.
        @set_time_limit($seconds);
        @ini_set('max_execution_time', (string) $seconds);
    }
}

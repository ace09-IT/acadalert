<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reclassify any legacy fallback scores as AI-derived before tightening the enum.
        DB::table('risk_scores')
            ->where('scoring_method', 'fallback')
            ->update(['scoring_method' => 'ai']);

        DB::statement("ALTER TABLE risk_scores MODIFY scoring_method ENUM('ai') NOT NULL DEFAULT 'ai'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE risk_scores MODIFY scoring_method ENUM('ai', 'fallback') NOT NULL DEFAULT 'ai'");
    }
};
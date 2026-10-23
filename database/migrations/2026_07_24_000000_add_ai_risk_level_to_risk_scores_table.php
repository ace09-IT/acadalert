<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {
            $table->string('ai_risk_level', 10)->nullable()->after('risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {
            $table->dropColumn('ai_risk_level');
        });
    }
};
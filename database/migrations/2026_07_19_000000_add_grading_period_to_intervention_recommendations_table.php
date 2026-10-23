<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_recommendations', function (Blueprint $table) {
            $table->string('grading_period', 20)->default('Midterm')->after('student_id');
            $table->string('school_year', 20)->default('2024-2025')->after('grading_period');
        });

        DB::table('intervention_recommendations')
            ->whereNull('grading_period')
            ->update(['grading_period' => 'Midterm', 'school_year' => '2024-2025']);

        Schema::table('intervention_recommendations', function (Blueprint $table) {
            $table->index('grading_period');
            $table->index(['student_id', 'grading_period', 'school_year'], 'recs_student_period_year_index');
        });
    }

    public function down(): void
    {
        Schema::table('intervention_recommendations', function (Blueprint $table) {
            $table->dropIndex(['grading_period']);
            $table->dropIndex('recs_student_period_year_index');
            $table->dropColumn(['grading_period', 'school_year']);
        });
    }
};

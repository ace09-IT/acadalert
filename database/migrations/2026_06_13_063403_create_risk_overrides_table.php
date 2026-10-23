<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('original_risk_score_id')->nullable()->constrained('risk_scores')->onDelete('set null');
            $table->foreignId('overridden_by')->constrained('users')->onDelete('restrict');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->integer('original_risk_score');
            $table->enum('original_risk_level', ['Low', 'Moderate', 'High']);
            $table->enum('new_risk_level', ['Low', 'Moderate', 'High']);
            $table->text('reason');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('original_risk_score_id');
            $table->index('overridden_by');
            $table->index('school_year');
            $table->index('grading_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_overrides');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->integer('risk_score');
            $table->enum('risk_level', ['Low', 'Moderate', 'High']);
            $table->json('risk_factors')->nullable();
            $table->enum('scoring_method', ['ai'])->default('ai');
            $table->text('ai_response_raw')->nullable();
            $table->integer('api_attempt_count')->default(0);
            $table->text('last_api_error')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('grading_period');
            $table->index('risk_level');
            $table->index('risk_score');
            $table->index('school_year');
            $table->index('scoring_method');
            $table->index(['student_id', 'grading_period']);
            $table->index(['grading_period', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->integer('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('risk_scoring_deadline');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            
            $table->index('school_year');
            $table->index('grading_period');
            $table->index('is_active');
            $table->unique(['school_year', 'semester', 'grading_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendar');
    }
};
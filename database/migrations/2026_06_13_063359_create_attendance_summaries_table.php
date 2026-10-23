<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->decimal('total_required_hours', 5, 1);
            $table->decimal('total_weighted_hours', 5, 1);
            $table->decimal('attendance_rate', 5, 2);
            $table->integer('total_absences')->default(0);
            $table->integer('total_lates')->default(0);
            $table->integer('total_excused')->default(0);
            $table->boolean('warning_issued')->default(false);
            $table->boolean('is_dropped')->default(false);
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('subject_id');
            $table->index('grading_period');
            $table->index('school_year');
            $table->index('attendance_rate');
            $table->index(['student_id', 'grading_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('flag_type', ['high_risk', 'consecutive_high_risk', 'low_attendance', 'failing_grade', 'attendance_warning', 'attendance_drop']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('escalated_to_counselor_at')->nullable();
            $table->integer('consecutive_periods_count')->default(0);
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('grading_period');
            $table->index('school_year');
            $table->index('flag_type');
            $table->index('severity');
            $table->index('is_acknowledged');
            $table->index(['student_id', 'grading_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
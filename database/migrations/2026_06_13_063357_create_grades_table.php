<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('restrict');
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->decimal('raw_score', 5, 2);
            $table->string('letter_grade', 2)->nullable();
            $table->decimal('numerical_grade', 5, 2);
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('subject_id');
            $table->index('grading_period');
            $table->index('school_year');
            $table->index('semester');
            $table->index(['student_id', 'grading_period']);
            $table->index(['subject_id', 'grading_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
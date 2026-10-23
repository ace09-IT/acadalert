<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('escalated_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('case_id')->nullable()->constrained()->onDelete('set null');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->text('notes')->nullable();
            $table->timestamp('escalated_at');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('escalated_by');
            $table->index('case_id');
            $table->index('school_year');
            $table->index('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
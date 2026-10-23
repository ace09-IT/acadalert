<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('block_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->timestamps();
            
            $table->index('master_teacher_id');
            $table->index('block_id');
            $table->index('subject_id');
            $table->index('school_year');
            $table->unique(['master_teacher_id', 'block_id', 'subject_id', 'school_year', 'semester'], 'teacher_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
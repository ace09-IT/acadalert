<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->date('session_date');
            $table->enum('status', ['Present', 'Late', 'Absent', 'Excused']);
            $table->decimal('hours_duration', 3, 1);
            $table->decimal('weighted_hours', 3, 1);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('subject_id');
            $table->index('session_date');
            $table->index('status');
            $table->index(['student_id', 'subject_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
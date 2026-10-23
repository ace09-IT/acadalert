<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->enum('warning_type', ['20%_warning', '30%_dropped', '50%_failed']);
            $table->timestamp('issued_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('warning_type');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_warnings');
    }
};
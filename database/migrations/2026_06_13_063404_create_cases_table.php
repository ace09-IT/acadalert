<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('counselor_id')->constrained()->onDelete('restrict');
            $table->foreignId('escalated_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('escalated_at');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('priority', ['Critical', 'High', 'Medium', 'Low'])->default('Medium');
            $table->enum('status', ['New', 'In Progress', 'Awaiting Parent', 'Awaiting Student', 'Referred', 'Resolved', 'Closed', 'Reopened'])->default('New');
            $table->enum('risk_level_at_escalation', ['High', 'Moderate', 'Low']);
            $table->integer('risk_score_at_escalation');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolved_reason')->nullable();
            $table->foreignId('reopened_from_case_id')->nullable()->constrained('cases')->onDelete('set null');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('counselor_id');
            $table->index('priority');
            $table->index('status');
            $table->index('school_year');
            $table->index('escalated_at');
            $table->index(['counselor_id', 'status']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
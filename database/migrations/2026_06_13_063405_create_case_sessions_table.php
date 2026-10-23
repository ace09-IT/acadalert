<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->date('session_date');
            $table->enum('session_type', ['In-person', 'Phone', 'Virtual', 'Parent Meeting']);
            $table->text('notes');
            $table->text('action_taken')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->enum('status_after_session', ['New', 'In Progress', 'Awaiting Parent', 'Awaiting Student', 'Referred', 'Resolved', 'Closed', 'Reopened'])->nullable();
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('session_date');
            $table->index('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_sessions');
    }
};
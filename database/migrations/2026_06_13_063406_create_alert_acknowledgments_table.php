<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('flag_id')->constrained()->onDelete('cascade');
            $table->timestamp('acknowledged_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('flag_id');
            $table->index('acknowledged_at');
            $table->unique(['student_id', 'flag_id'], 'alert_ack_student_flag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_acknowledgments');
    }
};
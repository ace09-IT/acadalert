<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_thresholds', function (Blueprint $table) {
            $table->id();
            $table->integer('low_threshold')->default(40);
            $table->integer('moderate_threshold')->default(70);
            $table->integer('high_threshold')->default(71);
            $table->decimal('grade_weight', 3, 2)->default(0.60);
            $table->decimal('attendance_weight', 3, 2)->default(0.40);
            $table->foreignId('updated_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_thresholds');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->json('risk_factors');
            $table->json('suggested_actions');
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_recommendations');
    }
};
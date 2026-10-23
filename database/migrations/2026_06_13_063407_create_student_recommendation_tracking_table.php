<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_recommendation_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('recommendation_id')->constrained('intervention_recommendations')->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('recommendation_id');
            $table->index('is_completed');
            
            $table->unique(['student_id', 'recommendation_id'], 'student_rec_tracking_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_recommendation_tracking');
    }
};
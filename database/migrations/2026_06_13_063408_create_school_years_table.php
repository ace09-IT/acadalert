<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 9)->unique();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
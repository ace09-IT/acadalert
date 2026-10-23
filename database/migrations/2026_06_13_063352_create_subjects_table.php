<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->integer('year_level');
            $table->integer('semester');
            $table->string('subject_code', 20);
            $table->string('subject_name', 200);
            $table->integer('units')->default(3);
            $table->timestamps();
            
            $table->index('program_id');
            $table->index('subject_code');
            $table->index(['year_level', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_level_id')->constrained()->onDelete('cascade');
            $table->integer('block_number');
            $table->string('name', 10);
            $table->integer('max_students')->default(20);
            $table->timestamps();
            
            $table->index('year_level_id');
            $table->index('block_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
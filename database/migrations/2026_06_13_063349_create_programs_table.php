<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->onDelete('restrict');
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->integer('total_students')->default(160);
            $table->timestamps();
            
            $table->index('code');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('restrict');
            $table->string('employee_number', 20)->unique();
            $table->string('specialization', 100)->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('department_id');
            $table->index('employee_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_teachers');
    }
};
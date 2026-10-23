<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->onDelete('restrict');
            $table->string('student_number', 30)->unique();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->year('year_enrolled');
            $table->timestamps();
            
            $table->index('student_number');
            $table->index('email');
            $table->index('block_id');
            $table->index('last_name');
            $table->index('status');
            $table->index('year_enrolled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
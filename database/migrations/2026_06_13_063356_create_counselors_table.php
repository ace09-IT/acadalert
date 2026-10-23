<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->string('employee_number', 20)->unique();
            $table->string('specialization', 100)->nullable();
            $table->integer('max_caseload')->default(30);
            $table->string('office_location', 200)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('office_hours', 200)->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('department_id');
            $table->index('employee_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselors');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('full_name', 100);
            $table->enum('relationship', ['Mother', 'Father', 'Guardian']);
            $table->string('contact_number', 20);
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('lives_with_student')->default(true);
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('relationship');
            $table->index('is_primary_contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
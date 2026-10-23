<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['admin', 'master_teacher', 'guidance_counselor', 'student']);
            $table->string('permission', 100);
            $table->boolean('is_allowed')->default(false);
            $table->timestamps();
            
            $table->index('role');
            $table->index('permission');
            $table->unique(['role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
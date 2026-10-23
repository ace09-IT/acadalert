<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_of_term_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd']);
            $table->enum('grading_period', ['Prelim', 'Midterm', 'Finals']);
            $table->unsignedTinyInteger('period_number');

            // Denormalised headline figures so list screens never need to decode JSON.
            $table->unsignedInteger('total_monitored')->default(0);
            $table->unsignedInteger('low_count')->default(0);
            $table->unsignedInteger('moderate_count')->default(0);
            $table->unsignedInteger('high_count')->default(0);
            $table->decimal('low_percentage', 5, 2)->default(0);
            $table->decimal('moderate_percentage', 5, 2)->default(0);
            $table->decimal('high_percentage', 5, 2)->default(0);

            $table->enum('previous_grading_period', ['Prelim', 'Midterm', 'Finals'])->nullable();

            $table->longText('snapshot');
            $table->char('digest', 64);

            $table->foreignId('generated_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('generated_at');

            $table->boolean('is_shared')->default(false);
            $table->json('shared_roles')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            $table->unique(['department_id', 'school_year', 'semester', 'grading_period'], 'eot_reports_unique_period');
            $table->index(['department_id', 'grading_period']);
            $table->index(['is_shared', 'department_id']);
            $table->index('generated_at');
            $table->index('digest');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_term_reports');
    }
};
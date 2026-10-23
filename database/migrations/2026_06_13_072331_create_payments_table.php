<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('school_year', 9);
            $table->enum('semester', ['1st', '2nd', 'Summer']);
            $table->string('fee_type', 50);
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->storedAs('amount - paid_amount');
            $table->date('due_date');
            $table->date('last_payment_date')->nullable();
            $table->enum('status', ['Paid', 'Unpaid', 'Overdue', 'Partial'])->default('Unpaid');
            $table->foreignId('updated_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('school_year');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
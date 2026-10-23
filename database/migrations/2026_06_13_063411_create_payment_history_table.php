<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->enum('action', ['Created', 'Updated', 'Payment Received', 'Status Changed']);
            $table->enum('old_status', ['Paid', 'Unpaid', 'Overdue', 'Partial'])->nullable();
            $table->enum('new_status', ['Paid', 'Unpaid', 'Overdue', 'Partial']);
            $table->decimal('amount_changed', 10, 2)->default(0);
            $table->unsignedBigInteger('performed_by');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('payment_id');
            $table->index('performed_by');
            $table->index('created_at');
            
            // Foreign keys with SET NULL instead of CASCADE
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_history');
    }
};
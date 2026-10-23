<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentHistoryTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('payment_history')->truncate();
        
        $payments = DB::table('payments')->where('paid_amount', '>', 0)->get();
        $historyCount = 0;
        
        foreach ($payments as $payment) {
            DB::table('payment_history')->insert([
                'payment_id' => $payment->id,
                'action' => 'Payment Received',
                'old_status' => 'Unpaid',
                'new_status' => $payment->status,
                'amount_changed' => $payment->paid_amount,
                'performed_by' => 1,
                'notes' => 'Payment recorded',
                'created_at' => $payment->last_payment_date ?? now(),
                'updated_at' => now(),
            ]);
            $historyCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$historyCount payment history records seeded successfully.");
    }
}
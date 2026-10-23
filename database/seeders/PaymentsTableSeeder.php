<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('payments')->truncate();
        
        $students = DB::table('students')->get();
        $paymentStatuses = [
            ['status' => 'Paid', 'weight' => 65],
            ['status' => 'Partial', 'weight' => 15],
            ['status' => 'Unpaid', 'weight' => 10],
            ['status' => 'Overdue', 'weight' => 10],
        ];
        
        $paymentCount = 0;
        
        foreach ($students as $student) {
            $status = $this->getRandomStatus($paymentStatuses);
            $amount = rand(20000, 35000);
            $paidAmount = $status === 'Paid' ? $amount : ($status === 'Partial' ? rand(5000, $amount - 5000) : 0);
            $dueDate = $status === 'Overdue' ? now()->subDays(rand(1, 30)) : now()->addDays(rand(1, 60));
            
            DB::table('payments')->insert([
                'student_id' => $student->id,
                'school_year' => '2024-2025',
                'semester' => '1st',
                'fee_type' => 'Tuition',
                'amount' => $amount,
                'paid_amount' => $paidAmount,
                'due_date' => $dueDate,
                'last_payment_date' => $paidAmount > 0 ? now() : null,
                'status' => $status,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $paymentCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$paymentCount payments seeded successfully.");
    }
    
    private function getRandomStatus(array $statuses): string
    {
        $totalWeight = array_sum(array_column($statuses, 'weight'));
        $random = rand(1, $totalWeight);
        $cumulative = 0;
        
        foreach ($statuses as $status) {
            $cumulative += $status['weight'];
            if ($random <= $cumulative) {
                return $status['status'];
            }
        }
        
        return 'Unpaid';
    }
}
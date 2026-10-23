<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogsTableSeeder extends Seeder
{
    private $actions = ['USER_LOGIN', 'USER_CREATED', 'AI_RISK_RUN', 'CASE_CREATED', 'CASE_UPDATED', 'PAYMENT_UPDATED'];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('audit_logs')->truncate();
        
        $users = DB::table('users')->get();
        $logCount = 0;
        
        for ($i = 1; $i <= 500; $i++) {
            $user = $users->random();
            
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'action' => $this->actions[array_rand($this->actions)],
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
                'ip_address' => '192.168.1.' . rand(1, 255),
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now(),
            ]);
            $logCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$logCount audit logs seeded successfully.");
    }
}
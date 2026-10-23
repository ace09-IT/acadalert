<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskThresholdsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('risk_thresholds')->truncate();
        Schema::enableForeignKeyConstraints();
        
        $admin = DB::table('users')->where('role', 'admin')->first();
        $updatedBy = $admin ? $admin->id : 1;
        
        DB::table('risk_thresholds')->insert([
            'low_threshold' => 40,
            'moderate_threshold' => 70,
            'high_threshold' => 71,
            'grade_weight' => 0.60,
            'attendance_weight' => 0.40,
            'updated_by' => $updatedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->command->info('Risk thresholds seeded successfully.');
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceWarningsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('attendance_warnings')->truncate();
        Schema::enableForeignKeyConstraints();
        
        $summaries = DB::table('attendance_summaries')
            ->where('warning_issued', true)
            ->orWhere('is_dropped', true)
            ->get();
        
        $warningCount = 0;
        
        foreach ($summaries as $summary) {
            $warningType = $this->determineWarningType($summary);
            if (!$warningType) {
                continue;
            }
            
            DB::table('attendance_warnings')->insert([
                'student_id' => $summary->student_id,
                'subject_id' => $summary->subject_id,
                'warning_type' => $warningType,
                'issued_at' => now()->subDays(rand(1, 30)),
                'acknowledged_by' => null,
                'acknowledged_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $warningCount++;
        }
        
        $this->command->info("$warningCount attendance warnings seeded successfully.");
    }
    
    private function determineWarningType($summary): ?string
    {
        $rate = $summary->attendance_rate;
        
        if ($rate < 50 && $summary->is_dropped) {
            return '50%_failed';
        } elseif ($rate < 70 && $summary->is_dropped) {
            return '30%_dropped';
        } elseif ($rate < 80 && $summary->warning_issued) {
            return '20%_warning';
        }
        
        return null;
    }
}
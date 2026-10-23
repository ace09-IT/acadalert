<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FlagsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('flags')->truncate();
        
        $riskScores = DB::table('risk_scores')->where('risk_level', 'High')->get();
        $flagCount = 0;
        
        foreach ($riskScores as $risk) {
            $prevRisk = DB::table('risk_scores')
                ->where('student_id', $risk->student_id)
                ->where('grading_period', $this->getPreviousPeriod($risk->grading_period))
                ->first();
            
            $isConsecutive = $prevRisk && $prevRisk->risk_level === 'High';
            
            DB::table('flags')->insert([
                'student_id' => $risk->student_id,
                'grading_period' => $risk->grading_period,
                'school_year' => $risk->school_year,
                'semester' => $risk->semester,
                'flag_type' => $isConsecutive ? 'consecutive_high_risk' : 'high_risk',
                'severity' => $isConsecutive ? 'critical' : 'high',
                'is_acknowledged' => false,
                'consecutive_periods_count' => $isConsecutive ? 2 : 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $flagCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$flagCount flags seeded successfully.");
    }
    
    private function getPreviousPeriod(string $current): string
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : '';
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskScoresTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('risk_scores')->truncate();
        
        $students = DB::table('students')->get();
        $gradingPeriods = ['Prelim', 'Midterm', 'Finals'];
        
        $riskCount = 0;
        
        foreach ($students as $student) {
            $grades = DB::table('grades')->where('student_id', $student->id)->get();
            $attendance = DB::table('attendance_summaries')->where('student_id', $student->id)->get();
            
            foreach ($gradingPeriods as $period) {
                $periodGrades = $grades->where('grading_period', $period);
                $avgGrade = $periodGrades->avg('numerical_grade') ?? 75;
                $failingSubjects = $periodGrades->where('numerical_grade', '<', 75)->count();

                $periodAttendance = $attendance->where('grading_period', $period)->first();
                $attendanceRate = $periodAttendance->attendance_rate ?? 85;

                $riskScore = round(($avgGrade * 0.6) + ($attendanceRate * 0.4));

                if ($riskScore >= 71) {
                    $riskLevel = 'High';
                } elseif ($riskScore >= 41) {
                    $riskLevel = 'Moderate';
                } else {
                    $riskLevel = 'Low';
                }

                $riskFactors = [];
                if ($failingSubjects > 0) {
                    $riskFactors[] = "Failing {$failingSubjects} subject(s) this period (below 75)";
                }
                if ($avgGrade < 75) {
                    $riskFactors[] = 'Average grade below the passing threshold (' . round($avgGrade, 2) . ')';
                }
                if ($attendanceRate < 85) {
                    $riskFactors[] = 'Attendance rate below 85% (' . $attendanceRate . '%)';
                }
                if (empty($riskFactors) && $riskLevel !== 'Low') {
                    $riskFactors[] = 'Borderline academic performance for this grading period';
                }

                DB::table('risk_scores')->insert([
                    'student_id' => $student->id,
                    'grading_period' => $period,
                    'school_year' => '2024-2025',
                    'semester' => '1st',
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'risk_factors' => json_encode(array_values($riskFactors)),
                    'scoring_method' => 'ai',
                    'processing_status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $riskCount++;
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$riskCount risk scores seeded successfully.");
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceTableSeeder extends Seeder
{
    private $attendanceDistribution = [
        ['min' => 95, 'max' => 100, 'weight' => 50],
        ['min' => 85, 'max' => 94, 'weight' => 25],
        ['min' => 75, 'max' => 84, 'weight' => 15],
        ['min' => 60, 'max' => 74, 'weight' => 7],
        ['min' => 0, 'max' => 59, 'weight' => 3],
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('attendance')->truncate();
        DB::table('attendance_summaries')->truncate();
        Schema::enableForeignKeyConstraints();
        
        $students = DB::table('students')->get();
        $subjects = DB::table('subjects')->get();
        $gradingPeriods = ['Prelim', 'Midterm', 'Finals'];
        $sessionsPerPeriod = 15;
        $hoursPerSession = 3;
        
        $attendanceCount = 0;
        $summaryCount = 0;
        
        $admin = DB::table('users')->where('role', 'admin')->first();
        $recordedBy = $admin ? $admin->id : 1;
        
        foreach ($students as $student) {
            $block = DB::table('blocks')->where('id', $student->block_id)->first();
            $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
            $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
            
            $studentSubjects = DB::table('subjects')
                ->where('program_id', $program->id)
                ->where('year_level', $yearLevel->year_number)
                ->get();
            
            foreach ($studentSubjects as $subject) {
                $attendanceRate = $this->generateAttendanceRate();
                
                $totalRequiredHours = $sessionsPerPeriod * $hoursPerSession;
                $totalWeightedHours = ($attendanceRate / 100) * $totalRequiredHours;
                
                $totalAbsences = round(($sessionsPerPeriod * (100 - $attendanceRate) / 100) / 2);
                $totalLates = rand(0, 5);
                $warningIssued = $attendanceRate < 80;
                $isDropped = $attendanceRate < 60;
                
                foreach ($gradingPeriods as $period) {
                    DB::table('attendance_summaries')->insert([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'grading_period' => $period,
                        'school_year' => '2024-2025',
                        'semester' => '1st',
                        'total_required_hours' => $totalRequiredHours,
                        'total_weighted_hours' => $totalWeightedHours,
                        'attendance_rate' => $attendanceRate,
                        'total_absences' => $totalAbsences,
                        'total_lates' => $totalLates,
                        'total_excused' => 0,
                        'warning_issued' => $warningIssued,
                        'is_dropped' => $isDropped,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $summaryCount++;
                }
                
                $sessionsToGenerate = rand(10, 15);
                for ($s = 1; $s <= $sessionsToGenerate; $s++) {
                    $status = $this->generateAttendanceStatus($attendanceRate);
                    $hours = $hoursPerSession;
                    $weightedHours = $this->calculateWeightedHours($status, $hours);
                    
                    DB::table('attendance')->insert([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'session_date' => now()->subDays(rand(1, 60)),
                        'status' => $status,
                        'hours_duration' => $hours,
                        'weighted_hours' => $weightedHours,
                        'remarks' => $status === 'Late' ? 'Arrived late' : ($status === 'Absent' ? 'No show' : null),
                        'recorded_by' => $recordedBy,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $attendanceCount++;
                }
            }
        }
        
        $this->command->info("$attendanceCount detailed attendance records seeded successfully.");
        $this->command->info("$summaryCount attendance summaries seeded successfully.");
    }
    
    private function generateAttendanceRate(): int
    {
        $totalWeight = array_sum(array_column($this->attendanceDistribution, 'weight'));
        $random = rand(1, $totalWeight);
        $cumulative = 0;
        
        foreach ($this->attendanceDistribution as $range) {
            $cumulative += $range['weight'];
            if ($random <= $cumulative) {
                return rand($range['min'], $range['max']);
            }
        }
        return rand(85, 95);
    }
    
    private function generateAttendanceStatus(int $rate): string
    {
        $random = rand(1, 100);
        if ($random <= $rate) {
            return 'Present';
        } elseif ($random <= $rate + 5) {
            return 'Late';
        } elseif ($random <= $rate + 10) {
            return 'Excused';
        } else {
            return 'Absent';
        }
    }
    
    private function calculateWeightedHours(string $status, float $hours): float
    {
        return match ($status) {
            'Present' => $hours,
            'Late' => $hours * 0.5,
            'Excused' => $hours,
            'Absent' => 0.0,
            default => 0.0,
        };
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GradesTableSeeder extends Seeder
{
    private $gradeDistribution = [
        ['min' => 90, 'max' => 100, 'weight' => 15],
        ['min' => 85, 'max' => 89, 'weight' => 20],
        ['min' => 80, 'max' => 84, 'weight' => 20],
        ['min' => 75, 'max' => 79, 'weight' => 25],
        ['min' => 70, 'max' => 74, 'weight' => 12],
        ['min' => 0, 'max' => 69, 'weight' => 8],
    ];
    
    private $letterGrades = [
        90 => 'A', 85 => 'B+', 80 => 'B', 75 => 'C', 70 => 'D', 0 => 'F'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('grades')->truncate();
        
        $students = DB::table('students')->get();
        $subjects = DB::table('subjects')->get();
        $gradingPeriods = ['Prelim', 'Midterm', 'Finals'];
        
        $gradeCount = 0;
        
        foreach ($students as $student) {
            $block = DB::table('blocks')->where('id', $student->block_id)->first();
            $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
            
            $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
            $studentSubjects = DB::table('subjects')
                ->where('program_id', $program->id)
                ->where('year_level', $yearLevel->year_number)
                ->get();
            
            foreach ($studentSubjects as $subject) {
                foreach ($gradingPeriods as $period) {
                    $grade = $this->generateGrade();
                    $letterGrade = $this->getLetterGrade($grade);
                    
                    DB::table('grades')->insert([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'grading_period' => $period,
                        'school_year' => '2024-2025',
                        'semester' => '1st',
                        'raw_score' => $grade,
                        'letter_grade' => $letterGrade,
                        'numerical_grade' => $grade,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $gradeCount++;
                }
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$gradeCount grades seeded successfully.");
    }
    
    private function generateGrade(): int
    {
        $totalWeight = 0;
        foreach ($this->gradeDistribution as $range) {
            $totalWeight += $range['weight'];
        }
        
        $random = rand(1, $totalWeight);
        $cumulative = 0;
        
        foreach ($this->gradeDistribution as $range) {
            $cumulative += $range['weight'];
            if ($random <= $cumulative) {
                return rand($range['min'], $range['max']);
            }
        }
        
        return rand(75, 85); // default fallback
    }
    
    private function getLetterGrade(int $grade): string
    {
        if ($grade >= 90) return 'A';
        if ($grade >= 85) return 'B+';
        if ($grade >= 80) return 'B';
        if ($grade >= 75) return 'C';
        if ($grade >= 70) return 'D';
        return 'F';
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InterventionRecommendationsTableSeeder extends Seeder
{
    private $recommendations = [
        'Attend tutoring sessions for struggling subjects',
        'Schedule a meeting with your guidance counselor',
        'Improve attendance - attend all classes this week',
        'Submit missing assignments before deadline',
        'Join a study group for collaborative learning',
        'Meet with your subject teacher for additional help',
        'Create a study schedule and stick to it',
        'Take advantage of online learning resources',
        'Attend academic support workshops',
        'Schedule a parent-teacher conference'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('intervention_recommendations')->truncate();
        Schema::enableForeignKeyConstraints();

        // Seed PER PERIOD so the dashboard's categorical filter has real,
        // non-blended data for Prelim / Midterm / Finals.
        $gradingPeriods = ['Prelim', 'Midterm', 'Finals'];
        $schoolYear = '2024-2025';

        $recCount = 0;

        foreach ($gradingPeriods as $period) {
            // Only students flagged High risk IN THIS PERIOD get recommendations.
            $highRiskStudents = DB::table('risk_scores')
                ->where('risk_level', 'High')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->select('student_id', 'risk_factors')
                ->get();

            foreach ($highRiskStudents as $student) {
                $riskFactors = json_decode($student->risk_factors ?? '', true);
                if (!is_array($riskFactors) || empty($riskFactors)) {
                    $riskFactors = ['Low grades in major subjects', 'Poor attendance record'];
                }

                $suggestedActions = array_values(array_rand(array_flip($this->recommendations), 3));

                DB::table('intervention_recommendations')->insert([
                    'student_id' => $student->student_id,
                    'grading_period' => $period,
                    'school_year' => $schoolYear,
                    'risk_factors' => json_encode(array_values($riskFactors)),
                    'suggested_actions' => json_encode($suggestedActions),
                    'generated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $recCount++;
            }
        }

        $this->command->info("$recCount period-scoped intervention recommendations seeded successfully.");
    }
}
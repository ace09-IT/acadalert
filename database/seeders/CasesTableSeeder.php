<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CasesTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('cases')->truncate();
        
        $flags = DB::table('flags')
            ->where('severity', 'critical')
            ->orWhere('severity', 'high')
            ->get();
        
        $counselors = DB::table('counselors')->get();
        
        $caseCount = 0;
        
        foreach ($flags as $flag) {
            $counselor = $counselors->random();
            $riskScore = DB::table('risk_scores')
                ->where('student_id', $flag->student_id)
                ->where('grading_period', $flag->grading_period)
                ->first();

            $recommendation = DB::table('intervention_recommendations')
                ->where('student_id', $flag->student_id)
                ->where('grading_period', $flag->grading_period)
                ->where('school_year', '2024-2025')
                ->orderByDesc('generated_at')
                ->first();

            $forwardedText = null;

            if ($recommendation !== null) {
                $forwardedText = trim(app(\App\Services\RecommendationTextService::class)
                    ->toText($recommendation->suggested_actions));
                $forwardedText = $forwardedText === '' ? null : $forwardedText;
            }

            DB::table('cases')->insert([
                'student_id' => $flag->student_id,
                'counselor_id' => $counselor->id,
                'escalated_by' => 2, // Assume first academic head
                'escalated_at' => now(),
                'school_year' => '2024-2025',
                'semester' => '1st',
                'priority' => $flag->severity === 'critical' ? 'Critical' : 'High',
                'status' => 'New',
                'risk_level_at_escalation' => 'High',
                'risk_score_at_escalation' => $riskScore->risk_score ?? 85,
                // Every other seeded case shows the "no recommendation provided"
                // path, so the counselor view is exercised both ways.
                'intervention_recommendation' => $caseCount % 2 === 0 ? $forwardedText : null,
                'intervention_included' => $caseCount % 2 === 0 && $forwardedText !== null,
                'intervention_edited' => false,
                'intervention_source_id' => ($caseCount % 2 === 0 && $forwardedText !== null)
                    ? $recommendation->id
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $caseCount++;

            if ($recommendation !== null) {
                $escalation = DB::table('escalations')
                    ->where('student_id', $flag->student_id)
                    ->orderByDesc('id')
                    ->first();

                if ($escalation !== null) {
                    DB::table('escalations')
                        ->where('id', $escalation->id)
                        ->update(['intervention_recommendation_id' => $recommendation->id]);
                }
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$caseCount cases seeded successfully.");
    }
}
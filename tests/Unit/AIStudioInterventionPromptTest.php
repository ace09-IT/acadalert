<?php

namespace Tests\Unit;

use App\Services\AIStudioApiService;
use Tests\TestCase;

class AIStudioInterventionPromptTest extends TestCase
{
    protected function prompt(): string
    {
        $service = app(AIStudioApiService::class);
        $method = new \ReflectionMethod($service, 'buildInterventionPrompt');
        $method->setAccessible(true);

        return $method->invoke($service, [
            'id' => 168,
            'name' => 'Wendy Santiago',
            'program' => 'BSIT',
            'risk_level' => 'High',
            'risk_factors' => ['Failing grades in 5 subjects', 'Attendance below 90%'],
            'subjects' => [
                ['subject_code' => 'CSC106', 'subject_name' => 'Database', 'numerical_grade' => 25],
                ['subject_code' => 'CSC102', 'subject_name' => 'Computer Systems', 'numerical_grade' => 38],
            ],
            'total_subjects' => 8,
            'failing_subjects' => 5,
            'avg_grade' => 65.38,
            'attendance_rate' => 97.06,
        ], ['Failing grades in 5 subjects']);
    }

    public function test_it_caps_the_number_of_actions(): void
    {
        $this->assertStringContainsString('Return 1 to 3 actions', $this->prompt());
        $this->assertStringContainsString('never more than three', $this->prompt());
    }

    public function test_it_sets_a_one_sentence_length_budget(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('One sentence per action', $prompt);
        $this->assertStringContainsString('at most 90 characters', $prompt);
        $this->assertStringContainsString('imperative verb', $prompt);
    }

    public function test_it_forbids_justifying_or_restating_the_metrics(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('Do NOT explain, justify or repeat', $prompt);
        $this->assertStringContainsString('"because"', $prompt);
        $this->assertStringContainsString('"due to"', $prompt);
    }

    public function test_it_collapses_the_tutoring_synonyms_before_the_model_picks_one(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('Never recommend the same kind of help twice', $prompt);
        $this->assertStringContainsString('coaching and academic advising are ONE action', $prompt);
    }

    public function test_it_lists_only_the_six_canonical_actions(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString(
            'Available actions: tutoring, counseling, study_skills, attendance_monitoring, parent_meeting, career_guidance',
            $prompt
        );

        foreach (['academic_advising', 'academic_coaching', 'peer_tutoring', 'time_management', 'stress_management', 'workshop'] as $retired) {
            $this->assertStringNotContainsString('Available actions: ' . $retired, $prompt);
        }
    }

    public function test_every_action_carries_a_trigger_condition(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('at least one subject below 75', $prompt);
        $this->assertStringContainsString('risk level High, or two or more subjects below 75', $prompt);
        $this->assertStringContainsString('average grade below 80', $prompt);
        $this->assertStringContainsString('attendance rate below 85%', $prompt);
        $this->assertStringContainsString('risk level High, or three or more subjects below 75', $prompt);
        $this->assertStringContainsString('risk level Low with no subject below 75', $prompt);
    }

    public function test_it_caps_the_high_priority_band(): void
    {
        $this->assertStringContainsString('at most two actions are high', $this->prompt());
    }

    public function test_it_still_carries_every_student_field_and_the_subject_grades(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('Student: Wendy Santiago', $prompt);
        $this->assertStringContainsString('Program: BSIT', $prompt);
        $this->assertStringContainsString('Risk Level: High', $prompt);
        $this->assertStringContainsString('Average Grade: 65.38%', $prompt);
        $this->assertStringContainsString('Subjects Failing (below 75): 5', $prompt);
        $this->assertStringContainsString('Attendance Rate: 97.06%', $prompt);
        $this->assertStringContainsString('Subject 1 (CSC106) Database: 25', $prompt);
        $this->assertStringContainsString('Subject 2 (CSC102) Computer Systems: 38', $prompt);
    }

    public function test_it_still_requests_the_json_shape_the_parser_reads(): void
    {
        $prompt = $this->prompt();

        $this->assertStringContainsString('"recommendations"', $prompt);
        $this->assertStringContainsString('"action"', $prompt);
        $this->assertStringContainsString('"details"', $prompt);
        $this->assertStringContainsString('"priority"', $prompt);
    }
}

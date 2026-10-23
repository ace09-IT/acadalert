<?php

namespace Tests\Unit;

use App\Services\AIStudioApiService;
use App\Services\RiskThresholdService;
use Tests\TestCase;

class AIStudioRiskPromptTest extends TestCase
{
    protected function student(): array
    {
        return [
            'id' => 7,
            'name' => 'Juan Dela Cruz',
            'student_number' => 'UDD-2024-001',
            'subjects' => [
                ['subject_code' => 'IT101', 'subject_name' => 'Computing', 'numerical_grade' => 68.5],
            ],
            'avg_grade' => 68.5,
            'total_subjects' => 1,
            'failing_subjects' => 1,
            'attendance_rate' => 72.0,
            'absences' => 4,
            'lates' => 2,
            'previous_risk' => 'Moderate',
            'program' => 'BSIT',
        ];
    }

    protected function batchPrompt(array $bands = []): string
    {
        $service = app(AIStudioApiService::class);
        $method = new \ReflectionMethod($service, 'buildBatchRiskPrompt');
        $method->setAccessible(true);

        return $method->invoke($service, [$this->student()], $bands);
    }

    protected function singlePrompt(array $bands = []): string
    {
        $service = app(AIStudioApiService::class);
        $method = new \ReflectionMethod($service, 'buildRiskPrompt');
        $method->setAccessible(true);

        return $method->invoke($service, $this->student(), $bands);
    }

    public function test_the_batch_prompt_carries_the_configured_bands(): void
    {
        $prompt = $this->batchPrompt(['low_threshold' => 30, 'moderate_threshold' => 60, 'high_threshold' => 61]);

        $this->assertStringContainsString('- Low: 0-30', $prompt);
        $this->assertStringContainsString('- Moderate: 31-60', $prompt);
        $this->assertStringContainsString('- High: 61-100', $prompt);
        $this->assertStringNotContainsString('- Low: 0-40', $prompt);
        $this->assertStringNotContainsString('- High: 71-100', $prompt);
    }

    public function test_the_default_batch_prompt_renders_the_legacy_guidelines(): void
    {
        $prompt = $this->batchPrompt();

        $this->assertStringContainsString('- Low: 0-40', $prompt);
        $this->assertStringContainsString('- Moderate: 41-70', $prompt);
        $this->assertStringContainsString('- High: 71-100', $prompt);
        $this->assertStringContainsString('- High: 71-100', $this->batchPrompt(RiskThresholdService::DEFAULTS));
    }

    public function test_both_prompts_state_that_the_system_assigns_the_final_level(): void
    {
        $this->assertStringContainsString('assigned deterministically', $this->batchPrompt());
        $this->assertStringContainsString('assigned deterministically', $this->singlePrompt());
    }

    public function test_the_single_student_prompt_carries_the_configured_bands(): void
    {
        $prompt = $this->singlePrompt(['low_threshold' => 10, 'moderate_threshold' => 50, 'high_threshold' => 51]);

        $this->assertStringContainsString('- Low: 0-10', $prompt);
        $this->assertStringContainsString('- Moderate: 11-50', $prompt);
        $this->assertStringContainsString('- High: 51-100', $prompt);
    }

    public function test_the_prompt_still_carries_every_student_field_and_subject_grade(): void
    {
        $prompt = $this->batchPrompt();

        $this->assertStringContainsString('student_id: 7', $prompt);
        $this->assertStringContainsString('avg_grade: 68.5', $prompt);
        $this->assertStringContainsString('IT101 Computing: 68.5', $prompt);
        $this->assertStringContainsString('attendance_rate: 72', $prompt);
        $this->assertStringContainsString('previous_risk: Moderate', $prompt);
    }
}
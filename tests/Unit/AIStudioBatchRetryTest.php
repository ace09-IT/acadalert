<?php

namespace Tests\Unit;

use App\Exceptions\AIStudioApiException;
use App\Services\AIStudioApiService;
use Tests\TestCase;

class AIStudioBatchRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The retry keeps the free-tier cadence, which would otherwise cost four real
        // seconds per assertion.
        config(['services.aistudio.batch_delay_seconds' => 0]);
    }

    protected function service(array $responses, int $retryLimit): AIStudioApiService
    {
        config(['services.aistudio.batch_retry_limit' => $retryLimit]);

        return new class($responses) extends AIStudioApiService {
            public array $prompts = [];

            protected array $queued;

            public function __construct(array $responses)
            {
                parent::__construct();

                $this->queued = array_values($responses);
            }

            protected function sendRequest(string $prompt, int $attempt = 1): string
            {
                $this->prompts[] = $prompt;

                return array_shift($this->queued) ?? '[]';
            }
        };
    }

    protected function batch(array $ids): array
    {
        $students = [];

        foreach ($ids as $id) {
            $students[] = [
                'id' => $id,
                'name' => 'Student '.$id,
                'avg_grade' => 70.0,
                'total_subjects' => 1,
                'failing_subjects' => 1,
                'attendance_rate' => 80.0,
                'absences' => 1,
                'lates' => 0,
                'previous_risk' => 'Low',
                'subjects' => [
                    ['subject_code' => 'IT101', 'subject_name' => 'Computing', 'numerical_grade' => 70.0],
                ],
            ];
        }

        return $students;
    }

    protected function answer(int $id, int $score = 80): string
    {
        return '[{"student_id":'.$id.',"risk_score":'.$score.',"risk_level":"High",'
            .'"risk_factors":["Low grades"],"explanation":"Test double."}]';
    }

    public function test_a_recovered_omission_returns_the_complete_batch(): void
    {
        // First answer skips 68; the retry answers only 68.
        $service = $this->service([$this->answer(67), $this->answer(68)], 2);

        $scores = $service->getRiskScoresForBatch($this->batch([67, 68]));

        $this->assertCount(2, $scores, 'both students must be scored once the omission is recovered');
        $this->assertArrayHasKey(67, $scores);
        $this->assertArrayHasKey(68, $scores);
        $this->assertCount(2, $service->prompts, 'exactly one retry should have been sent');
    }

    public function test_the_retry_re_asks_only_the_omitted_students(): void
    {
        $service = $this->service([$this->answer(67), $this->answer(68)], 2);

        $service->getRiskScoresForBatch($this->batch([67, 68]));

        $retryPrompt = $service->prompts[1];

        $this->assertStringContainsString('student_id: 68', $retryPrompt);
        $this->assertStringNotContainsString(
            'student_id: 67',
            $retryPrompt,
            'the student already answered must not be asked for twice'
        );
    }

    public function test_partial_recovery_across_two_retries(): void
    {
        // 66 answered first; 67 on retry 1; 68 only on retry 2.
        $service = $this->service([$this->answer(66), $this->answer(67), $this->answer(68)], 2);

        $scores = $service->getRiskScoresForBatch($this->batch([66, 67, 68]));

        $this->assertCount(3, $scores);
        $this->assertCount(3, $service->prompts);
    }

    public function test_an_unrecovered_omission_fails_explicitly_after_the_limit(): void
    {
        // The model never answers for 68, no matter how often it is asked.
        $service = $this->service([$this->answer(67), $this->answer(67), $this->answer(67)], 2);

        try {
            $service->getRiskScoresForBatch($this->batch([67, 68]));
            $this->fail('An unrecovered omission must throw rather than persist a partial batch.');
        } catch (AIStudioApiException $e) {
            $this->assertStringContainsString('omitted student 68', $e->getMessage());
        }

        $this->assertCount(3, $service->prompts);
    }

    public function test_the_limit_is_configurable_and_zero_disables_the_retry(): void
    {
        $service = $this->service([$this->answer(67), $this->answer(68)], 0);

        try {
            $service->getRiskScoresForBatch($this->batch([67, 68]));
            $this->fail('A limit of 0 must fail on the first omission.');
        } catch (AIStudioApiException $e) {
            $this->assertStringContainsString('omitted student 68', $e->getMessage());
        }

        $this->assertCount(1, $service->prompts);
    }

    public function test_a_malformed_response_is_not_retried_as_an_omission(): void
    {
        $service = $this->service(['I am unable to score these students.'], 2);

        try {
            $service->getRiskScoresForBatch($this->batch([67, 68]));
            $this->fail('A malformed response must throw.');
        } catch (AIStudioApiException $e) {
            $this->assertStringContainsString('unparseable', $e->getMessage());
        }

        $this->assertCount(1, $service->prompts);
    }

    public function test_an_answer_for_an_unknown_student_is_ignored(): void
    {
        // The model volunteers a student that was not in the batch; it must not be scored.
        $service = $this->service([
            '[{"student_id":999,"risk_score":10,"risk_level":"Low"},'
                .'{"student_id":67,"risk_score":80,"risk_level":"High"},'
                .'{"student_id":68,"risk_score":80,"risk_level":"High"}]',
        ], 2);

        $scores = $service->getRiskScoresForBatch($this->batch([67, 68]));

        $this->assertSame([67, 68], array_keys($scores));
        $this->assertCount(1, $service->prompts);
    }
}
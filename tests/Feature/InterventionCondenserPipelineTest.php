<?php

namespace Tests\Feature;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use App\Services\InterventionService;
use App\Services\RecommendationCondenser;
use App\Services\RecommendationTextService;
use App\Services\RiskScoringService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class InterventionCondenserPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        config()->set('services.aistudio.api_key', 'test-key');
        config()->set('services.aistudio.api_url', 'https://aistudio.test/generate');
        config()->set('services.aistudio.retry_limit', 1);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function createTables(): void
    {
        foreach (['risk_scores', 'intervention_recommendations', 'student_recommendation_tracking'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('grading_period', 12);
            $table->string('school_year', 9);
            $table->integer('risk_score');
            $table->string('risk_level', 10);
            $table->json('risk_factors')->nullable();
            $table->timestamps();
        });

        Schema::create('intervention_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('grading_period', 12);
            $table->string('school_year', 9);
            $table->json('risk_factors')->nullable();
            $table->longText('suggested_actions')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_recommendation_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('recommendation_id');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::table('risk_scores')->insert([
            'student_id' => 168,
            'grading_period' => 'Midterm',
            'school_year' => InterventionService::SCHOOL_YEAR,
            'risk_score' => 88,
            'risk_level' => 'High',
            'risk_factors' => json_encode(['Failing grades in 5 subjects', 'Attendance below 90%']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function verboseResponse(): array
    {
        return [
            ['action' => 'tutoring', 'priority' => 'high', 'details' => 'Mandatory intensive tutoring for CSC106 (Database) and CSC102 (Computer Systems) due to critical failure grades (25 and 38). Focus on foundational concept remediation.'],
            ['action' => 'academic_advising', 'priority' => 'high', 'details' => 'Immediate intervention to discuss the 65.38% average and potential shift in study strategies for the 5 failing subjects. Reviewing the load for the next semester is critical.'],
            ['action' => 'parent_meeting', 'priority' => 'high', 'details' => 'Formal meeting with student and parents/guardians to discuss the high-risk status and the severe discrepancy between high performance in programming (87, 78) and low performance in theory-based subjects.'],
            ['action' => 'study_skills', 'priority' => 'medium', 'details' => 'Workshop on study habits specifically for theory-heavy technical subjects, as the student displays a clear aptitude for practical programming (CSP101/102) but struggles with conceptual subjects like CSC106 and CSC102.'],
            ['action' => 'academic_coaching', 'priority' => 'medium', 'details' => 'Coaching session to address the near-passing grades (70-74) in Calculus 1, 2, and Discrete Structures to prevent further drops in core mathematical foundations.'],
            ['action' => 'counseling', 'priority' => 'medium', 'details' => 'Assessment of potential external factors affecting performance, given that the student maintains excellent attendance (97.06%) but is failing 62.5% of their course load.'],
        ];
    }

    protected function fakeAi(array $recommendations): void
    {
        Http::fake([
            'aistudio.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['recommendations' => $recommendations])]]],
                ]],
            ], 200),
        ]);
    }

    /** The real service, with the mock API and the scoring engine doubled. */
    protected function service(): InterventionService
    {
        $students = Mockery::mock(StudentRepository::class);
        $students->shouldReceive('find')->andReturn((object) [
            'id' => 168,
            'first_name' => 'Wendy',
            'last_name' => 'Santiago',
            'student_number' => 'UDD-2024-168',
            'block_id' => 3,
        ]);

        $structure = Mockery::mock(AcademicStructureRepository::class);
        $structure->shouldReceive('blockPlacement')->andReturn(['program_id' => 2, 'program_code' => 'BSIT']);

        $riskScoring = Mockery::mock(RiskScoringService::class);
        $riskScoring->shouldReceive('getGrades')->andReturn([168 => [
            'subjects' => [
                ['subject_code' => 'CSC106', 'subject_name' => 'Database', 'numerical_grade' => 25],
                ['subject_code' => 'CSC102', 'subject_name' => 'Computer Systems', 'numerical_grade' => 38],
            ],
            'total_subjects' => 8,
            'avg_grade' => 65.38,
            'failing_subjects' => 5,
        ]]);
        $riskScoring->shouldReceive('getAttendance')->andReturn([168 => [
            'attendance_rate' => 97.06,
            'absences' => 1,
            'lates' => 0,
        ]]);

        return new InterventionService($riskScoring, $students, $structure, new RecommendationCondenser());
    }

    protected function storedText(int $studentId): string
    {
        $stored = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->value('suggested_actions');

        return (new RecommendationTextService())->toText($stored);
    }

    public function test_the_pipeline_stores_the_condensed_actions_not_the_raw_essay(): void
    {
        $this->fakeAi($this->verboseResponse());

        $result = $this->service()->generateForStudent(168, 'Midterm');

        $this->assertTrue($result['success'], $result['error'] ?? '');

        $stored = DB::table('intervention_recommendations')->where('student_id', 168)->get();
        $this->assertCount(1, $stored);
        $this->assertCount(3, json_decode($stored->first()->suggested_actions, true));

        $this->assertSame(
            "[high] Tutoring: Mandatory intensive tutoring for CSC106 (Database) and CSC102 (Computer Systems).\n"
            . "[high] Parent meeting: Formal meeting with student and parents/guardians.\n"
            . '[medium] Study skills: Workshop on study habits specifically for theory-heavy technical subjects.',
            $this->storedText(168)
        );
    }

    public function test_a_lean_ai_response_reaches_the_database_verbatim(): void
    {
        $lean = [
            ['action' => 'tutoring', 'details' => 'Twice-weekly tutoring for CSC106 and CSC102 until grades reach 75.', 'priority' => 'high'],
            ['action' => 'parent_meeting', 'details' => 'Meet the guardians this week on the 5 failing subjects.', 'priority' => 'high'],
        ];

        $this->fakeAi($lean);

        $this->service()->generateForStudent(168, 'Midterm');

        $this->assertSame(
            "[high] Tutoring: Twice-weekly tutoring for CSC106 and CSC102 until grades reach 75.\n"
            . '[high] Parent meeting: Meet the guardians this week on the 5 failing subjects.',
            $this->storedText(168)
        );
    }

    public function test_the_trimming_is_logged_for_audit(): void
    {
        $logged = [];

        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            if ($event->message === 'Intervention recommendations condensed') {
                $logged[] = $event->context;
            }
        });

        $this->fakeAi($this->verboseResponse());

        $this->service()->generateForStudent(168, 'Midterm');

        $this->assertCount(1, $logged);
        $this->assertSame(168, $logged[0]['student_id']);
        $this->assertSame(6, $logged[0]['raw_actions']);
        $this->assertSame(3, $logged[0]['kept_actions']);
        $this->assertSame(3, $logged[0]['dropped']);
        $this->assertSame(['tutoring', 'parent_meeting', 'study_skills'], $logged[0]['kept']);
    }

    public function test_a_lean_response_logs_nothing_because_nothing_was_trimmed(): void
    {
        $logged = 0;

        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            if ($event->message === 'Intervention recommendations condensed') {
                $logged++;
            }
        });

        $this->fakeAi([
            ['action' => 'tutoring', 'details' => 'Twice-weekly tutoring for CSC106 until the grade reaches 75.', 'priority' => 'high'],
        ]);

        $this->service()->generateForStudent(168, 'Midterm');

        $this->assertSame(0, $logged);
    }

    public function test_regenerating_replaces_the_row_rather_than_stacking_them(): void
    {
        $this->fakeAi($this->verboseResponse());

        $service = $this->service();
        $service->generateForStudent(168, 'Midterm');
        $service->generateForStudent(168, 'Midterm');

        $this->assertCount(1, DB::table('intervention_recommendations')->where('student_id', 168)->get());
    }
}

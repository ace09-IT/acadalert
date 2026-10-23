<?php

namespace Tests\Feature;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\StudentRepository;
use App\Services\AIStudioApiService;
use App\Services\RiskScoringCacheService;
use App\Services\RiskScoringService;
use App\Services\RiskThresholdService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RiskClassificationPipelineTest extends TestCase
{
    protected int $aiCalls = 0;

    protected array $lastBands = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        config()->set('services.aistudio.batch_delay_seconds', 0);
        config()->set('services.aistudio.batch_size', 5);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function createTables(): void
    {
        foreach (['risk_thresholds', 'risk_scores', 'risk_score_cache'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('risk_thresholds', function (Blueprint $table) {
            $table->id();
            $table->integer('low_threshold')->default(40);
            $table->integer('moderate_threshold')->default(70);
            $table->integer('high_threshold')->default(71);
            $table->decimal('grade_weight', 3, 2)->default(0.60);
            $table->decimal('attendance_weight', 3, 2)->default(0.40);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('grading_period', 12);
            $table->string('school_year', 9);
            $table->string('semester', 4);
            $table->integer('risk_score');
            $table->string('risk_level', 10);
            $table->string('ai_risk_level', 10)->nullable();
            $table->json('risk_factors')->nullable();
            $table->string('scoring_method', 10)->default('ai');
            $table->string('processing_status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('risk_score_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('block_id');
            $table->string('grading_period', 12);
            $table->string('school_year', 9);
            $table->longText('payload');
            $table->timestamps();
        });
    }

    protected function storeBands(int $low, int $moderate, int $high): void
    {
        DB::table('risk_thresholds')->insert([
            'low_threshold' => $low,
            'moderate_threshold' => $moderate,
            'high_threshold' => $high,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function ai(): AIStudioApiService
    {
        $ai = Mockery::mock(AIStudioApiService::class);

        $ai->shouldReceive('getRiskScoresForBatch')->andReturnUsing(function (array $students, array $bands = []) {
            $this->aiCalls++;
            $this->lastBands = $bands;

            $scores = [];
            foreach ($students as $student) {
                $scores[$student['id']] = [
                    'student_id' => $student['id'],
                    'risk_score' => 85,
                    'risk_level' => 'Low',
                    'risk_factors' => ['Low grades in programming'],
                    'explanation' => 'Test double.',
                ];
            }

            return $scores;
        });

        return $ai;
    }

    protected function service(AIStudioApiService $ai): RiskScoringService
    {
        $grades = Mockery::mock(GradeRepository::class);
        $grades->shouldReceive('forStudentsPeriod')->andReturn([]);
        $grades->shouldReceive('failingCountsFor')->andReturn([]);

        $attendance = Mockery::mock(AttendanceRepository::class);
        $attendance->shouldReceive('rawLogsForStudentsGrouped')->andReturn(new Collection());

        $calendar = Mockery::mock(CalendarRepository::class);
        $calendar->shouldReceive('periodDateRange')->andReturn(null);
        $calendar->shouldReceive('periodNumber')->andReturn(2);

        $students = Mockery::mock(StudentRepository::class);
        $students->shouldReceive('forBlock')->andReturn(new Collection([
            (object) [
                'id' => 1,
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'student_number' => 'UDD-2024-001',
                'block_id' => 7,
                'status' => 'Active',
            ],
        ]));

        $structure = Mockery::mock(AcademicStructureRepository::class);
        $structure->shouldReceive('studentPlacements')->andReturn([]);
        $this->app->instance(AcademicStructureRepository::class, $structure);

        return new RiskScoringService(
            $grades,
            $attendance,
            $calendar,
            $students,
            $ai,
            new RiskThresholdService()
        );
    }

    public function test_the_system_assigns_the_level_from_the_default_bands_not_the_ai(): void
    {
        $result = $this->service($this->ai())->processBlock(7, 'Midterm', '2024-2025');

        $this->assertTrue($result['success']);
        $this->assertSame(RiskThresholdService::DEFAULTS, $this->lastBands);

        $row = DB::table('risk_scores')->where('student_id', 1)->first();

        $this->assertNotNull($row);
        $this->assertSame(85, (int) $row->risk_score);
        $this->assertSame('High', $row->risk_level);
        $this->assertSame('Low', $row->ai_risk_level);
    }

    public function test_a_strict_configuration_reclassifies_the_same_ai_score(): void
    {
        $this->storeBands(90, 95, 96);

        $this->service($this->ai())->processBlock(7, 'Midterm', '2024-2025');

        $this->assertSame(
            ['low_threshold' => 90, 'moderate_threshold' => 95, 'high_threshold' => 96],
            $this->lastBands
        );

        $this->assertSame('Low', DB::table('risk_scores')->where('student_id', 1)->value('risk_level'));
    }

    public function test_the_prompt_receives_the_configured_bands(): void
    {
        $this->storeBands(30, 60, 61);

        $this->service($this->ai())->processBlock(7, 'Midterm', '2024-2025');

        $this->assertSame(
            ['low_threshold' => 30, 'moderate_threshold' => 60, 'high_threshold' => 61],
            $this->lastBands
        );
        $this->assertSame(1, $this->aiCalls);
    }

    public function test_the_cache_short_circuits_the_ai_and_flush_all_forces_a_re_score(): void
    {
        $service = $this->service($this->ai());

        $service->processBlock(7, 'Midterm', '2024-2025');
        $this->assertSame(1, $this->aiCalls);
        $this->assertSame(1, DB::table('risk_score_cache')->count());

        $cached = $service->processBlock(7, 'Midterm', '2024-2025');
        $this->assertTrue($cached['cached']);
        $this->assertSame(1, $this->aiCalls);

        $this->assertSame(1, (new RiskScoringCacheService())->flushAll());
        $this->assertSame(0, DB::table('risk_score_cache')->count());

        $service->processBlock(7, 'Midterm', '2024-2025');
        $this->assertSame(2, $this->aiCalls);
    }

    public function test_the_result_carries_the_applied_bands_for_audit(): void
    {
        $result = $this->service($this->ai())->processBlock(7, 'Midterm', '2024-2025');

        $this->assertSame(RiskThresholdService::DEFAULTS, $result['risk_thresholds']);
    }
}
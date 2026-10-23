<?php

namespace Tests\Feature;

use App\Http\Controllers\AcademicHead\RiskScoringController;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Services\RiskScoringService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RiskScoringWorkspaceRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['risk_score_cache', 'risk_scores', 'flags'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('risk_score_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('block_id');
            $table->string('grading_period');
            $table->string('school_year');
            $table->longText('payload');
            $table->timestamps();
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('grading_period');
            $table->string('school_year');
            $table->string('risk_level');
            $table->timestamps();
        });

        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('grading_period');
            $table->string('school_year');
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function controllerWithTwoBlocks(): RiskScoringController
    {
        $structure = Mockery::mock(AcademicStructureRepository::class);
        $structure->shouldReceive('department')->andReturn((object) ['name' => 'CCS', 'code' => 'CCS']);
        $structure->shouldReceive('blocksWithProgramForDepartment')->andReturn(new Collection([
            (object) ['id' => 9, 'name' => 'Block 1', 'program_code' => 'BSCS', 'year_number' => 1],
            (object) ['id' => 10, 'name' => 'Block 2', 'program_code' => 'BSCS', 'year_number' => 1],
        ]));
        $structure->shouldReceive('studentIdsInBlock')->andReturnUsing(function ($blockId) {
            return $blockId === 9 ? [101, 102] : [201, 202];
        });

        $scores = Mockery::mock(RiskScoreRepository::class);
        $scores->shouldReceive('levelByStudent')->andReturn([101 => 'High', 102 => 'Low']);

        $this->app->instance(AcademicStructureRepository::class, $structure);
        $this->app->instance(RiskScoreRepository::class, $scores);

        return new RiskScoringController(Mockery::mock(RiskScoringService::class));
    }

    protected function request(array $query = ['period' => 'Midterm', 'school_year' => '2024-2025']): Request
    {
        $request = Request::create('/academic-head/risk-scoring/data', 'GET', $query);
        $request->attributes->set('department_id', 3);

        return $request;
    }

    public function test_the_refresh_endpoint_returns_both_fragments_the_page_swaps(): void
    {
        $response = $this->controllerWithTwoBlocks()->data($this->request());

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);

        $this->assertStringContainsString('id="scoringCards"', $payload['cards_html']);
        $this->assertStringContainsString('data-block-id="9"', $payload['rows_html']);
        $this->assertStringContainsString('data-block-id="10"', $payload['rows_html']);
    }

    public function test_the_fragments_carry_the_freshly_computed_figures(): void
    {
        $response = $this->controllerWithTwoBlocks()->data($this->request());

        $payload = $response->getData(true);

        $this->assertSame(2, (int) $payload['summary']['blocks']);
        $this->assertSame(4, (int) $payload['summary']['students']);
        $this->assertSame(2, (int) $payload['summary']['scored']);
        $this->assertSame(2, (int) $payload['summary']['pending']);
        $this->assertSame(1, (int) $payload['summary']['high']);
        $this->assertSame(1, (int) $payload['summary']['low']);
        $this->assertSame(50.0, (float) $payload['summary']['percentage']);

        // The refreshed cards must show those figures, not the ones baked into the
        // original page render.
        $this->assertStringContainsString('50.0%', $payload['cards_html']);
    }

    public function test_the_refresh_echoes_the_period_and_school_year_it_served(): void
    {
        // A non-canonical period is normalised, and the response says which slice it
        // actually served so the caller can tell a stale refresh from a current one.
        $response = $this->controllerWithTwoBlocks()->data(
            $this->request(['period' => 'semifinal', 'school_year' => '2025-2026'])
        );

        $payload = $response->getData(true);

        $this->assertSame('2025-2026', $payload['school_year']);
        $this->assertContains($payload['period'], ['Prelim', 'Midterm', 'Finals']);
    }

    public function test_the_refresh_endpoint_refuses_a_user_without_a_department(): void
    {
        $request = Request::create('/academic-head/risk-scoring/data', 'GET');

        $response = $this->controllerWithTwoBlocks()->data($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_the_refresh_is_read_only_and_never_triggers_scoring(): void
    {
        $riskService = Mockery::mock(RiskScoringService::class);
        $riskService->shouldNotReceive('processBlock');

        $structure = Mockery::mock(AcademicStructureRepository::class);
        $structure->shouldReceive('department')->andReturn((object) ['name' => 'CCS', 'code' => 'CCS']);
        $structure->shouldReceive('blocksWithProgramForDepartment')->andReturn(new Collection([
            (object) ['id' => 9, 'name' => 'Block 1', 'program_code' => 'BSCS', 'year_number' => 1],
        ]));
        $structure->shouldReceive('studentIdsInBlock')->andReturn([101]);

        $scores = Mockery::mock(RiskScoreRepository::class);
        $scores->shouldReceive('levelByStudent')->andReturn([]);

        $this->app->instance(AcademicStructureRepository::class, $structure);
        $this->app->instance(RiskScoreRepository::class, $scores);

        $controller = new RiskScoringController($riskService);

        $this->assertTrue($controller->data($this->request())->getData(true)['success']);

        // Nothing was written: the endpoint only reads.
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('risk_scores')->count());
    }
}
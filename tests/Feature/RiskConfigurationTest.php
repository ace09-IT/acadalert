<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RiskThresholdService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        config()->set('services.mock_api', [
            'base_url' => 'http://bulk.test',
            'core_url' => 'http://core.test',
            'timeout' => 5,
            'retry_times' => 1,
            'retry_sleep_ms' => 0,
            'cache_ttl_seconds' => 0,
            'concurrency' => 5,
            'attendance_chunk' => 25,
            'writes_enabled' => true,
            'core_resources' => ['risk_thresholds'],
        ]);

        Http::fake([
            'core.test/*' => Http::response(['id' => 1], 200),
            'bulk.test/*' => Http::response([], 200),
        ]);
    }

    protected function createTables(): void
    {
        foreach (['risk_thresholds', 'risk_scores', 'risk_score_cache', 'audit_logs', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

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

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    protected function user(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => $role . '@example.test',
            'password' => 'secret-password',
            'role' => $role,
            'is_active' => true,
        ]);
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

    protected function storeScore(int $studentId, int $score, string $level): void
    {
        DB::table('risk_scores')->insert([
            'student_id' => $studentId,
            'grading_period' => 'Midterm',
            'school_year' => '2024-2025',
            'semester' => '1st',
            'risk_score' => $score,
            'risk_level' => $level,
            'ai_risk_level' => $level,
            'risk_factors' => json_encode([]),
            'scoring_method' => 'ai',
            'processing_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function cacheRow(): void
    {
        DB::table('risk_score_cache')->insert([
            'block_id' => 7,
            'grading_period' => 'Midterm',
            'school_year' => '2024-2025',
            'payload' => json_encode(['students' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_page_renders_the_stored_bands_as_the_labels_and_drops_the_weights(): void
    {
        $this->storeBands(30, 60, 61);

        $response = $this->actingAs($this->user())->get(route('admin.risk.config'));

        $response->assertOk();
        $response->assertSee('0 - 30', false);
        $response->assertSee('31 - 60', false);
        $response->assertSee('61 - 100', false);

        $response->assertSee("name='high_threshold'", false);

        // The deprecated weights are gone from the UI.
        $response->assertDontSee('grade_weight', false);
        $response->assertDontSee('attendance_weight', false);
    }

    public function test_a_non_contiguous_stored_configuration_is_surfaced_with_its_effective_bands(): void
    {
        // The live-shaped configuration: the High floor (95) is above the Moderate
        // ceiling (70), so 71-94 would otherwise belong to no band at all.
        $this->storeBands(40, 70, 95);

        $response = $this->actingAs($this->user())->get(route('admin.risk.config'));

        $response->assertOk();
        $response->assertSee('Non-contiguous configuration', false);
        $response->assertSee('41 - 94', false);
        $response->assertSee('95 - 100', false);
    }

    public function test_a_non_admin_cannot_reach_the_configuration_page(): void
    {
        $this->actingAs($this->user('guidance_counselor'))
            ->get(route('admin.risk.config'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_saving_persists_the_bands_invalidates_the_cache_and_reclassifies_stored_scores(): void
    {
        $this->storeBands(40, 70, 71);
        $this->storeScore(1, 85, 'High');
        $this->storeScore(2, 60, 'Moderate');
        $this->cacheRow();

        $response = $this->actingAs($this->user())
            ->from(route('admin.risk.config'))
            ->post(route('admin.risk.save'), [
                'low_threshold' => 90,
                'moderate_threshold' => 95,
                'high_threshold' => 96,
            ]);

        $response->assertRedirect(route('admin.risk.config'));
        $response->assertSessionHas('success');

        $row = DB::table('risk_thresholds')->orderBy('id')->first();
        $this->assertSame(90, (int) $row->low_threshold);
        $this->assertSame(95, (int) $row->moderate_threshold);
        $this->assertSame(96, (int) $row->high_threshold);

        // Weights are deprecated: not written, schema defaults preserved.
        $this->assertEqualsWithDelta(0.60, (float) $row->grade_weight, 0.001);
        $this->assertEqualsWithDelta(0.40, (float) $row->attendance_weight, 0.001);

        // The 60-minute cache can never defer an explicit save.
        $this->assertSame(0, DB::table('risk_score_cache')->count());

        $this->assertSame('Low', DB::table('risk_scores')->where('student_id', 1)->value('risk_level'));
        $this->assertSame('Low', DB::table('risk_scores')->where('student_id', 2)->value('risk_level'));

        $this->assertSame(1, DB::table('audit_logs')->where('action', 'RISK_CONFIG_UPDATED')->count());
    }

    public function test_a_fresh_install_creates_the_configuration_row(): void
    {
        $response = $this->actingAs($this->user())
            ->from(route('admin.risk.config'))
            ->post(route('admin.risk.save'), [
                'low_threshold' => 30,
                'moderate_threshold' => 60,
                'high_threshold' => 61,
            ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, DB::table('risk_thresholds')->count());
        $this->assertSame(30, (int) DB::table('risk_thresholds')->value('low_threshold'));
        $this->assertSame(61, (int) DB::table('risk_thresholds')->value('high_threshold'));
    }

    public function test_a_gap_between_the_bands_is_rejected_and_nothing_changes(): void
    {
        $this->storeBands(40, 70, 71);

        $response = $this->actingAs($this->user())
            ->from(route('admin.risk.config'))
            ->post(route('admin.risk.save'), [
                'low_threshold' => 40,
                'moderate_threshold' => 70,
                'high_threshold' => 80,
            ]);

        $response->assertRedirect(route('admin.risk.config'));
        $response->assertSessionHas('error');

        $this->assertSame(71, (int) DB::table('risk_thresholds')->orderBy('id')->value('high_threshold'));
    }

    public function test_an_overlapping_configuration_is_rejected(): void
    {
        $this->storeBands(40, 70, 71);

        $response = $this->actingAs($this->user())
            ->from(route('admin.risk.config'))
            ->post(route('admin.risk.save'), [
                'low_threshold' => 70,
                'moderate_threshold' => 70,
                'high_threshold' => 71,
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(40, (int) DB::table('risk_thresholds')->orderBy('id')->value('low_threshold'));
    }

    public function test_the_consistency_rules_are_the_single_source_used_by_the_form(): void
    {
        $this->assertNull(RiskThresholdService::consistencyError([
            'low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 71,
        ]));
        $this->assertNotNull(RiskThresholdService::consistencyError([
            'low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 80,
        ]));
    }
}
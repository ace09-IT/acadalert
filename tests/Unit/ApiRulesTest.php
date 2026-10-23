<?php

namespace Tests\Unit;

use App\Rules\ApiExists;
use App\Rules\ApiUnique;
use App\Services\Api\MockApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // MockApiClient is a singleton holding a memo, so it must be rebuilt per test.
        $this->app->forgetInstance(MockApiClient::class);
    }

    protected function fakeDepartments(): void
    {
        Http::fake([
            '*/departments' => Http::response([
                ['id' => '1', 'code' => 'CCS', 'name' => 'College of Computer Studies'],
                ['id' => '2', 'code' => 'COE', 'name' => 'College of Engineering'],
            ], 200),
        ]);
    }

    protected function runRule($rule, $value): array
    {
        $failed = [];

        $rule->validate('attribute', $value, function ($message) use (&$failed) {
            $failed[] = $message;
        });

        return $failed;
    }

    public function test_api_exists_passes_for_a_matching_id(): void
    {
        $this->fakeDepartments();

        $this->assertSame([], $this->runRule(new ApiExists('departments', 'id'), 1));
        $this->assertSame([], $this->runRule(new ApiExists('departments', 'id'), '2'));
    }

    public function test_api_exists_fails_for_a_missing_id(): void
    {
        $this->fakeDepartments();

        $this->assertCount(1, $this->runRule(new ApiExists('departments', 'id'), 99));
    }

    public function test_api_exists_matches_strings_case_insensitively(): void
    {
        // MySQL's default collation was case-insensitive, and so is this rule.
        $this->fakeDepartments();

        $this->assertSame([], $this->runRule(new ApiExists('departments', 'code'), 'ccs'));
        $this->assertSame([], $this->runRule(new ApiExists('departments', 'code'), 'CCS'));
    }

    public function test_api_unique_rejects_a_case_insensitive_duplicate(): void
    {
        $this->fakeDepartments();

        $this->assertCount(1, $this->runRule(new ApiUnique('departments', 'code'), 'ccs'));
        $this->assertCount(1, $this->runRule(new ApiUnique('departments', 'code'), 'CCS'));
    }

    public function test_api_unique_allows_a_new_value(): void
    {
        $this->fakeDepartments();

        $this->assertSame([], $this->runRule(new ApiUnique('departments', 'code'), 'CBA'));
    }

    public function test_api_unique_ignores_the_row_being_updated(): void
    {
        $this->fakeDepartments();

        // Re-saving department 1's own code must not collide with itself.
        $this->assertSame(
            [],
            $this->runRule(new ApiUnique('departments', 'code', 1), 'CCS')
        );

        $this->assertCount(
            1,
            $this->runRule(new ApiUnique('departments', 'code', 1), 'COE')
        );
    }
}

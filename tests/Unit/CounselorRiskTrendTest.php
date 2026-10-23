<?php

namespace Tests\Unit;

use App\Http\Controllers\Counselor\DashboardController;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CounselorRiskTrendTest extends TestCase
{
    protected function trendFor(array $rows): array
    {
        $controller = app(DashboardController::class);
        $method = new \ReflectionMethod($controller, 'buildRiskTrend');
        $method->setAccessible(true);

        $history = (new Collection($rows))->map(fn (array $row) => (object) $row);

        return $method->invoke($controller, $history);
    }

    public function test_it_reads_the_periods_in_pretim_to_finals_order_with_a_verdict_each(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Prelim', 'risk_score' => 60, 'risk_level' => 'Moderate'],
            ['grading_period' => 'Midterm', 'risk_score' => 75, 'risk_level' => 'High'],
            ['grading_period' => 'Finals', 'risk_score' => 70, 'risk_level' => 'Moderate'],
        ]);

        $this->assertSame(['Prelim', 'Midterm', 'Finals'], array_column($trend['rows'], 'period'));

        $this->assertSame('stable', $trend['rows'][0]['trend']);
        $this->assertNull($trend['rows'][0]['delta']);

        $this->assertSame('worsening', $trend['rows'][1]['trend']);
        $this->assertSame(15, $trend['rows'][1]['delta']);

        $this->assertSame('improving', $trend['rows'][2]['trend']);
        $this->assertSame(-5, $trend['rows'][2]['delta']);

        $this->assertSame('improving', $trend['overall']);
    }

    public function test_an_unchanged_level_is_stable_even_when_the_score_moves(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Prelim', 'risk_score' => 80, 'risk_level' => 'High'],
            ['grading_period' => 'Midterm', 'risk_score' => 83, 'risk_level' => 'High'],
        ]);

        $this->assertSame('stable', $trend['rows'][1]['trend']);
        $this->assertSame(3, $trend['rows'][1]['delta']);
    }

    public function test_lower_risk_is_improving(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Prelim', 'risk_score' => 90, 'risk_level' => 'High'],
            ['grading_period' => 'Midterm', 'risk_score' => 40, 'risk_level' => 'Low'],
        ]);

        $this->assertSame('improving', $trend['rows'][1]['trend']);
        $this->assertSame('improving', $trend['overall']);
    }

    public function test_higher_risk_is_worsening(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Prelim', 'risk_score' => 30, 'risk_level' => 'Low'],
            ['grading_period' => 'Finals', 'risk_score' => 88, 'risk_level' => 'High'],
        ]);

        $this->assertSame('worsening', $trend['rows'][1]['trend']);
        $this->assertSame('worsening', $trend['overall']);
    }

    public function test_a_single_scored_period_reports_stable_with_no_delta(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Midterm', 'risk_score' => 55, 'risk_level' => 'Moderate'],
        ]);

        $this->assertCount(1, $trend['rows']);
        $this->assertSame('stable', $trend['overall']);
        $this->assertNull($trend['delta']);
    }

    public function test_no_scores_at_all_reports_unknown_and_never_throws(): void
    {
        $trend = $this->trendFor([]);

        $this->assertSame([], $trend['rows']);
        $this->assertSame('unknown', $trend['overall']);
        $this->assertNull($trend['delta']);
    }

    public function test_an_unrecognised_level_does_not_break_the_comparison(): void
    {
        $trend = $this->trendFor([
            ['grading_period' => 'Prelim', 'risk_score' => 50, 'risk_level' => 'No Data'],
            ['grading_period' => 'Midterm', 'risk_score' => 60, 'risk_level' => 'High'],
        ]);

        $this->assertSame('worsening', $trend['rows'][1]['trend']);
    }
}

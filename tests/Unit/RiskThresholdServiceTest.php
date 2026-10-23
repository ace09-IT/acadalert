<?php

namespace Tests\Unit;

use App\Services\RiskThresholdService;
use Tests\TestCase;

class RiskThresholdServiceTest extends TestCase
{
    protected function service(): RiskThresholdService
    {
        return new RiskThresholdService();
    }

    public function test_the_defaults_reproduce_the_legacy_bands_exactly(): void
    {
        $expected = [
            0 => 'Low',
            40 => 'Low',
            41 => 'Moderate',
            70 => 'Moderate',
            71 => 'High',
            100 => 'High',
        ];

        foreach ($expected as $score => $level) {
            $this->assertSame(
                $level,
                $this->service()->levelFor($score, RiskThresholdService::DEFAULTS),
                'score ' . $score
            );
        }
    }

    public function test_changing_the_thresholds_changes_the_assigned_level(): void
    {
        $service = $this->service();
        $default = RiskThresholdService::DEFAULTS;
        $strict = ['low_threshold' => 30, 'moderate_threshold' => 60, 'high_threshold' => 61];
        $extreme = ['low_threshold' => 90, 'moderate_threshold' => 95, 'high_threshold' => 96];

        $this->assertSame('Low', $service->levelFor(40, $default));
        $this->assertSame('Moderate', $service->levelFor(41, $default));
        $this->assertSame('Moderate', $service->levelFor(40, $strict));
        $this->assertSame('Low', $service->levelFor(20, $strict));
        $this->assertSame('High', $service->levelFor(85, $strict));

        $this->assertSame('High', $service->levelFor(85, $default));
        $this->assertSame('Low', $service->levelFor(85, $extreme));
        $this->assertSame('Moderate', $service->levelFor(95, $extreme));
        $this->assertSame('High', $service->levelFor(96, $extreme));
    }

    public function test_a_non_contiguous_configuration_never_leaves_a_score_unclassified(): void
    {
        $gap = ['low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 80];
        $service = $this->service();

        $this->assertSame('Moderate', $service->levelFor(70, $gap));
        $this->assertSame('Moderate', $service->levelFor(75, $gap));
        $this->assertSame('High', $service->levelFor(80, $gap));
    }

    public function test_band_labels_and_prompt_bands_come_from_the_stored_values(): void
    {
        $bands = ['low_threshold' => 30, 'moderate_threshold' => 60, 'high_threshold' => 61];

        $this->assertSame(
            ['Low' => '0 - 30', 'Moderate' => '31 - 60', 'High' => '61 - 100'],
            RiskThresholdService::bandLabels($bands)
        );

        $this->assertSame([
            ['level' => 'Low', 'min' => 0, 'max' => 30],
            ['level' => 'Moderate', 'min' => 31, 'max' => 60],
            ['level' => 'High', 'min' => 61, 'max' => 100],
        ], RiskThresholdService::bandsForPrompt($bands));
    }

    public function test_normalize_falls_back_per_field(): void
    {
        $this->assertSame(RiskThresholdService::DEFAULTS, RiskThresholdService::normalize([]));
        $this->assertSame(RiskThresholdService::DEFAULTS, RiskThresholdService::normalize(['low_threshold' => null]));
        $this->assertSame(30, RiskThresholdService::normalize(['low_threshold' => '30'])['low_threshold']);
    }

    public function test_effective_bands_cover_the_gap_while_nominal_labels_stay_readable(): void
    {
        $contiguous = ['low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 71];

        $this->assertSame(
            RiskThresholdService::bandLabels($contiguous),
            RiskThresholdService::effectiveBandLabels($contiguous)
        );

        $gapped = ['low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 95];

        $this->assertSame(
            ['Low' => '0 - 40', 'Moderate' => '41 - 70', 'High' => '95 - 100'],
            RiskThresholdService::bandLabels($gapped)
        );

        $this->assertSame(
            ['Low' => '0 - 40', 'Moderate' => '41 - 94', 'High' => '95 - 100'],
            RiskThresholdService::effectiveBandLabels($gapped)
        );
    }

    public function test_the_consistency_rules_reject_gaps_overlaps_and_out_of_range_values(): void
    {
        $this->assertNull(RiskThresholdService::consistencyError(RiskThresholdService::DEFAULTS));
        $this->assertNull(RiskThresholdService::consistencyError([
            'low_threshold' => 30, 'moderate_threshold' => 60, 'high_threshold' => 61,
        ]));

        $this->assertNotNull(RiskThresholdService::consistencyError([
            'low_threshold' => 70, 'moderate_threshold' => 70, 'high_threshold' => 71,
        ]));

        $this->assertNotNull(RiskThresholdService::consistencyError([
            'low_threshold' => 40, 'moderate_threshold' => 70, 'high_threshold' => 72,
        ]));

        $this->assertNotNull(RiskThresholdService::consistencyError([
            'low_threshold' => 40, 'moderate_threshold' => 101, 'high_threshold' => 102,
        ]));
    }
}
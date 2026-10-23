<?php

namespace Tests\Unit;

use App\Services\Reports\EndOfTermReportInsightService;
use App\Services\Reports\EndOfTermReportService;
use Tests\TestCase;

class EndOfTermReportAppendixTest extends TestCase
{
    protected function service(): EndOfTermReportService
    {
        return app(EndOfTermReportService::class);
    }

    protected function snapshot(): array
    {
        return [
            'meta' => [
                'department' => ['id' => 1, 'code' => 'SITE', 'name' => 'School of Information Technology Education'],
                'school_year' => '2024-2025',
                'semester' => '1st',
                'grading_period' => 'Prelim',
                'period_number' => 1,
                'period_count' => 3,
                'generated_at' => '2026-09-30 02:00:17',
                'formula_version' => '1.0.0',
                'computation' => 'deterministic-rule-based-aggregation',
                'ai_used' => false,
                'external_service_calls' => 0,
                'source' => ['table' => 'risk_scores', 'processing_status' => 'completed'],
            ],
            'totals' => [
                'monitored' => 300,
                'active_students' => 320,
                'coverage_percentage' => 93.75,
                'without_score' => 20,
            ],
            'distribution' => [
                ['risk_level' => 'Low', 'count' => 6, 'percentage' => 2.0],
                ['risk_level' => 'Moderate', 'count' => 40, 'percentage' => 13.33],
                ['risk_level' => 'High', 'count' => 254, 'percentage' => 84.67],
            ],
            'counts_by_level' => ['Low' => 6, 'Moderate' => 40, 'High' => 254],
            'percentages_by_level' => ['Low' => 2.0, 'Moderate' => 13.33, 'High' => 84.67],
            'comparison' => [
                'available' => false,
                'previous_period' => null,
                'previous_total' => 0,
                'total_delta' => 0,
                'headline' => 'Prelim is the baseline grading period of the academic year, so no earlier '
                    . 'period exists to compare against.',
                'lines' => ['This report establishes the reference point that subsequent end-of-term reports '
                    . 'will be measured against.'],
                'previous_counts' => [],
                'previous_percentages' => [],
                'count_delta' => [],
                'percentage_point_delta' => [],
                'transitions' => [],
            ],
            'programs' => [[
                'program_code' => 'BSIT',
                'program_name' => 'BS Information Technology',
                'monitored' => 300,
                'counts' => ['Low' => 6, 'Moderate' => 40, 'High' => 254],
                'percentages' => ['Low' => 2.0, 'Moderate' => 13.33, 'High' => 84.67],
            ]],
            'score_statistics' => [
                'count' => 300,
                'average' => 80.8,
                'median' => 84,
                'minimum' => 5,
                'maximum' => 92,
            ],
        ];
    }

    protected function insights(): array
    {
        return [
            'bands' => ['Low' => '0 - 40', 'Moderate' => '41 - 75', 'High' => '76 - 100'],
            'bands_effective' => ['Low' => '0 - 40', 'Moderate' => '41 - 75', 'High' => '76 - 100'],
            'thresholds' => ['low_threshold' => 40, 'moderate_threshold' => 75, 'high_threshold' => 76],
            'warning' => [
                'percentage' => '84.67%',
                'high' => 254,
                'monitored' => 300,
                'bands' => 'Low 0-40 / Moderate 41-75 / High 76-100',
            ],
            'year_levels' => [[
                'label' => '1st Year',
                'monitored' => 120,
                'counts' => ['Low' => 2, 'Moderate' => 16, 'High' => 102],
                'percentages' => ['Low' => 1.67, 'Moderate' => 13.33, 'High' => 85.0],
            ]],
            'intervention' => [
                'high_risk' => 254,
                'high_escalated' => 200,
                'high_pending' => 54,
                'coverage_percentage' => 78.74,
                'total_escalations' => 240,
                'escalated_students' => 200,
                'open_cases' => 60,
                'resolved_cases' => 180,
                'note' => null,
            ],
            'period_series' => [[
                'period' => 'Prelim',
                'monitored' => 300,
                'high' => 254,
                'moderate' => 40,
                'low' => 6,
                'high_percentage' => 84.67,
                'escalations' => 240,
            ]],
            'monitored' => 300,
        ];
    }

    public function test_the_plain_pdf_is_reproducible_and_carries_no_appendix(): void
    {
        $first = $this->service()->renderPdf($this->snapshot(), 'Academic Head');
        $second = $this->service()->renderPdf($this->snapshot(), 'Academic Head');

        $this->assertStringStartsWith('%PDF', $first);
        $this->assertSame($first, $second, 'a stored report must re-render byte-for-byte');
        $this->assertStringNotContainsString('Appendix', $first);
        $this->assertStringNotContainsString('Breakdown by', $first);
    }

    public function test_an_empty_insight_payload_is_treated_as_no_appendix(): void
    {
        $plain = $this->service()->renderPdf($this->snapshot(), 'Academic Head');

        $this->assertSame($plain, $this->service()->renderPdf($this->snapshot(), 'Academic Head', []));
    }

    public function test_the_enriched_appendix_is_numbered_a1_to_a5_without_a_gap(): void
    {
        $enriched = $this->service()->renderPdf($this->snapshot(), 'Academic Head', $this->insights());

        $this->assertStringContainsString('Appendix - Departmental Analysis', $enriched);

        preg_match_all('/A(\d)\. /', $enriched, $matches);

        $this->assertSame(['1', '2', '3', '4', '5'], $matches[1], 'the appendix numbers must run A1..A5 with no gap');
    }

    public function test_an_appendix_without_a_configuration_warning_still_numbers_without_a_gap(): void
    {
        // A1 is conditional (it only appears when the High band swallows the
        // department). Its absence must not leave a hole in the numbering.
        $insights = $this->insights();
        $insights['warning'] = null;

        $enriched = $this->service()->renderPdf($this->snapshot(), 'Academic Head', $insights);

        preg_match_all('/A(\d)\. /', $enriched, $matches);

        $this->assertSame(['2', '3', '4', '5'], $matches[1]);
    }

    public function test_the_removed_sections_are_absent_from_the_enriched_pdf(): void
    {
        $enriched = $this->service()->renderPdf($this->snapshot(), 'Academic Head', $this->insights());

        $this->assertStringNotContainsString('Breakdown by Subject', $enriched);
        $this->assertStringNotContainsString('Recommendations and Next Steps', $enriched);
        $this->assertStringNotContainsString('grades below 75', $enriched);
        // Match the rendered heading shape ("A6. Title"), not the bare three
        // characters: the PDF's embedded image stream can contain "A6." by
        // chance. The sibling numbering test already proves A1..A5 is gapless.
        $this->assertStringNotContainsString('A6. ', $enriched);
        $this->assertStringNotContainsString('A7. ', $enriched);

        $this->assertStringContainsString('A4. Intervention Status', $enriched);
        $this->assertStringContainsString('A5. Term Progression', $enriched);
    }

    public function test_the_report_spells_it_program_never_programme(): void
    {
        $plain = $this->service()->renderPdf($this->snapshot(), 'Academic Head');
        $enriched = $this->service()->renderPdf($this->snapshot(), 'Academic Head', $this->insights());

        foreach (['plain' => $plain, 'enriched' => $enriched] as $label => $bytes) {
            $this->assertStringContainsString('4. Program Breakdown', $bytes, $label);
            $this->assertStringNotContainsString('Programme', $bytes, $label);
            $this->assertStringNotContainsString('programme', $bytes, $label);
        }
    }

    public function test_the_display_payload_no_longer_carries_the_removed_sections(): void
    {
        $payload = app(EndOfTermReportInsightService::class)->forReport([]);

        $this->assertArrayNotHasKey('subjects', $payload);
        $this->assertArrayNotHasKey('subject_rows_total', $payload);
        $this->assertArrayNotHasKey('next_steps', $payload);

        $this->assertArrayHasKey('year_levels', $payload);
        $this->assertArrayHasKey('intervention', $payload);
        $this->assertArrayHasKey('period_series', $payload);
    }
}

<?php

namespace Tests\Unit;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\CaseRepository;
use App\Services\Reports\CounselorActivityReportService;
use Tests\TestCase;

class CounselorActivityReportServiceTest extends TestCase
{
    protected function service(): CounselorActivityReportService
    {
        return new CounselorActivityReportService(
            $this->createMock(AcademicStructureRepository::class),
            $this->createMock(CaseRepository::class)
        );
    }

    protected function cases(): array
    {
        return [
            [
                'id' => 1,
                'student_id' => 10,
                'status' => 'In Progress',
                'priority' => 'High',
                'risk_level_at_escalation' => 'High',
                'escalated_at' => '2025-01-05 09:00:00',
                'resolved_at' => null,
                'school_year' => '2024-2025',
            ],
            [
                'id' => 2,
                'student_id' => 11,
                'status' => 'Resolved',
                'priority' => 'Medium',
                'risk_level_at_escalation' => 'Moderate',
                'escalated_at' => '2025-01-06 10:00:00',
                'resolved_at' => '2025-01-20 10:00:00',
                'school_year' => '2024-2025',
            ],
            [
                'id' => 3,
                'student_id' => 12,
                'status' => 'New',
                'priority' => 'Critical',
                'risk_level_at_escalation' => 'High',
                'escalated_at' => '2025-02-01 08:00:00',
                'resolved_at' => null,
                'school_year' => '2024-2025',
            ],
        ];
    }

    protected function sessions(): array
    {
        return [
            [
                'case_id' => 1,
                'session_date' => '2025-01-08',
                'session_type' => 'In-person',
                'status_after_session' => 'In Progress',
                'follow_up_date' => '2025-01-15',
            ],
            [
                'case_id' => 2,
                'session_date' => '2025-01-20',
                'session_type' => 'Virtual',
                'status_after_session' => 'Resolved',
                'follow_up_date' => null,
            ],
            // Belongs to a DIFFERENT caseload and must be ignored entirely.
            [
                'case_id' => 999,
                'session_date' => '2025-01-09',
                'session_type' => 'Email',
                'status_after_session' => 'In Progress',
                'follow_up_date' => null,
            ],
        ];
    }

    protected function snapshot(string $asOf = '2025-02-10'): array
    {
        return $this->service()->summarise($this->cases(), $this->sessions(), $asOf);
    }

    public function test_the_caseload_splits_into_open_and_resolved(): void
    {
        $totals = $this->snapshot()['totals'];

        $this->assertSame(3, $totals['caseload']);
        $this->assertSame(2, $totals['open']);
        $this->assertSame(1, $totals['resolved']);
        $this->assertSame(1, $totals['critical_open']);
        $this->assertSame(33.33, $totals['resolution_rate']);
    }

    public function test_priority_counts_cover_the_open_cases_only(): void
    {
        $byPriority = $this->snapshot()['by_priority'];

        // Every bucket is present in canonical order, even when empty, so the
        // chart legend never changes shape.
        $this->assertSame(['Critical', 'High', 'Medium', 'Low'], array_keys($byPriority));
        $this->assertSame(1, $byPriority['Critical']);
        $this->assertSame(1, $byPriority['High']);
        $this->assertSame(0, $byPriority['Medium'], 'the resolved Medium case must not count as open');
    }

    public function test_sessions_from_another_caseload_are_never_counted(): void
    {
        $sessions = $this->snapshot()['sessions'];

        $this->assertSame(2, $sessions['total']);
        $this->assertSame(2, $sessions['cases_contacted']);
        $this->assertArrayNotHasKey('Email', $sessions['by_type']);
        $this->assertSame(['In-person' => 1, 'Virtual' => 1], $sessions['by_type']);
    }

    public function test_response_time_uses_the_first_session_and_excludes_uncontacted_cases(): void
    {
        $response = $this->snapshot()['response'];

        $this->assertSame(2, $response['cases_contacted']);
        $this->assertSame(1, $response['cases_uncontacted']);
        $this->assertSame(8.5, $response['average_days']);
        $this->assertSame(8.5, $response['median_days']);
        $this->assertSame(3, $response['fastest_days']);
        $this->assertSame(14, $response['slowest_days']);
    }

    public function test_a_satisfied_follow_up_is_not_reported_as_overdue(): void
    {
        $sessions = $this->sessions();
        $sessions[] = [
            'case_id' => 1,
            'session_date' => '2025-01-17',
            'session_type' => 'Phone',
            'status_after_session' => 'In Progress',
            'follow_up_date' => null,
        ];

        $followUps = $this->service()->summarise($this->cases(), $sessions, '2025-02-10')['follow_ups'];

        $this->assertSame(1, $followUps['scheduled']);
        $this->assertSame(0, $followUps['overdue'], 'the later session satisfied the follow-up');
        $this->assertSame(100.0, $followUps['compliance_rate']);
    }

    public function test_an_unsatisfied_past_follow_up_is_overdue(): void
    {
        $followUps = $this->snapshot()['follow_ups'];

        $this->assertSame(1, $followUps['overdue']);
        $this->assertSame(0, $followUps['upcoming']);
        $this->assertSame(0.0, $followUps['compliance_rate']);
    }

    public function test_the_covered_period_is_derived_from_the_data_when_not_pinned(): void
    {
        $meta = $this->snapshot()['meta'];

        $this->assertSame('2025-01-05', $meta['window_from']);
        $this->assertSame('2025-02-10', $meta['window_to']);
        $this->assertSame('2025-02-10', $meta['as_of']);
        $this->assertFalse($meta['ai_used']);
        $this->assertSame(0, $meta['external_service_calls']);
        $this->assertSame('deterministic-rule-based', $meta['computation']);
    }

    public function test_monthly_activity_is_grouped_and_ordered_by_month(): void
    {
        $monthly = $this->snapshot()['monthly'];

        $this->assertSame(['2025-01', '2025-02'], array_column($monthly, 'month'));
        $this->assertSame(2, $monthly[0]['escalated']);
        $this->assertSame(1, $monthly[0]['resolved']);
        $this->assertSame(2, $monthly[0]['sessions']);
        $this->assertSame(1, $monthly[1]['escalated']);
    }

    public function test_row_order_never_changes_the_snapshot_or_its_digest(): void
    {
        $service = $this->service();

        $ordered = $service->summarise($this->cases(), $this->sessions(), '2025-02-10');

        $shuffledCases = array_reverse($this->cases());
        $shuffledSessions = array_reverse($this->sessions());
        $shuffled = $service->summarise($shuffledCases, $shuffledSessions, '2025-02-10');

        $this->assertSame($ordered, $shuffled);
        $this->assertSame($service->digest($ordered), $service->digest($shuffled));
    }

    public function test_the_digest_depends_on_the_figures_only(): void
    {
        $service = $this->service();
        $snapshot = $service->summarise($this->cases(), $this->sessions(), '2025-02-10');

        // Re-keying every associative array (a no-op change of insertion order)
        // must not move the fingerprint.
        $rekeyed = $snapshot;
        ksort($rekeyed['totals']);

        $this->assertSame($service->digest($snapshot), $service->digest($rekeyed));

        // A real change must move it.
        $tampered = $snapshot;
        $tampered['totals']['open'] = 99;

        $this->assertNotSame($service->digest($snapshot), $service->digest($tampered));
    }

    public function test_an_empty_caseload_reports_zeroes_and_never_divides_by_zero(): void
    {
        $snapshot = $this->service()->summarise([], [], '2025-02-10');

        $this->assertSame(0, $snapshot['totals']['caseload']);
        $this->assertSame(0.0, $snapshot['totals']['resolution_rate']);
        $this->assertSame(0.0, $snapshot['sessions']['average_per_case']);
        $this->assertSame(0.0, $snapshot['response']['average_days']);
        $this->assertSame(100.0, $snapshot['follow_ups']['compliance_rate']);
        $this->assertSame([], $snapshot['sessions']['by_type']);
        $this->assertSame([], $snapshot['monthly']);

        // Every bucket is still present, so the screen renders an empty chart
        // rather than a broken one.
        $this->assertSame(['Critical', 'High', 'Medium', 'Low'], array_keys($snapshot['by_priority']));
    }

    public function test_the_pdf_is_a_self_contained_document_that_repeats_byte_for_byte(): void
    {
        $service = $this->service();
        $snapshot = $service->summarise($this->cases(), $this->sessions(), '2025-02-10');

        $pdf = $service->renderPdf($snapshot, 'Test Counselor');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);

        // No embedded fonts: the document must not depend on a font CDN.
        $this->assertStringNotContainsString('/FontFile', $pdf);

        // Two renders of ONE snapshot must be identical, which is what makes the
        // download reproducible.
        $this->assertSame($pdf, $service->renderPdf($snapshot, 'Test Counselor'));
    }
}

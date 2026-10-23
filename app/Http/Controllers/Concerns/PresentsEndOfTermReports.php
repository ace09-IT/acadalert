<?php

namespace App\Http\Controllers\Concerns;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Services\Reports\EndOfTermReportService;
use Illuminate\Support\Facades\DB;

trait PresentsEndOfTermReports
{
    protected function decodeSnapshot(object $report): array
    {
        $decoded = json_decode((string) $report->snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function reportViewData(object $report, array $context = []): array
    {
        $snapshot = $this->decodeSnapshot($report);
        $meta = $snapshot['meta'] ?? [];
        $comparison = $snapshot['comparison'] ?? [];
        $counts = $snapshot['counts_by_level'] ?? ['Low' => 0, 'Moderate' => 0, 'High' => 0];
        $percentages = $snapshot['percentages_by_level'] ?? ['Low' => 0, 'Moderate' => 0, 'High' => 0];

        return array_merge([
            'report' => $report,
            'snapshot' => $snapshot,
            'generatedByName' => app(StaffRepository::class)->userName($report->generated_by) ?? 'Unknown',
            'digest' => (string) $report->digest,
            'comparison' => $comparison,
            'distribution' => $snapshot['distribution'] ?? [],
            'scoreStatistics' => $snapshot['score_statistics'] ?? [],
            'programs' => $snapshot['programs'] ?? [],
            'totals' => $snapshot['totals'] ?? [],
            'periodNumber' => (int) ($meta['period_number'] ?? 0),
            'periodCount' => (int) ($meta['period_count'] ?? 3),
            'formulaVersion' => (string) ($meta['formula_version'] ?? ''),
            'aiUsed' => (bool) ($meta['ai_used'] ?? false),
            'computation' => (string) ($meta['computation'] ?? ''),
            'chart' => [
                'labels' => ['High', 'Moderate', 'Low'],
                'current' => [
                    (int) ($counts['High'] ?? 0),
                    (int) ($counts['Moderate'] ?? 0),
                    (int) ($counts['Low'] ?? 0),
                ],
                'previous' => [
                    (int) ($comparison['previous_counts']['High'] ?? 0),
                    (int) ($comparison['previous_counts']['Moderate'] ?? 0),
                    (int) ($comparison['previous_counts']['Low'] ?? 0),
                ],
                'previousPeriod' => $comparison['previous_period'] ?? null,
                'percentages' => [
                    (float) ($percentages['High'] ?? 0),
                    (float) ($percentages['Moderate'] ?? 0),
                    (float) ($percentages['Low'] ?? 0),
                ],
            ],
        ], $context);
    }

    protected function verifyReport(object $report, EndOfTermReportService $service): array
    {
        $snapshot = $this->decodeSnapshot($report);

        $recomputed = $service->buildSnapshot(
            (int) $report->department_id,
            (string) $report->school_year,
            (string) $report->semester,
            (string) $report->grading_period,
            $snapshot['meta']['generated_by'] ?? null,
            $snapshot['meta']['generated_at'] ?? null
        );

        $recomputedDigest = $service->digest($recomputed);
        $storedDigest = (string) $report->digest;

        return [
            'stored_digest' => $storedDigest,
            'recomputed_digest' => $recomputedDigest,
            'payload_digest_matches' => hash_equals($storedDigest, $service->digest($snapshot)),
            'matches' => hash_equals($storedDigest, $recomputedDigest),
            'ai_used' => $snapshot['meta']['ai_used'] ?? null,
            'external_service_calls' => $snapshot['meta']['external_service_calls'] ?? null,
            'formula_version' => $snapshot['meta']['formula_version'] ?? null,
            'computation' => $snapshot['meta']['computation'] ?? null,
        ];
    }


    protected function sharedRoles(object $report): array
    {
        $decoded = json_decode((string) ($report->shared_roles ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function sharedReports(?int $departmentId, string $role)
    {
        $query = DB::table('end_of_term_reports')
            ->where('end_of_term_reports.is_shared', true);

        if ($departmentId !== null) {
            $query->where('end_of_term_reports.department_id', $departmentId);
        }

        $userNames = $this->userNames();
        $departments = $this->departments();

        return $this->sortReports(
            $query->get()
                ->map(fn ($report) => $this->decorateReport($report, $userNames, $departments))
                ->filter(fn ($report) => in_array($role, $this->sharedRoles($report), true))
        );
    }

    protected function findSharedReport($reportId, ?int $departmentId, string $role): ?object
    {
        if (!is_numeric($reportId)) {
            return null;
        }

        $query = DB::table('end_of_term_reports')
            ->where('end_of_term_reports.id', (int) $reportId)
            ->where('end_of_term_reports.is_shared', true);

        if ($departmentId !== null) {
            $query->where('end_of_term_reports.department_id', $departmentId);
        }

        $report = $query->first();

        if (!$report) {
            return null;
        }

        $report = $this->decorateReport($report, $this->userNames(), $this->departments());

        return in_array($role, $this->sharedRoles($report), true) ? $report : null;
    }


    /** User display names keyed by user id (mock API). */
    protected function userNames(): array
    {
        return app(StaffRepository::class)->userNameIndex();
    }

    /** Department rows keyed by department id (mock API). */
    protected function departments(): array
    {
        return app(AcademicStructureRepository::class)->departments()
            ->keyBy(fn ($department) => (int) $department->id)
            ->all();
    }

    protected function decorateReport(object $report, array $userNames, array $departments): object
    {
        $department = $departments[(int) $report->department_id] ?? null;

        $row = clone $report;
        $row->generated_by_name = $userNames[(int) $report->generated_by] ?? null;
        $row->department_code = $department->code ?? null;
        $row->department_name = $department->name ?? null;

        return $row;
    }

    protected function sortReports($reports)
    {
        $rank = RiskScoreRepository::PERIOD_ORDER;

        return $reports->sort(function ($a, $b) use ($rank) {
            $cmp = strcmp((string) $b->school_year, (string) $a->school_year);

            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = strcmp((string) $b->updated_at, (string) $a->updated_at);

            if ($cmp !== 0) {
                return $cmp;
            }

            return ($rank[(string) $a->grading_period] ?? 99) <=> ($rank[(string) $b->grading_period] ?? 99);
        })->values();
    }
}

<?php

namespace App\Services\Reports;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\CaseRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorActivityReportService
{
    public const FORMULA_VERSION = '1.0.0';

    /** Priority buckets, most severe first — also the report's row order. */
    public const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];

    /** Every case status the workflow can produce, in workflow order. */
    public const STATUSES = [
        'New',
        'In Progress',
        'Awaiting Parent',
        'Awaiting Student',
        'Referred',
        'Resolved',
        'Closed',
        'Reopened',
    ];

    public const CLOSED_STATUSES = ['Resolved', 'Closed'];

    public const RISK_LEVELS = ['High', 'Moderate', 'Low'];

    public const FOLLOW_UP_HORIZON_DAYS = 7;

    public function __construct(
        protected AcademicStructureRepository $structure,
        protected CaseRepository $cases
    ) {
    }

    public function summarise(array $caseRows, array $sessionRows, ?string $asOf = null, array $meta = []): array
    {
        $asOf = $this->normaliseDate($asOf) ?? '2000-01-01';

        $cases = array_map(static fn ($row) => (array) $row, $caseRows);
        $sessions = array_map(static fn ($row) => (array) $row, $sessionRows);

        // Only sessions belonging to a case in scope may contribute. A session
        // is never counted twice and never leaks in from another caseload.
        $caseIds = array_map(static fn ($case) => (int) ($case['id'] ?? 0), $cases);
        $sessions = array_values(array_filter(
            $sessions,
            static fn ($session) => in_array((int) ($session['case_id'] ?? 0), $caseIds, true)
        ));

        $live = array_values(array_filter(
            $cases,
            static fn ($case) => !in_array((string) ($case['status'] ?? ''), self::CLOSED_STATUSES, true)
        ));

        // The covered period is data-derived unless the caller pins it, so the
        // report never silently re-dates itself when it is reopened.
        $windowFrom = $this->normaliseDate($meta['window_from'] ?? null)
            ?? $this->earliest($cases, 'escalated_at')
            ?? $asOf;
        $windowTo = $this->normaliseDate($meta['window_to'] ?? null) ?? $asOf;

        [$overdue, $upcoming, $scheduled] = $this->followUpCompliance($live, $sessions, $asOf);

        $total = count($cases);
        $resolved = $total - count($live);

        return [
            'meta' => [
                'title' => (string) ($meta['title'] ?? 'Counselor Caseload Activity Report'),
                'counselor' => (string) ($meta['counselor'] ?? ''),
                'department' => (string) ($meta['department'] ?? ''),
                'as_of' => $asOf,
                'window_from' => $windowFrom,
                'window_to' => $windowTo,
                'formula_version' => self::FORMULA_VERSION,
                'computation' => 'deterministic-rule-based',
                'ai_used' => false,
                'external_service_calls' => 0,
                'source' => 'cases + case_sessions (local workflow state)',
            ],
            'totals' => [
                'caseload' => $total,
                'open' => count($live),
                'resolved' => $resolved,
                'critical_open' => count(array_filter(
                    $live,
                    static fn ($case) => (string) ($case['priority'] ?? '') === 'Critical'
                )),
                'resolution_rate' => $total > 0 ? round(100 * $resolved / $total, 2) : 0.0,
            ],
            'by_priority' => $this->countsBy($live, 'priority', self::PRIORITIES, true),
            'by_status' => $this->countsBy($cases, 'status', self::STATUSES, true),
            'risk_mix' => $this->countsBy($cases, 'risk_level_at_escalation', self::RISK_LEVELS, true),
            'sessions' => [
                'total' => count($sessions),
                'in_window' => $this->countInWindow($sessions, 'session_date', $windowFrom, $windowTo),
                'cases_contacted' => count(array_unique(array_map(
                    static fn ($session) => (int) ($session['case_id'] ?? 0),
                    $sessions
                ))),
                'average_per_case' => $total > 0 ? round(count($sessions) / $total, 2) : 0.0,
                'by_type' => $this->countsBy($sessions, 'session_type'),
            ],
            'follow_ups' => [
                'scheduled' => $scheduled,
                'overdue' => $overdue,
                'upcoming' => $upcoming,
                'compliance_rate' => $scheduled > 0
                    ? round(100 * ($scheduled - $overdue) / $scheduled, 2)
                    : 100.0,
            ],
            'response' => $this->responseTimes($cases, $sessions),
            'resolutions' => [
                'total' => $resolved,
                'in_window' => $this->countInWindow($cases, 'resolved_at', $windowFrom, $windowTo),
            ],
            'monthly' => $this->monthlyTrend($cases, $sessions),
        ];
    }


    protected function countsBy(array $rows, string $key, array $order = [], bool $keepEmpty = false): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $value = (string) ($row[$key] ?? '');
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        if ($order === []) {
            ksort($counts);

            return $counts;
        }

        $ordered = [];
        foreach ($order as $value) {
            $ordered[$value] = (int) ($counts[$value] ?? 0);
        }

        return $keepEmpty ? $ordered : array_filter($ordered, static fn ($count) => $count > 0);
    }

    protected function countInWindow(array $rows, string $key, string $from, string $to): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $date = $this->normaliseDate($row[$key] ?? null);

            if ($date !== null && $date >= $from && $date <= $to) {
                $count++;
            }
        }

        return $count;
    }

    /** Earliest date in a column, or null when the column is empty/undated. */
    protected function earliest(array $rows, string $key): ?string
    {
        $dates = $this->dates($rows, $key);
        sort($dates);

        return $dates[0] ?? null;
    }

    protected function latest(array $rows, string $key): ?string
    {
        $dates = $this->dates($rows, $key);
        sort($dates);

        return $dates === [] ? null : $dates[count($dates) - 1];
    }

    protected function dates(array $rows, string $key): array
    {
        $dates = [];

        foreach ($rows as $row) {
            $date = $this->normaliseDate($row[$key] ?? null);

            if ($date !== null) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /** Normalise any stored date/datetime to Y-m-d, or null when unparseable. */
    protected function normaliseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }


    protected function responseTimes(array $cases, array $sessions): array
    {
        $firstSession = [];

        foreach ($sessions as $session) {
            $caseId = (int) ($session['case_id'] ?? 0);
            $date = $this->normaliseDate($session['session_date'] ?? null);

            if ($date === null) {
                continue;
            }

            if (!isset($firstSession[$caseId]) || $date < $firstSession[$caseId]) {
                $firstSession[$caseId] = $date;
            }
        }

        $days = [];

        foreach ($cases as $case) {
            $caseId = (int) ($case['id'] ?? 0);
            $escalated = $this->normaliseDate($case['escalated_at'] ?? null);
            $first = $firstSession[$caseId] ?? null;

            if ($escalated === null || $first === null) {
                continue;
            }

            $days[] = max(0, (int) Carbon::parse($escalated)->diffInDays(Carbon::parse($first), false));
        }

        sort($days);
        $count = count($days);

        return [
            'cases_contacted' => $count,
            'cases_uncontacted' => count($cases) - $count,
            'average_days' => $count > 0 ? round(array_sum($days) / $count, 1) : 0.0,
            'median_days' => $count > 0 ? $this->median($days) : 0.0,
            'fastest_days' => $count > 0 ? $days[0] : null,
            'slowest_days' => $count > 0 ? $days[$count - 1] : null,
        ];
    }

    protected function median(array $sorted): float
    {
        $count = count($sorted);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $sorted[$middle];
        }

        return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 1);
    }

    protected function followUpCompliance(array $liveCases, array $sessions, string $asOf): array
    {
        $liveIds = array_map(static fn ($case) => (int) ($case['id'] ?? 0), $liveCases);

        $latestFollowUp = [];
        $latestSession = [];

        foreach ($sessions as $session) {
            $caseId = (int) ($session['case_id'] ?? 0);

            if (!in_array($caseId, $liveIds, true)) {
                continue;
            }

            $due = $this->normaliseDate($session['follow_up_date'] ?? null);

            if ($due !== null && (!isset($latestFollowUp[$caseId]) || $due > $latestFollowUp[$caseId])) {
                $latestFollowUp[$caseId] = $due;
            }

            $held = $this->normaliseDate($session['session_date'] ?? null);

            if ($held !== null && (!isset($latestSession[$caseId]) || $held > $latestSession[$caseId])) {
                $latestSession[$caseId] = $held;
            }
        }

        $horizon = Carbon::parse($asOf)->addDays(self::FOLLOW_UP_HORIZON_DAYS)->toDateString();

        $overdue = 0;
        $upcoming = 0;

        foreach ($latestFollowUp as $caseId => $due) {
            if ($due > $asOf) {
                if ($due <= $horizon) {
                    $upcoming++;
                }

                continue;
            }

            $held = $latestSession[$caseId] ?? null;

            if ($held === null || $held <= $due) {
                $overdue++;
            }
        }

        return [$overdue, $upcoming, count($latestFollowUp)];
    }

    protected function monthlyTrend(array $cases, array $sessions): array
    {
        $buckets = [];

        $touch = static function (array &$buckets, ?string $month) {
            if ($month !== null) {
                $buckets[$month] ??= ['month' => $month, 'escalated' => 0, 'resolved' => 0, 'sessions' => 0];
            }
        };

        foreach ($cases as $case) {
            $escalated = $this->month($case['escalated_at'] ?? null);
            $touch($buckets, $escalated);

            if ($escalated !== null) {
                $buckets[$escalated]['escalated']++;
            }

            $resolved = $this->month($case['resolved_at'] ?? null);
            $touch($buckets, $resolved);

            if ($resolved !== null) {
                $buckets[$resolved]['resolved']++;
            }
        }

        foreach ($sessions as $session) {
            $month = $this->month($session['session_date'] ?? null);
            $touch($buckets, $month);

            if ($month !== null) {
                $buckets[$month]['sessions']++;
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /** Normalise any stored date/datetime to 'Y-m', or null. */
    protected function month($value): ?string
    {
        $date = $this->normaliseDate($value);

        return $date === null ? null : substr($date, 0, 7);
    }


    public function buildSnapshot(
        int $counselorId,
        ?int $departmentId = null,
        array $filters = [],
        ?string $asOf = null,
        string $counselorName = ''
    ): array {
        $studentIds = $departmentId
            ? $this->structure->studentIdsInDepartment($departmentId, false)
            : array_keys($this->structure->studentPlacements());

        $query = DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds);

        if (!empty($filters['school_year'])) {
            $query->where('cases.school_year', (string) $filters['school_year']);
        }

        if (!empty($filters['priority'])) {
            $query->where('cases.priority', (string) $filters['priority']);
        }

        if (!empty($filters['status'])) {
            $query->where('cases.status', (string) $filters['status']);
        }

        $from = $this->normaliseDate($filters['from'] ?? null);
        $to = $this->normaliseDate($filters['to'] ?? null);

        if ($from !== null) {
            $query->whereDate('cases.escalated_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('cases.escalated_at', '<=', $to);
        }

        $caseRows = $query
            ->orderBy('cases.escalated_at')
            ->orderBy('cases.id')
            ->get([
                'cases.id',
                'cases.student_id',
                'cases.status',
                'cases.priority',
                'cases.risk_level_at_escalation',
                'cases.escalated_at',
                'cases.resolved_at',
                'cases.school_year',
            ])
            ->all();

        $caseIds = array_map(static fn ($row) => (int) $row->id, $caseRows);

        $sessionRows = $caseIds === []
            ? []
            : DB::table('case_sessions')
                ->whereIn('case_id', $caseIds)
                ->orderBy('session_date')
                ->orderBy('id')
                ->get(['case_id', 'session_date', 'session_type', 'status_after_session', 'follow_up_date'])
                ->all();

        $department = $departmentId ? $this->structure->department($departmentId) : null;

        return $this->summarise($caseRows, $sessionRows, $asOf, [
            'counselor' => $counselorName,
            'department' => $department !== null
                ? trim((string) $department->code . ' - ' . (string) $department->name, ' -')
                : '',
            'window_from' => $from,
            'window_to' => $to,
        ]);
    }

    public function filterOptions(int $counselorId, ?int $departmentId = null): array
    {
        $studentIds = $departmentId
            ? $this->structure->studentIdsInDepartment($departmentId, false)
            : array_keys($this->structure->studentPlacements());

        $years = DB::table('cases')
            ->where('cases.counselor_id', $counselorId)
            ->whereIn('cases.student_id', $studentIds)
            ->whereNotNull('cases.school_year')
            ->distinct()
            ->pluck('cases.school_year')
            ->map(static fn ($year) => (string) $year)
            ->sort()
            ->values()
            ->reverse()
            ->values()
            ->all();

        return [
            'school_years' => $years,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
        ];
    }


    public function digest(array $snapshot): string
    {
        $canonical = $snapshot;
        $this->canonicalise($canonical);

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Recursively sort associative keys so the encoding is order-independent. */
    protected function canonicalise(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->canonicalise($value);
            }
        }

        unset($value);

        if (!array_is_list($data)) {
            ksort($data);
        }
    }

    protected function tableRows(array $counts): array
    {
        $rows = [];

        foreach ($counts as $label => $count) {
            $rows[] = [(string) $label, number_format((int) $count)];
        }

        return $rows;
    }


    public function renderPdf(array $snapshot, string $counselorName = ''): string
    {
        $meta = $snapshot['meta'] ?? [];
        $totals = $snapshot['totals'] ?? [];
        $sessions = $snapshot['sessions'] ?? [];
        $followUps = $snapshot['follow_ups'] ?? [];
        $response = $snapshot['response'] ?? [];

        $for = $counselorName !== '' ? $counselorName : (string) ($meta['counselor'] ?? 'Guidance Counselor');

        $pdf = new PdfReportWriter(
            (string) ($meta['title'] ?? 'Counselor Caseload Activity Report'),
            $for,
            'Guidance Counselor caseload activity summary',
            new \DateTimeImmutable(
                (string) ($meta['as_of'] ?? '2000-01-01') . ' 00:00:00',
                new \DateTimeZone('UTC')
            )
        );

        $pdf->setFooter('AcadAlert — Guidance Counselor report — deterministic, rule-based. Digest '
            . substr($this->digest($snapshot), 0, 16));

        $pdf->heading((string) ($meta['title'] ?? 'Counselor Caseload Activity Report'));
        $pdf->paragraph('Prepared for ' . $for . '.');
        $pdf->rule();

        $pdf->keyValues([
            'Department' => (string) ($meta['department'] ?? 'n/a'),
            'Period covered' => (string) ($meta['window_from'] ?? '') . ' to ' . (string) ($meta['window_to'] ?? ''),
            'As of' => (string) ($meta['as_of'] ?? ''),
            'Computation' => (string) ($meta['computation'] ?? '') . ' (v' . (string) ($meta['formula_version'] ?? '') . ')',
            'Source' => (string) ($meta['source'] ?? ''),
        ]);

        $pdf->heading('1. Caseload Summary', 12);
        $pdf->table(
            ['Measure', 'Value'],
            [
                ['Total caseload', number_format((int) ($totals['caseload'] ?? 0))],
                ['Open cases', number_format((int) ($totals['open'] ?? 0))],
                ['Resolved / closed cases', number_format((int) ($totals['resolved'] ?? 0))],
                ['Critical priority (open)', number_format((int) ($totals['critical_open'] ?? 0))],
                ['Resolution rate', number_format((float) ($totals['resolution_rate'] ?? 0), 2) . '%'],
            ],
            [3.0, 3.0],
            ['left', 'right']
        );

        $pdf->heading('2. Open Cases by Priority', 12);
        $pdf->table(['Priority', 'Cases'], $this->tableRows($snapshot['by_priority'] ?? []), [3.0, 3.0], ['left', 'right']);

        $pdf->heading('3. Cases by Status', 12);
        $pdf->table(['Status', 'Cases'], $this->tableRows($snapshot['by_status'] ?? []), [3.0, 3.0], ['left', 'right']);

        $pdf->heading('4. Contact Volume', 12);
        $pdf->keyValues([
            'Sessions logged' => number_format((int) ($sessions['total'] ?? 0)),
            'Sessions inside the period' => number_format((int) ($sessions['in_window'] ?? 0)),
            'Cases with at least one session' => number_format((int) ($sessions['cases_contacted'] ?? 0)),
            'Average sessions per case' => number_format((float) ($sessions['average_per_case'] ?? 0), 2),
        ]);

        $byType = $this->tableRows($sessions['by_type'] ?? []);

        if ($byType !== []) {
            $pdf->table(['Session type', 'Count'], $byType, [3.0, 3.0], ['left', 'right']);
        }

        return $this->finishPdf($pdf, $snapshot);
    }

    protected function finishPdf(PdfReportWriter $pdf, array $snapshot): string
    {
        $meta = $snapshot['meta'] ?? [];
        $followUps = $snapshot['follow_ups'] ?? [];
        $response = $snapshot['response'] ?? [];

        $pdf->heading('5. Follow-Up Compliance', 12);
        $pdf->keyValues([
            'Cases with a scheduled follow-up' => number_format((int) ($followUps['scheduled'] ?? 0)),
            'Overdue follow-ups' => number_format((int) ($followUps['overdue'] ?? 0)),
            'Follow-ups due within ' . self::FOLLOW_UP_HORIZON_DAYS . ' days' => number_format((int) ($followUps['upcoming'] ?? 0)),
            'Compliance rate' => number_format((float) ($followUps['compliance_rate'] ?? 0), 2) . '%',
        ]);

        $pdf->heading('6. Response Time (escalation to first session)', 12);
        $pdf->keyValues([
            'Cases contacted' => number_format((int) ($response['cases_contacted'] ?? 0)),
            'Cases still awaiting first contact' => number_format((int) ($response['cases_uncontacted'] ?? 0)),
            'Average response' => number_format((float) ($response['average_days'] ?? 0), 1) . ' day(s)',
            'Median response' => number_format((float) ($response['median_days'] ?? 0), 1) . ' day(s)',
            'Fastest / slowest' => $this->range($response),
        ]);

        $monthly = array_values($snapshot['monthly'] ?? []);

        if ($monthly !== []) {
            $pdf->heading('7. Monthly Activity', 12);
            $pdf->table(
                ['Month', 'Escalated', 'Resolved', 'Sessions'],
                array_map(static fn ($row) => [
                    (string) ($row['month'] ?? ''),
                    number_format((int) ($row['escalated'] ?? 0)),
                    number_format((int) ($row['resolved'] ?? 0)),
                    number_format((int) ($row['sessions'] ?? 0)),
                ], $monthly),
                [2.4, 1.2, 1.2, 1.2],
                ['left', 'right', 'right', 'right']
            );
        }

        $pdf->heading('8. Report Provenance', 12);
        $pdf->keyValues([
            'Formula version' => 'v' . (string) ($meta['formula_version'] ?? ''),
            'Generative AI used' => !empty($meta['ai_used']) ? 'YES' : 'NO',
            'External service calls' => number_format((int) ($meta['external_service_calls'] ?? 0)),
            'Snapshot SHA-256' => $this->digest($snapshot),
        ]);

        $pdf->paragraph(
            'This report was compiled by deterministic, rule-based aggregation of this counselor\'s own case'
            . ' and session records. No generative AI, language model or external service was invoked in its'
            . ' production, and re-running the same aggregation over the same data reproduces this document'
            . ' byte-for-byte. It summarises one counselor\'s caseload activity only; consolidated department'
            . ' end-of-term reports remain the Academic Head\'s to generate and share.'
        );

        return $pdf->output();
    }

    protected function range(array $response): string
    {
        if (($response['fastest_days'] ?? null) === null || ($response['slowest_days'] ?? null) === null) {
            return 'n/a';
        }

        return (string) $response['fastest_days'] . ' / ' . (string) $response['slowest_days'] . ' day(s)';
    }
}

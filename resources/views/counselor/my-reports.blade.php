@extends('layouts.app')

@section('title', 'My Reports - AcadAlert')

@section('page_title', 'My Reports — Caseload Activity')

@php
    $meta = $snapshot['meta'] ?? [];
    $totals = $snapshot['totals'] ?? [];
    $sessions = $snapshot['sessions'] ?? [];
    $followUps = $snapshot['follow_ups'] ?? [];
    $response = $snapshot['response'] ?? [];

    // Chart payloads. Each becomes [{label, value}] so the client-side renderer
    // stays a dumb loop, and so the JSON stays stable for snapshot comparisons.
    $priorityChart = collect($snapshot['by_priority'] ?? [])
        ->map(fn ($count, $label) => ['label' => (string) $label, 'value' => (int) $count])
        ->values();
    $statusChart = collect($snapshot['by_status'] ?? [])
        ->map(fn ($count, $label) => ['label' => (string) $label, 'value' => (int) $count])
        ->values();
    $monthlyChart = collect($snapshot['monthly'] ?? [])->values();

    // The export must reproduce exactly what is on screen, so the active filters
    // travel with the download link.
    $pdfQuery = array_filter($filters ?? [], fn ($value) => $value !== '' && $value !== null);
@endphp

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-secondary text-white p-2">
            <i class="fas fa-user-tie me-1"></i> {{ auth()->user()->name }}
        </span>
        @if(!empty($department))
            <span class="badge bg-primary text-white p-2">
                <i class="fas fa-building me-1"></i> {{ $department->code ?? '' }}
            </span>
        @endif
        <a href="{{ route('counselor.reports') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-file-alt me-1"></i> Department Reports
        </a>
        <a href="{{ route('counselor.reports.mine.pdf', $pdfQuery) }}" class="btn btn-sm btn-danger">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page gc-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">
<div class="alert alert-light border ah-reveal" style="--ah-i: 0;">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <i class="fas fa-calculator text-primary me-2"></i>
            Deterministic, rule-based summary of <strong>your own</strong> caseload.
            Period covered:
            <strong>{{ $meta['window_from'] ?? '' }}</strong> to <strong>{{ $meta['window_to'] ?? '' }}</strong>
            (as of {{ $meta['as_of'] ?? '' }}).
        </div>
        <div class="small text-muted">
            <i class="fas fa-fingerprint me-1"></i>
            Digest <code>{{ substr((string) $digest, 0, 16) }}</code>
            &middot; v{{ $meta['formula_version'] ?? '' }}
            &middot; AI used: {{ !empty($meta['ai_used']) ? 'yes' : 'no' }}
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Caseload</div>
                    <div class="stat-number">{{ number_format((int) ($totals['caseload'] ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((int) ($totals['critical_open'] ?? 0)) }} critical open</small>
                </div>
                <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Open Cases</div>
                    <div class="stat-number">{{ number_format((int) ($totals['open'] ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((int) ($sessions['cases_contacted'] ?? 0)) }} case(s) contacted</small>
                </div>
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Resolved</div>
                    <div class="stat-number">{{ number_format((int) ($totals['resolved'] ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((float) ($totals['resolution_rate'] ?? 0), 2) }}% resolution rate</small>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card {{ (int) ($followUps['overdue'] ?? 0) > 0 ? 'danger' : 'warning' }} h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Overdue Follow-ups</div>
                    <div class="stat-number">{{ number_format((int) ($followUps['overdue'] ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((float) ($followUps['compliance_rate'] ?? 0), 2) }}% compliance</small>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-exclamation"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 2;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter text-primary"></i> Narrow the Report</span>
        @if($filtersApplied)
            <span class="badge bg-warning text-dark">filters active</span>
        @endif
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('counselor.reports.mine') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1" for="reportYear">School Year</label>
                <select class="form-select form-select-sm" id="reportYear" name="school_year">
                    <option value="">All school years</option>
                    @foreach($options['school_years'] ?? [] as $year)
                        <option value="{{ $year }}" @selected(($filters['school_year'] ?? '') === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="reportPriority">Priority</label>
                <select class="form-select form-select-sm" id="reportPriority" name="priority">
                    <option value="">All priorities</option>
                    @foreach($options['priorities'] ?? [] as $priorityOption)
                        <option value="{{ $priorityOption }}" @selected(($filters['priority'] ?? '') === $priorityOption)>{{ $priorityOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="reportStatus">Status</label>
                <select class="form-select form-select-sm" id="reportStatus" name="status">
                    <option value="">All statuses</option>
                    @foreach($options['statuses'] ?? [] as $statusOption)
                        <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="reportFrom">Escalated from</label>
                <input type="date" class="form-control form-control-sm" id="reportFrom" name="from"
                       value="{{ $filters['from'] ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="reportTo">Escalated to</label>
                <input type="date" class="form-control form-control-sm" id="reportTo" name="to"
                       value="{{ $filters['to'] ?? '' }}">
            </div>

            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary" title="Apply filters">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            @if($filtersApplied)
                <div class="col-12">
                    <a href="{{ route('counselor.reports.mine') }}" class="btn btn-sm btn-link px-0">
                        <i class="fas fa-times me-1"></i> Clear filters
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 3;">
    <div class="col-xl-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-pie text-primary"></i> Open Cases by Priority
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="myPriorityChart" data-chart="{{ $priorityChart->toJson() }}"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary"></i> Cases by Status
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="myStatusChart" data-chart="{{ $statusChart->toJson() }}"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 4;">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i> Monthly Activity
                <small class="text-muted ms-2">cases escalated, cases resolved, sessions logged</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="myMonthlyChart" data-chart="{{ $monthlyChart->toJson() }}"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 5;">
    <div class="col-lg-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-phone text-primary"></i> Contact Volume</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Sessions logged</span>
                        <span class="fw-semibold">{{ number_format((int) ($sessions['total'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Sessions in period</span>
                        <span class="fw-semibold">{{ number_format((int) ($sessions['in_window'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Cases with a session</span>
                        <span class="fw-semibold">{{ number_format((int) ($sessions['cases_contacted'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Cases never contacted</span>
                        <span class="fw-semibold text-danger">{{ number_format((int) ($response['cases_uncontacted'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">Average sessions per case</span>
                        <span class="fw-semibold">{{ number_format((float) ($sessions['average_per_case'] ?? 0), 2) }}</span>
                    </li>
                </ul>

                @if(count($sessions['by_type'] ?? []) > 0)
                    <hr>
                    <div class="text-muted small fw-bold mb-2">Sessions by type</div>
                    @foreach($sessions['by_type'] as $typeLabel => $typeCount)
                        <div class="d-flex justify-content-between small">
                            <span>{{ $typeLabel }}</span>
                            <span class="fw-semibold">{{ number_format((int) $typeCount) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-stopwatch text-primary"></i> Response Time</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="display-5 fw-bold">{{ number_format((float) ($response['average_days'] ?? 0), 1) }}</div>
                    <div class="text-muted small">average day(s) from escalation to first session</div>
                </div>
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Median</span>
                        <span class="fw-semibold">{{ number_format((float) ($response['median_days'] ?? 0), 1) }} day(s)</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Fastest</span>
                        <span class="fw-semibold">{{ $response['fastest_days'] === null ? 'n/a' : $response['fastest_days'] . ' day(s)' }}</span>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">Slowest</span>
                        <span class="fw-semibold">{{ $response['slowest_days'] === null ? 'n/a' : $response['slowest_days'] . ' day(s)' }}</span>
                    </li>
                </ul>
                <div class="form-text">
                    Cases still awaiting first contact are excluded rather than counted as zero.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-calendar-check text-primary"></i> Follow-Up Compliance</div>
            <div class="card-body">
                @php
                    $compliance = (float) ($followUps['compliance_rate'] ?? 0);
                    $complianceTone = $compliance >= 90 ? 'success' : ($compliance >= 70 ? 'warning' : 'danger');
                @endphp

                <div class="text-center mb-3">
                    <div class="display-5 fw-bold text-{{ $complianceTone }}">
                        {{ number_format($compliance, 2) }}%
                    </div>
                    <div class="text-muted small">of scheduled follow-ups honoured</div>
                </div>

                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Cases with a follow-up</span>
                        <span class="fw-semibold">{{ number_format((int) ($followUps['scheduled'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">Overdue</span>
                        <span class="fw-semibold text-danger">{{ number_format((int) ($followUps['overdue'] ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">Due within 7 days</span>
                        <span class="fw-semibold">{{ number_format((int) ($followUps['upcoming'] ?? 0)) }}</span>
                    </li>
                </ul>

                <div class="form-text">
                    A follow-up counts as outstanding only until a later session satisfies it.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 6;">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-flag text-primary"></i> Open Cases by Priority</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Priority</th><th class="text-end">Open Cases</th></tr>
                        </thead>
                        <tbody>
                            @forelse($snapshot['by_priority'] ?? [] as $priorityLabel => $priorityCount)
                                <tr>
                                    <td>{{ $priorityLabel }}</td>
                                    <td class="text-end fw-semibold">{{ number_format((int) $priorityCount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted text-center py-3">No open cases.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-circle-info text-primary"></i> Cases by Status</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Status</th><th class="text-end">Cases</th></tr>
                        </thead>
                        <tbody>
                            @forelse($snapshot['by_status'] ?? [] as $statusLabel => $statusCount)
                                <tr>
                                    <td>{{ $statusLabel }}</td>
                                    <td class="text-end fw-semibold">{{ number_format((int) $statusCount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted text-center py-3">No cases recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 7;">
    <div class="col-lg-8 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-table-columns text-primary"></i> Monthly Activity</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Escalated</th>
                                <th class="text-end">Resolved</th>
                                <th class="text-end">Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($monthlyChart as $monthRow)
                                <tr>
                                    <td>{{ $monthRow['month'] ?? '' }}</td>
                                    <td class="text-end">{{ number_format((int) ($monthRow['escalated'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($monthRow['resolved'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($monthRow['sessions'] ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No dated activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header"><i class="fas fa-triangle-exclamation text-primary"></i> Risk Mix at Escalation</div>
            <div class="card-body">
                @php $riskMixTotal = array_sum(array_map('intval', $snapshot['risk_mix'] ?? [])); @endphp

                @forelse($snapshot['risk_mix'] ?? [] as $riskLabel => $riskCount)
                    @php
                        $riskShare = $riskMixTotal > 0 ? (int) round((100 * (int) $riskCount) / $riskMixTotal) : 0;
                        $riskBar = $riskLabel === 'High' ? 'danger' : ($riskLabel === 'Moderate' ? 'warning' : 'success');
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span class="fw-semibold">{{ $riskLabel }}</span>
                            <span class="text-muted">{{ number_format((int) $riskCount) }} ({{ $riskShare }}%)</span>
                        </div>
                        <div class="progress mt-1" style="height: 8px;">
                            <div class="progress-bar bg-{{ $riskBar }}" style="width: {{ $riskShare }}%;"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No risk levels recorded on these cases.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 8;">
    <div class="card-header"><i class="fas fa-shield-halved text-primary"></i> Report Provenance</div>
    <div class="card-body">
        <div class="row small">
            <div class="col-md-4 mb-2">
                <span class="text-muted d-block">Computation</span>
                <span class="fw-semibold">{{ $meta['computation'] ?? '' }} (v{{ $meta['formula_version'] ?? '' }})</span>
            </div>
            <div class="col-md-4 mb-2">
                <span class="text-muted d-block">Source</span>
                <span class="fw-semibold">{{ $meta['source'] ?? '' }}</span>
            </div>
            <div class="col-md-4 mb-2">
                <span class="text-muted d-block">External service calls / AI</span>
                <span class="fw-semibold">
                    {{ number_format((int) ($meta['external_service_calls'] ?? 0)) }} / {{ !empty($meta['ai_used']) ? 'yes' : 'no' }}
                </span>
            </div>
            <div class="col-12">
                <span class="text-muted d-block">Snapshot SHA-256</span>
                <code class="small text-break">{{ $digest }}</code>
            </div>
        </div>
        <div class="form-text mt-2">
            Compiled by deterministic, rule-based aggregation of your own case and session records.
            Re-running it over the same data reproduces these figures — and the exported PDF — exactly.
        </div>
    </div>
</div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/counselor-reports-charts.js') }}"></script>
@endpush

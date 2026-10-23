@extends('layouts.app')

@section('title', 'Department Overview - AcadAlert')

@section('page_title', 'Department Overview')

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-primary text-white p-2">
            <i class="fas fa-building me-1"></i> {{ $departmentCode ?? '' }} - {{ $departmentName ?? 'Department' }}
        </span>

        <form method="GET" action="{{ route('academic-head.department') }}" class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" name="period" onchange="this.form.submit()" aria-label="Grading period">
                @foreach($periods as $p)
                    <option value="{{ $p }}" @selected($currentPeriod === $p)>{{ $p }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" name="school_year" onchange="this.form.submit()" aria-label="School year">
                @foreach($schoolYears as $year)
                    <option value="{{ $year }}" @selected($schoolYear === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </form>

        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboardBtn">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
@php
    $attention = collect($attention ?? []);
    $recentEscalations = collect($recentEscalations ?? []);
    $pending = $pendingRecommendations ?? ['total' => 0, 'acted' => 0, 'pending' => 0, 'rows' => []];
@endphp

<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="row ah-reveal" style="--ah-i: 0;">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-number">{{ number_format((int) ($totalStudents ?? 0)) }}</div>
                    <small class="opacity-75">
                        {{ number_format((int) ($unmonitoredCount ?? 0)) }} without a {{ $currentPeriod }} score
                    </small>
                </div>
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk</div>
                    <div class="stat-number">{{ number_format((int) ($highRiskCount ?? 0)) }}</div>
                    <small class="opacity-75">{{ $currentPeriod }} &middot; needs escalation</small>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Moderate Risk</div>
                    <div class="stat-number">{{ number_format((int) ($moderateRiskCount ?? 0)) }}</div>
                    <small class="opacity-75">one period from the High band</small>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Low Risk</div>
                    <div class="stat-number">{{ number_format((int) ($lowRiskCount ?? 0)) }}</div>
                    <small class="opacity-75">no intervention required</small>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="fas fa-chart-bar text-primary"></i> Risk Distribution by Program</span>
                <span class="badge bg-secondary">{{ $currentPeriod }}</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="riskByProgramChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="fas fa-layer-group text-primary"></i> Risk Distribution by Block</span>
                <span class="badge bg-secondary">
                    {{ $blocks instanceof \Illuminate\Support\Collection ? $blocks->count() : count($blocks) }} blocks
                </span>
            </div>
            <div class="card-body">
                @if(empty($blocks))
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-1"></i> No blocks are placed in this department yet.
                    </p>
                @else
                    <div class="chart-container">
                        <canvas id="riskByBlockChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="fas fa-chart-line text-primary"></i> Department Risk Trend</span>
                <span class="badge bg-secondary">Prelim &rarr; Midterm &rarr; Finals</span>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Monitored</th>
                                <th class="text-end">High</th>
                                <th class="text-end">High %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trendData as $point)
                                <tr class="{{ $point['period'] === $currentPeriod ? 'table-active' : '' }}">
                                    <td class="fw-semibold">{{ $point['period'] }}</td>
                                    <td class="text-end">{{ number_format((int) $point['total_students']) }}</td>
                                    <td class="text-end text-danger">{{ number_format((int) $point['high_risk_count']) }}</td>
                                    <td class="text-end">{{ number_format((float) $point['high_risk_percentage'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- CHANGED: Use the shared responsive chart stage height. --}}
                <div class="chart-container">
                    <canvas id="departmentTrendChart" data-chart='@json($trendData)'></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="fas fa-arrow-up-right-dots text-primary"></i> Escalation Trend</span>
                <span class="badge bg-secondary">{{ $schoolYear }}</span>
            </div>
            <div class="card-body">
                <div class="alert alert-light border small">
                    <i class="fas fa-circle-info me-1"></i>
                    Escalations are counted from the hand-off records, so this line shows when the
                    department actually acted on its High-risk students.
                </div>
                <div class="chart-container">
                    <canvas id="escalationTrendChart" data-chart='@json($escalationTrend)'></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 3;">
    <div class="col-xl-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="fas fa-triangle-exclamation text-danger"></i> Students Needing Attention
                    <span class="badge bg-danger ms-2">{{ $attention->count() }}</span>
                </span>
                <span class="text-muted small">Highest risk scores for {{ $currentPeriod }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-mobile-cards">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th class="text-end">Score</th>
                                <th class="text-end">Avg Grade</th>
                                <th class="text-end">Attendance</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attention as $student)
                                <tr>
                                    <td data-label="Student">
                                        <div class="fw-semibold">{{ $student['name'] }}</div>
                                        <small class="text-muted">
                                            {{ $student['student_number'] }}
                                            @if($student['program_code'])
                                                &middot; {{ $student['program_code'] }}
                                            @endif
                                            @if($student['block_name'])
                                                &middot; {{ $student['block_name'] }}
                                            @endif
                                        </small>
                                    </td>
                                    <td class="text-end" data-label="Score">
                                        <span class="badge badge-risk-high">{{ number_format((int) $student['risk_score']) }}</span>
                                    </td>
                                    <td class="text-end {{ ($student['grade'] ?? 100) < 75 ? 'text-danger fw-bold' : '' }}" data-label="Avg Grade">
                                        {{ $student['grade'] === null ? 'n/a' : number_format((float) $student['grade'], 2) }}
                                    </td>
                                    <td class="text-end {{ ($student['attendance'] ?? 100) < 80 ? 'text-warning' : '' }}" data-label="Attendance">
                                        {{ $student['attendance'] === null ? 'n/a' : number_format((float) $student['attendance'], 2) . '%' }}
                                    </td>
                                    <td data-label="Status">
                                        @if($student['has_open_case'])
                                            <span class="badge bg-info"><i class="fas fa-folder-open me-1"></i> Escalated</span>
                                        @else
                                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Not escalated</span>
                                        @endif
                                    </td>
                                    <td class="text-end" data-label="Action">
                                        @if($student['block_id'])
                                            <a href="{{ route('academic-head.block', ['blockId' => $student['block_id'], 'period' => $currentPeriod, 'school_year' => $schoolYear]) }}"
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-arrow-up me-1"></i> Review
                                            </a>
                                        @else
                                            <span class="text-muted small">no block</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                                        No risk scores recorded for {{ $currentPeriod }} yet. Run risk scoring to populate this list.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="fas fa-clock-rotate-left text-primary"></i> Recent Escalations
                    <span class="badge bg-secondary ms-2">{{ $recentEscalations->count() }}</span>
                </span>
                @if((int) ($counters['pending_escalations'] ?? 0) > 0)
                    <span class="badge bg-warning text-dark">
                        {{ number_format((int) $counters['pending_escalations']) }} still open
                    </span>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentEscalations as $escalation)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $escalation['name'] }}</div>
                                    <small class="text-muted">
                                        {{ $escalation['grading_period'] }}
                                        @if($escalation['program_code'])
                                            &middot; {{ $escalation['program_code'] }}
                                        @endif
                                        @if($escalation['block_name'])
                                            &middot; {{ $escalation['block_name'] }}
                                        @endif
                                    </small>
                                </div>
                                <div class="text-end">
                                    @if($escalation['status'])
                                        <span class="badge bg-secondary">{{ $escalation['status'] }}</span>
                                    @endif
                                    @if($escalation['priority'])
                                        <div class="small text-muted">{{ $escalation['priority'] }} priority</div>
                                    @endif
                                    <div class="small text-muted">{{ $escalation['escalated_at'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                            No students have been escalated to the Guidance Counselor yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 4;">
    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class=" text-primary"></i> Pending Recommendations
                    <span class="badge bg-info text-dark ms-2">{{ number_format((int) $pending['pending']) }}</span>
                </span>
                <a href="{{ route('academic-head.recommendations', ['period' => $currentPeriod]) }}"
                   class="btn btn-sm btn-outline-primary">Open workspace</a>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    {{ number_format((int) $pending['total']) }} recommendation(s) exist for {{ $currentPeriod }};
                    {{ number_format((int) $pending['acted']) }} have been acted upon (escalated or completed) and
                    <strong>{{ number_format((int) $pending['pending']) }}</strong> are still waiting.
                </p>
                <div class="list-group list-group-flush">
                    @forelse($pending['rows'] as $row)
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $row['name'] }}</div>
                                    <small class="text-muted">
                                        {{ $row['grading_period'] }}
                                        @if($row['program_code'])
                                            &middot; {{ $row['program_code'] }}
                                        @endif
                                        @if($row['block_name'])
                                            &middot; {{ $row['block_name'] }}
                                        @endif
                                    </small>
                                </div>
                                <small class="text-muted">{{ $row['created_at'] }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                            Every {{ $currentPeriod }} recommendation has been acted upon.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="card h-100 border-{{ count($alerts) > 0 ? 'danger' : 'success' }}">
            <div class="card-header bg-{{ count($alerts) > 0 ? 'danger' : 'success' }} text-white">
                <i class="fas fa-bell me-2"></i>
                {{ count($alerts) > 0 ? 'Critical Alerts' : 'All Clear' }}
            </div>
            <div class="card-body">
                @if(count($alerts) > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($alerts as $alert)
                            <li class="py-2 border-bottom {{ $loop->last ? 'border-0' : '' }}">
                                <span class="badge {{ $alert['severity'] === 'critical' ? 'bg-danger' : 'bg-warning text-dark' }} me-2">
                                    {{ $alert['severity'] === 'critical' ? 'CRITICAL' : 'WARNING' }}
                                </span>
                                {{ $alert['message'] }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        No critical alerts at this time. All programs are within their expected risk range.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 5;">
    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-sitemap text-primary"></i> Program Summary
                <span class="badge bg-secondary ms-2">{{ count($programs) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th class="text-end">Students</th>
                                <th class="text-end">High</th>
                                <th class="text-end">Moderate</th>
                                <th class="text-end">Low</th>
                                <th class="text-end">High %</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programs as $program)
                                <tr>
                                    <td class="fw-bold">{{ $program->code }}</td>
                                    <td class="text-end">{{ number_format((int) ($program->student_count ?? 0)) }}</td>
                                    <td class="text-end">
                                        <span class="badge badge-risk-high">{{ number_format((int) ($program->high_risk ?? 0)) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge badge-risk-moderate">{{ number_format((int) ($program->moderate_risk ?? 0)) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge badge-risk-low">{{ number_format((int) ($program->low_risk ?? 0)) }}</span>
                                    </td>
                                    <td class="text-end {{ $program->high_risk_percentage > 15 ? 'text-danger fw-bold' : ($program->high_risk_percentage > 8 ? 'text-warning' : 'text-success') }}">
                                        {{ number_format((float) $program->high_risk_percentage, 1) }}%
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('academic-head.blocks', ['programId' => $program->id]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> Blocks
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i> No programs found in this department.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="fas fa-layer-group text-primary"></i> Block Risk Ranking
                    <span class="badge bg-secondary ms-2">{{ count($blocks) }}</span>
                </span>
                <a href="{{ route('academic-head.blocks.index', ['period' => $currentPeriod]) }}"
                   class="btn btn-sm btn-outline-primary">All blocks</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Block</th>
                                <th class="text-end">Students</th>
                                <th class="text-end">High</th>
                                <th class="text-end">High %</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($blocks as $block)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $block->program_code }} - {{ $block->name }}</div>
                                        <small class="text-muted">Year {{ $block->year_number ?? '?' }}</small>
                                    </td>
                                    <td class="text-end">{{ number_format((int) $block->student_count) }}</td>
                                    <td class="text-end">
                                        <span class="badge badge-risk-high">{{ number_format((int) $block->high_risk) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="{{ $block->high_risk_percentage > 15 ? 'text-danger fw-bold' : 'text-muted' }}">
                                            {{ number_format((float) $block->high_risk_percentage, 1) }}%
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('academic-head.block', ['blockId' => $block->id, 'period' => $currentPeriod, 'school_year' => $schoolYear]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No blocks placed yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/academic-head-charts.js') }}"></script>

<script>
    (function () {
        const button = document.getElementById('refreshDashboardBtn');

        if (button) {
            button.addEventListener('click', function () {
                window.location.reload();
            });
        }
    })();
</script>
@endpush

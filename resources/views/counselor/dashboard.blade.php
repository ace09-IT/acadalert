@extends('layouts.app')

@section('title', 'Counselor Dashboard - AcadAlert')

@section('page_title', 'Dashboard')

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
        <a href="{{ route('counselor.cases') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-list me-1"></i> Full Caseload
        </a>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
@php
    $priorityMeta = [
        'Critical' => ['bg' => 'danger', 'icon' => 'fa-fire'],
        'High' => ['bg' => 'warning', 'icon' => 'fa-exclamation-circle'],
        'Medium' => ['bg' => 'info', 'icon' => 'fa-flag'],
        'Low' => ['bg' => 'secondary', 'icon' => 'fa-leaf'],
    ];

    $statusTone = static function (string $status): string {
        return match ($status) {
            'Resolved' => 'success',
            'Closed' => 'dark',
            'Reopened' => 'danger',
            'Awaiting Parent', 'Awaiting Student' => 'warning',
            'Referred' => 'info',
            'In Progress' => 'primary',
            default => 'secondary',
        };
    };
@endphp

<div class="ah-page gc-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="row ah-reveal" style="--ah-i: 0;">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Caseload</div>
                    <div class="stat-number">{{ number_format((int) ($totalCaseload ?? 0)) }}</div>
                    <small class="opacity-75">
                        {{ number_format((int) ($criticalCases ?? 0)) }} critical open
                    </small>
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
                    <div class="stat-number">{{ number_format((int) ($openCases ?? 0)) }}</div>
                    <small class="opacity-75">
                        {{ number_format((int) ($awaitingParent ?? 0)) }} awaiting parent
                    </small>
                </div>
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Resolved Cases</div>
                    <div class="stat-number">{{ number_format((int) ($resolvedCases ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((float) ($resolutionRate ?? 0), 1) }}% of caseload</small>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card {{ (int) ($overdueFollowUps ?? 0) > 0 ? 'danger' : 'warning' }} h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Overdue Follow-ups</div>
                    <div class="stat-number">{{ number_format((int) ($overdueFollowUps ?? 0)) }}</div>
                    <small class="opacity-75">
                        {{ number_format((int) ($upcomingFollowUpCount ?? 0)) }} due within 7 days
                    </small>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-exclamation"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie text-primary"></i> Case Priority Distribution</span>
                <a href="{{ route('counselor.cases', ['scope' => 'open']) }}"
                   class="btn btn-sm btn-outline-primary">Open cases</a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-bar text-primary"></i> Case Status Distribution</span>
                <a href="{{ route('counselor.cases') }}" class="btn btn-sm btn-outline-primary">All cases</a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-xl-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas fa-clock-rotate-left text-primary"></i> Recent Cases
                    <span class="badge bg-secondary ms-2">{{ $recentCases->count() ?? 0 }}</span>
                </span>
                <a href="{{ route('counselor.cases') }}" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Escalated</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentCases as $recent)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $recent->student_name }}</div>
                                        <small class="text-muted">
                                            {{ $recent->student_number ?? '—' }}
                                            @if(!empty($recent->program_code))
                                                &middot; {{ $recent->program_code }}
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $priorityMeta[$recent->priority]['bg'] ?? 'secondary' }}">
                                            {{ $recent->priority }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $statusTone((string) $recent->status) }}">
                                            {{ str_replace('_', ' ', (string) $recent->status) }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">{{ $recent->escalated_label ?? 'N/A' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('counselor.case', $recent->id) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-folder-open me-1"></i> Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                                        No cases have been escalated to you yet.
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas fa-calendar-check text-primary"></i> Upcoming Follow-ups
                    <small class="text-muted ms-1">next 7 days</small>
                </span>
                <span class="badge bg-{{ (int) ($overdueFollowUps ?? 0) > 0 ? 'danger' : 'secondary' }}">
                    {{ number_format((int) ($overdueFollowUps ?? 0)) }} overdue
                </span>
            </div>
            <div class="card-body">
                @forelse($upcomingFollowUps as $followUp)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2 {{ $loop->last ? 'border-0 pb-0' : '' }}">
                        <div class="me-2">
                            <a href="{{ route('counselor.case', $followUp->case_id) }}"
                               class="fw-semibold text-decoration-none">
                                {{ $followUp->student_name }}
                            </a>
                            <div class="small text-muted">
                                <span class="badge bg-{{ $priorityMeta[$followUp->priority]['bg'] ?? 'secondary' }} me-1">
                                    {{ $followUp->priority }}
                                </span>
                                {{ $followUp->session_type }}
                            </div>
                        </div>
                        <div class="text-end text-nowrap">
                            <div class="fw-semibold">{{ $followUp->follow_up_label }}</div>
                            <small class="text-muted">
                                @if($followUp->days_until === 0)
                                    today
                                @elseif($followUp->days_until === 1)
                                    tomorrow
                                @else
                                    in {{ $followUp->days_until }} days
                                @endif
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-check fa-2x d-block mb-2 opacity-50"></i>
                        <p class="mb-0">No follow-ups scheduled in the next 7 days.</p>
                    </div>
                @endforelse

                @if((int) ($overdueFollowUps ?? 0) > 0)
                    <div class="alert alert-danger mb-0 mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>{{ $overdueFollowUps }}</strong> follow-up(s) are past their due date.
                        <a href="{{ route('counselor.cases', ['scope' => 'open']) }}" class="alert-link">Review open cases</a>.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 3;">
    <div class="col-xl-8 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i> Caseload Trend
                <small class="text-muted ms-2">cases escalated over time</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="caseloadTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-stopwatch text-primary"></i> Response Time
            </div>
            <div class="card-body text-center">
                @php
                    $responseDays = (float) ($avgResponseTime ?? 0);
                    $responseTone = $responseDays <= 3 ? 'success' : ($responseDays <= 7 ? 'warning' : 'danger');
                    $responsePct = min(100, (int) round(($responseDays / 14) * 100));
                @endphp

                <div class="display-5 fw-bold text-{{ $responseTone }}">
                    {{ number_format($responseDays, 1) }}
                    <small class="fs-6 text-muted">day(s)</small>
                </div>
                <p class="text-muted small mb-3">
                    Average time from escalation to the first logged session
                </p>

                <div class="progress" style="height: 10px;" role="progressbar"
                     aria-label="Average response time" aria-valuenow="{{ $responsePct }}"
                     aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-{{ $responseTone }} progress-bar-animated"
                         style="width: {{ $responsePct }}%;"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>0 days</span>
                    <span>14+ days</span>
                </div>

                <ul class="list-unstyled text-start small mt-3 mb-0">
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Critical open cases</span>
                        <span class="fw-semibold">{{ number_format((int) ($criticalCases ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Awaiting parent</span>
                        <span class="fw-semibold">{{ number_format((int) ($awaitingParent ?? 0)) }}</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">Resolution rate</span>
                        <span class="fw-semibold">{{ number_format((float) ($resolutionRate ?? 0), 1) }}%</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/counselor-charts.js') }}"></script>
@endpush

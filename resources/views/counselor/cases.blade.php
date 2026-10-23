@extends('layouts.app')

@section('title', 'Caseload - AcadAlert')

@php
    $activeScope = $scope ?? 'all';
    $scopeLabels = [
        'all' => 'Caseload',
        'open' => 'Open Cases',
        'resolved' => 'Resolved Cases',
    ];
    $scopeIcons = [
        'all' => 'fa-list',
        'open' => 'fa-folder-open',
        'resolved' => 'fa-archive',
    ];
    $hasFilters = ($riskLevelFilter ?? 'all') !== 'all'
        || ($escalatedFrom ?? null)
        || ($escalatedTo ?? null)
        || (request('search') ?? '') !== ''
        || (request('priority', 'all') ?? 'all') !== 'all'
        || (request('status', 'all') ?? 'all') !== 'all';
@endphp

@section('page_title', $scopeLabels[$activeScope] ?? 'Caseload')

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-secondary text-white p-2">
            <i class="fas fa-filter me-1"></i> Showing {{ $matchedCases ?? 0 }} of {{ $scopeCounts['all'] ?? 0 }}
        </span>
        <a href="{{ route('counselor.dashboard') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-chart-pie me-1"></i> Dashboard
        </a>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')

<div class="ah-page gc-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">
<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 0;">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas {{ $scopeIcons[$activeScope] ?? 'fa-list' }} text-primary"></i>
            {{ $scopeLabels[$activeScope] ?? 'Caseload' }}
            <span class="badge bg-secondary ms-2">{{ count($cases ?? []) }}</span>
            <small class="text-muted ms-2">{{ $matchedCases ?? count($cases ?? []) }} matching filter(s)</small>
        </span>
        <span class="btn-group btn-group-sm" role="group" aria-label="Case scope">
            <a href="{{ route('counselor.cases') }}"
               class="btn {{ $activeScope === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-list me-1"></i> Caseload ({{ $scopeCounts['all'] ?? 0 }})
            </a>
            <a href="{{ route('counselor.cases', ['scope' => 'open']) }}"
               class="btn {{ $activeScope === 'open' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-folder-open me-1"></i> Open ({{ $scopeCounts['open'] ?? 0 }})
            </a>
            <a href="{{ route('counselor.cases', ['scope' => 'resolved']) }}"
               class="btn {{ $activeScope === 'resolved' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-archive me-1"></i> Resolved ({{ $scopeCounts['resolved'] ?? 0 }})
            </a>
        </span>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('counselor.cases') }}" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="scope" value="{{ $activeScope }}">

            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1" for="caseSearch">Search student</label>
                <input type="text" id="caseSearch" class="form-control form-control-sm" name="search"
                       value="{{ request('search') }}" placeholder="Name or student number...">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="caseRisk">Risk level</label>
                <select id="caseRisk" class="form-select form-select-sm" name="risk_level">
                    <option value="all" @selected(($riskLevelFilter ?? 'all') === 'all')>All levels</option>
                    @foreach(['High', 'Moderate', 'Low'] as $levelOption)
                        <option value="{{ $levelOption }}" @selected(($riskLevelFilter ?? 'all') === $levelOption)>{{ $levelOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="casePriority">Priority</label>
                <select id="casePriority" class="form-select form-select-sm" name="priority">
                    <option value="all" @selected(request('priority', 'all') === 'all')>All priorities</option>
                    @foreach(['Critical', 'High', 'Medium', 'Low'] as $priorityOption)
                        <option value="{{ $priorityOption }}" @selected(request('priority') === $priorityOption)>{{ $priorityOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="caseFrom">Escalated from</label>
                <input type="date" id="caseFrom" class="form-control form-control-sm" name="escalated_from"
                       value="{{ $escalatedFrom ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1" for="caseTo">Escalated to</label>
                <input type="date" id="caseTo" class="form-control form-control-sm" name="escalated_to"
                       value="{{ $escalatedTo ?? '' }}">
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1" for="caseSort">Order by</label>
                <select id="caseSort" class="form-select form-select-sm" name="sort">
                    <option value="priority" @selected(($sortFilter ?? 'priority') === 'priority')>Priority (most severe first)</option>
                    <option value="escalated_newest" @selected(($sortFilter ?? '') === 'escalated_newest')>Newest escalated</option>
                    <option value="escalated_oldest" @selected(($sortFilter ?? '') === 'escalated_oldest')>Oldest escalated</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="fas fa-search me-1"></i> Apply
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                        data-bs-target="#filterModal" title="More filters">
                    <i class="fas fa-sliders-h"></i>
                </button>
            </div>

            @if($hasFilters || ($sortFilter ?? 'priority') !== 'priority')
                <div class="col-12">
                    <a href="{{ route('counselor.cases', ['scope' => $activeScope]) }}" class="btn btn-sm btn-link px-0">
                        <i class="fas fa-times me-1"></i> Clear all filters
                    </a>
                    @if(request('search'))
                        <span class="badge bg-light text-dark border me-1">Search: {{ request('search') }}</span>
                    @endif
                    @if(($riskLevelFilter ?? 'all') !== 'all')
                        <span class="badge bg-light text-dark border me-1">Risk: {{ $riskLevelFilter }}</span>
                    @endif
                    @if(($sortFilter ?? 'priority') !== 'priority')
                        <span class="badge bg-light text-dark border me-1">Sort: {{ str_replace('_', ' ', $sortFilter) }}</span>
                    @endif
                </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-mobile-cards">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Risk at Escalation</th>
                        <th>Status</th>
                        <th>Last Session</th>
                        <th>Next Follow-up</th>
                        <th>Effectiveness</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $priorityColors = [
                            'Critical' => 'danger',
                            'High' => 'warning',
                            'Medium' => 'info',
                            'Low' => 'secondary',
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

                    @forelse($cases as $case)
                        @php
                            $followUpOverdue = $case->follow_up_date
                                && \Carbon\Carbon::parse($case->follow_up_date)->isPast();
                        @endphp
                        <tr>
                            <td data-label="Priority">
                                <span class="badge bg-{{ $priorityColors[$case->priority] ?? 'secondary' }}">
                                    {{ $case->priority }}
                                </span>
                            </td>
                            <td data-label="Student"><strong>{{ $case->student_name ?? 'N/A' }}</strong></td>
                            <td data-label="Program">{{ $case->program ?? 'N/A' }}</td>
                            <td data-label="Risk at Escalation">
                                <span class="badge {{ $case->risk_level === 'High' ? 'badge-risk-high' : ($case->risk_level === 'Moderate' ? 'badge-risk-moderate' : 'badge-risk-low') }}">
                                    {{ $case->risk_level ?? 'N/A' }}
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="badge bg-{{ $statusTone((string) $case->status) }}">
                                    {{ str_replace('_', ' ', (string) $case->status) }}
                                </span>
                            </td>
                            <td data-label="Last Session">{{ $case->last_session_date ?? 'No sessions' }}</td>
                            <td data-label="Next Follow-up" class="{{ $followUpOverdue ? 'text-danger fw-bold' : '' }}">
                                {{ $case->follow_up_date ?? 'N/A' }}
                                @if($followUpOverdue)
                                    <i class="fas fa-exclamation-triangle ms-1" title="Follow-up is past due"></i>
                                @endif
                            </td>
                            <td data-label="Effectiveness">
                                @if($case->improvement !== null && $case->improvement > 0)
                                    <span class="text-success">
                                        <i class="fas fa-arrow-down me-1"></i> {{ $case->improvement }} pts
                                    </span>
                                @elseif($case->improvement !== null && $case->improvement < 0)
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-up me-1"></i> {{ abs($case->improvement) }} pts
                                    </span>
                                @else
                                    <span class="text-muted">Pending</span>
                                @endif
                            </td>
                            <td data-label="Action" class="text-end">
                                <a href="{{ route('counselor.case', $case->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-folder-open me-1"></i> Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                                No {{ strtolower($scopeLabels[$activeScope] ?? 'cases') }} match these filters.
                                @if($hasFilters)
                                    <div class="mt-2">
                                        <a href="{{ route('counselor.cases', ['scope' => $activeScope]) }}">
                                            Clear the filters
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="filterModalLabel">
                    <i class="fas fa-filter me-2"></i> Filter Cases
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <form method="GET" action="{{ route('counselor.cases') }}">
                <input type="hidden" name="scope" value="{{ $scope ?? 'all' }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterModalStatus">Status</label>
                        <select class="form-select" id="filterModalStatus" name="status">
                            <option value="all" @selected(request('status', 'all') === 'all')>All statuses</option>
                            @foreach([
                                'New' => 'New',
                                'In Progress' => 'In Progress',
                                'Awaiting Parent' => 'Awaiting Parent',
                                'Awaiting Student' => 'Awaiting Student',
                                'Referred' => 'Referred',
                                'Resolved' => 'Resolved',
                                'Closed' => 'Closed',
                                'Reopened' => 'Reopened',
                            ] as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected(request('status') === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            A specific status narrows the active scope; it never widens it.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterModalDepartment">Department</label>
                        <select class="form-select" id="filterModalDepartment" name="department">
                            <option value="all" @selected(request('department', 'all') === 'all')>All departments</option>
                            @foreach($departments ?? [] as $departmentOption)
                                <option value="{{ $departmentOption->code }}" @selected(request('department') === $departmentOption->code)>
                                    {{ $departmentOption->code }} - {{ $departmentOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Your caseload is scoped to your assigned department by isolation policy.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterModalSearch">Search</label>
                        <input type="text" class="form-control" id="filterModalSearch" name="search"
                               value="{{ request('search') }}" placeholder="Student name or number...">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('counselor.cases', ['scope' => $scope ?? 'all']) }}" class="btn btn-link me-auto">
                        Reset
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

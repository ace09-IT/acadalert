@extends('layouts.app')

@section('title', 'End-of-Term Reports - AcadAlert')

@section('page_title', 'End-of-Term Academic Risk Reports')

@section('page_actions')
    <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-secondary text-white p-2">
            <i class="fas fa-building me-1"></i> {{ $department->code }} - {{ $department->name }}
        </span>
    </div>
@endsection

@section('content')
<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="alert alert-info d-flex align-items-start ah-reveal" style="--ah-i: 0;">
    <i class="fas fa-shield-alt fa-2x me-3"></i>
    <div>
        <h6 class="fw-bold mb-1">How these figures are produced</h6>
        <p class="mb-1 small">
            <strong>Scoring:</strong> the risk <em>score</em> comes from the AI risk-assessment engine
            (Gemini via AI Studio) per student and grading period.
            <strong>Classification:</strong> the score is mapped onto the configured bands
            (Admin &rarr; Risk Settings) by deterministic, auditable rules, so the same score always
            becomes the same risk level.
            <strong>Reporting:</strong> this report is a rule-based aggregation of those stored scores
            — counts, percentages and a comparison against the previous grading period. No generative
            AI is involved in compiling the report itself.
        </p>
        <p class="mb-0 small">
            Each report is stored as an immutable snapshot with a SHA-256 fingerprint, so the exported
            PDF is reproducible and independently verifiable.
        </p>
    </div>
</div>

@php
    $activeThresholds = app(\App\Services\RiskThresholdService::class)->current();
    $activeBands = \App\Services\RiskThresholdService::bandLabels($activeThresholds);
@endphp
<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 1;">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="fas fa-sliders-h text-primary me-2"></i> Risk Band Definitions</span>
        <span class="text-muted small">Score is 0-100</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 border-success">
                    <span class="badge badge-risk-low mb-2">Low Risk</span>
                    <div class="fw-bold">Score {{ $activeBands['Low'] ?? 'n/a' }}</div>
                    <small class="text-muted">No intervention required; monitor at the next grading period.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 border-warning">
                    <span class="badge badge-risk-moderate mb-2">Moderate Risk</span>
                    <div class="fw-bold">Score {{ $activeBands['Moderate'] ?? 'n/a' }}</div>
                    <small class="text-muted">Intervention recommended before the next grading period.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 border-danger">
                    <span class="badge badge-risk-high mb-2">High Risk</span>
                    <div class="fw-bold">Score {{ $activeBands['High'] ?? 'n/a' }}</div>
                    <small class="text-muted">Escalate to the Guidance Counselor for case management.</small>
                </div>
            </div>
        </div>
        <p class="small text-muted mb-0 mt-3">
            <i class="fas fa-info-circle me-1"></i>
            These bands are configurable by the Administrator. If most of the department falls into one
            band, the boundaries need recalibration rather than the scores — the report flags that case
            explicitly.
        </p>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 2;">
    <div class="card-header">
        <i class="fas fa-cogs text-primary me-2"></i> Generate Report for a Grading Period
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('academic-head.reports') }}" class="row g-2 align-items-end mb-4">
            <div class="col-md-3">
                <label class="form-label fw-bold small">School Year</label>
                <select class="form-select form-select-sm" name="school_year" onchange="this.form.submit()">
                    @foreach($schoolYears as $year)
                        <option value="{{ $year }}" @selected($schoolYear === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Semester</label>
                <select class="form-select form-select-sm" name="semester" onchange="this.form.submit()">
                    <option value="1st" @selected($semester === '1st')>First Semester</option>
                    <option value="2nd" @selected($semester === '2nd')>Second Semester</option>
                </select>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted small">
                    Academic year is graded on three periods: {{ implode(' &middot; ', $periods) }}.
                </span>
            </div>
        </form>

        <div class="row g-3">
            @foreach($periodPlan as $plan)
                <div class="col-lg-4">
                    <div class="card h-100 ah-glow {{ $plan['generated'] ? 'border-success' : ($plan['has_data'] ? 'border-primary' : 'border-secondary') }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0">{{ $plan['period'] }}</h6>
                                <span class="badge bg-secondary">Period {{ $plan['number'] }} of 3</span>
                            </div>

                            <p class="small text-muted mb-2">
                                @if($plan['previous'])
                                    Compared against <strong>{{ $plan['previous'] }}</strong>.
                                @else
                                    Baseline period &mdash; no earlier period to compare against.
                                @endif
                            </p>

                            <div class="mb-3">
                                @if($plan['has_data'])
                                    <span class="badge bg-primary">
                                        <i class="fas fa-check me-1"></i> Score data available
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-ban me-1"></i> No score data yet
                                    </span>
                                @endif

                                @if($plan['generated'])
                                    <span class="badge bg-success">
                                        <i class="fas fa-file-alt me-1"></i> Report generated
                                    </span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('academic-head.reports.generate') }}">
                                @csrf
                                <input type="hidden" name="school_year" value="{{ $schoolYear }}">
                                <input type="hidden" name="semester" value="{{ $semester }}">
                                <input type="hidden" name="grading_period" value="{{ $plan['period'] }}">
                                <button type="submit"
                                        class="btn btn-sm w-100 {{ $plan['generated'] ? 'btn-outline-primary' : 'btn-primary' }}"
                                        @disabled(!$plan['has_data'])>
                                    @if($plan['generated'])
                                        <i class="fas fa-sync me-1"></i> Regenerate
                                    @else
                                        <i class="fas fa-play me-1"></i> Generate
                                    @endif
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 3;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-archive text-primary me-2"></i> Stored Reports
        </div>
        <span class="badge bg-secondary">{{ $reports->count() }} report(s)</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>School Year</th>
                        <th class="text-end">Monitored</th>
                        <th class="text-end">High</th>
                        <th class="text-end">Moderate</th>
                        <th class="text-end">Low</th>
                        <th class="text-end">High %</th>
                        <th>Comparison</th>
                        <th>Generated</th>
                        <th>Sharing</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>
                                <span class="badge bg-primary">{{ $report->grading_period }}</span>
                                <span class="badge bg-secondary">{{ $report->semester === '2nd' ? '2nd' : '1st' }} sem</span>
                            </td>
                            <td>{{ $report->school_year }}</td>
                            <td class="text-end fw-bold">{{ number_format((int) $report->total_monitored) }}</td>
                            <td class="text-end text-danger">{{ number_format((int) $report->high_count) }}</td>
                            <td class="text-end text-warning">{{ number_format((int) $report->moderate_count) }}</td>
                            <td class="text-end text-success">{{ number_format((int) $report->low_count) }}</td>
                            <td class="text-end">{{ number_format((float) $report->high_percentage, 2) }}%</td>
                            <td>
                                @if($report->previous_grading_period)
                                    <span class="small text-muted">vs {{ $report->previous_grading_period }}</span>
                                @else
                                    <span class="small text-muted">baseline</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $report->generated_at }}</div>
                                <div class="small text-muted">by {{ $report->generated_by_name ?? 'Unknown' }}</div>
                            </td>
                            <td>
                                @if($report->is_shared)
                                    <span class="badge bg-success"><i class="fas fa-share-alt me-1"></i> Shared</span>
                                @else
                                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i> Private</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('academic-head.reports.preview', $report->id) }}"
                                       class="btn btn-outline-primary" title="View report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('academic-head.reports.pdf', $report->id) }}"
                                       class="btn btn-outline-danger" title="Download PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <form method="POST" action="{{ route('academic-head.reports.destroy', $report->id) }}"
                                          onsubmit="return confirm('Delete this stored report?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-secondary" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="fas fa-file-alt fa-2x d-block mb-2"></i>
                                No end-of-term reports generated yet. Choose a grading period above to compile one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
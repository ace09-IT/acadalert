@extends('layouts.app')

@section('title', 'Department Reports - AcadAlert')

@section('page_title', 'Department Reports')

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-secondary text-white p-2">
            <i class="fas fa-building me-1"></i> {{ $department->code ?? '' }} - {{ $department->name ?? '' }}
        </span>
        <a href="{{ route('counselor.reports.mine') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-file-lines me-1"></i> My Reports
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page gc-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">
<div class="alert alert-light border ah-reveal" style="--ah-i: 0;">
    <i class="fas fa-info-circle text-primary me-2"></i>
    Shared by your <strong>Academic Head</strong>. Each report is a frozen snapshot, so the PDF you
    download matches the original record exactly. Generation and sharing remain with the Academic Head.
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Reports Shared</div>
                    <div class="stat-number">{{ number_format((int) ($summary['count'] ?? 0)) }}</div>
                    <small class="opacity-75">{{ number_format((int) ($totalShared ?? 0)) }} total for your department</small>
                </div>
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Latest Period</div>
                    <div class="stat-number fs-3">
                        {{ $summary['latest']->grading_period ?? 'N/A' }}
                    </div>
                    <small class="opacity-75">{{ $summary['latest']->school_year ?? 'no reports yet' }}</small>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk %</div>
                    <div class="stat-number">
                        {{ $summary['latest_high_percentage'] !== null ? number_format((float) $summary['latest_high_percentage'], 2) . '%' : 'N/A' }}
                    </div>
                    <small class="opacity-75">
                        {{ !empty($summary['previous_period']) ? 'vs ' . $summary['previous_period'] : 'baseline period' }}
                    </small>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card purple h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Students Monitored</div>
                    <div class="stat-number">{{ number_format((int) ($summary['latest_monitored'] ?? 0)) }}</div>
                    <small class="opacity-75">in the latest report</small>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-xl-5 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-filter text-primary"></i> Filter Reports
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('counselor.reports') }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterSchoolYear">School Year</label>
                        <select class="form-select" id="filterSchoolYear" name="school_year">
                            <option value="">All school years</option>
                            @foreach($options['school_years'] ?? [] as $year)
                                <option value="{{ $year }}" @selected(($filters['school_year'] ?? '') === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterSemester">Semester</label>
                        <select class="form-select" id="filterSemester" name="semester">
                            <option value="">All semesters</option>
                            @foreach($options['semesters'] ?? [] as $semester)
                                <option value="{{ $semester }}" @selected(($filters['semester'] ?? '') === $semester)>
                                    {{ $semester }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="filterPeriod">Grading Period</label>
                        <select class="form-select" id="filterPeriod" name="grading_period">
                            <option value="">All grading periods</option>
                            @foreach($options['grading_periods'] ?? [] as $period)
                                <option value="{{ $period }}" @selected(($filters['grading_period'] ?? '') === $period)>{{ $period }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-1"></i> Apply
                        </button>
                        <a href="{{ route('counselor.reports') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <div class="form-text mt-3">
                    Options are drawn from the reports actually shared with your department, so a
                    filter can never produce an empty table by accident.
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary"></i> Risk Distribution per Reported Period
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="reportDistributionChart"
                            data-chart="{{ ($chart ?? collect())->toJson() }}"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 3;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-alt text-primary"></i> Reports Shared With Counselors</span>
        <span class="badge bg-secondary">{{ $reports->count() }} report(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-mobile-cards">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>School Year</th>
                        <th>Semester</th>
                        <th class="text-end">Students Monitored</th>
                        <th class="text-end">High</th>
                        <th class="text-end">Moderate</th>
                        <th class="text-end">Low</th>
                        <th class="text-end">High %</th>
                        <th>Comparison</th>
                        <th>Shared On</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td data-label="Period"><span class="badge bg-primary">{{ $report->grading_period }}</span></td>
                            <td data-label="School Year">{{ $report->school_year }}</td>
                            <td data-label="Semester">{{ $report->semester ?? '—' }}</td>
                            <td data-label="Students Monitored" class="text-end fw-bold">{{ number_format((int) $report->total_monitored) }}</td>
                            <td data-label="High" class="text-end text-danger">{{ number_format((int) $report->high_count) }}</td>
                            <td data-label="Moderate" class="text-end text-warning">{{ number_format((int) $report->moderate_count) }}</td>
                            <td data-label="Low" class="text-end text-success">{{ number_format((int) $report->low_count) }}</td>
                            <td data-label="High %" class="text-end">{{ number_format((float) $report->high_percentage, 2) }}%</td>
                            <td data-label="Comparison">
                                <span class="small text-muted">
                                    {{ $report->previous_grading_period ? 'vs ' . $report->previous_grading_period : 'baseline' }}
                                </span>
                            </td>
                            <td data-label="Shared On"><span class="small">{{ $report->shared_at ?? 'n/a' }}</span></td>
                            <td data-label="Actions" class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('counselor.reports.preview', $report->id) }}"
                                       class="btn btn-outline-primary" title="View report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('counselor.reports.pdf', $report->id) }}"
                                       class="btn btn-outline-danger" title="Download PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                                No end-of-term reports have been shared with counselors for your department yet.
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

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/counselor-reports-charts.js') }}"></script>
@endpush

@extends('layouts.app')

@section('title', 'End-of-Term Reports - AcadAlert')

@section('page_title', 'Consolidated End-of-Term Reports')

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="alert alert-light border">
    <i class="fas fa-info-circle text-primary me-2"></i>
    Consolidated, department-level end-of-term academic risk reports shared by the Academic Heads.
    Reports are <strong>generated solely by the Academic Head</strong> of each department using deterministic,
    rule-based aggregation — no generative AI is involved. This view is read-only.
</div>

<div class="row">
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="stat-card primary ah-reveal" style="--ah-i: 0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Shared Reports</div>
                    <div class="stat-number">{{ number_format((int) $summary['total_reports']) }}</div>
                </div>
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="stat-card warning ah-reveal" style="--ah-i: 1;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Departments Reporting</div>
                    <div class="stat-number">{{ number_format((int) $summary['departments']) }}</div>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="stat-card success ah-reveal" style="--ah-i: 2;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Students Monitored (sum)</div>
                    <div class="stat-number">{{ number_format((int) $summary['students']) }}</div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 3;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="fas fa-archive text-primary me-2"></i> Reports Shared With Administrators</div>
        <span class="badge bg-secondary">{{ $reports->count() }} report(s)</span>
    </div>
    <div class="card-body">
        @include('admin.partials.end-of-term-report-table', ['reports' => $reports])
    </div>
</div>

</div>
@endsection
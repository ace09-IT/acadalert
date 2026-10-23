@extends('layouts.app')

@section('title', 'Student Basis - {{ $student->first_name }} {{ $student->last_name }} - AcadAlert')

@section('page_title', 'Student Risk Basis')
@section('page_actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('academic-head.block', $blockId) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to {{ $block->name }}
        </a>
        <form method="GET" action="{{ route('academic-head.block.student.basis', ['blockId' => $blockId, 'studentId' => $student->id]) }}" class="d-inline">
            <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($periods as $period)
                    <option value="{{ $period }}" {{ $period === $currentPeriod ? 'selected' : '' }}>{{ $period }}</option>
                @endforeach
            </select>
            <input type="hidden" name="school_year" value="{{ $schoolYear }}">
        </form>
    </div>
@endsection

@section('content')
<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

    <!-- Banded header: student identity & placement -->
    <div class="card ah-glow mb-4">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" aria-hidden="true" class="rounded-circle" width="36" height="36">
            <div>
                <span class="d-block small text-opacity-75">Basis Verification &middot; {{ $currentPeriod }} &middot; {{ $schoolYear }}</span>
                <h5 class="mb-0">{{ $student->first_name }} {{ $student->last_name }}</h5>
                <span class="small text-opacity-90">{{ $student->student_number }} &middot; {{ $placement['program_code'] ?? '-' }} &middot; Year {{ $placement['year_number'] }} &middot; Block {{ $block->name }}</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-sm-3">
                    <div class="stat-card primary h-100">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-number">{{ $student->student_number }}</div>
                        <div class="stat-label">Student Number</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card success h-100">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-number">{{ $placement['program_code'] ?? '-' }}</div>
                        <div class="stat-label">Program</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card info h-100">
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-number">Year {{ $placement['year_number'] }}</div>
                        <div class="stat-label">Year Level</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card secondary h-100">
                        <div class="stat-icon"><i class="fas fa-hashtag"></i></div>
                        <div class="stat-number">{{ $block->name }}</div>
                        <div class="stat-label">Block</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Current risk score -->
    <div class="card ah-glow mb-4 {{ $riskScore >= 75 ? 'border-danger' : ($riskScore >= 50 ? 'border-warning' : 'border-success') }}">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-shield-halved me-2"></i> Current Risk Score &mdash; {{ $currentPeriod }}</span>
            <span class="badge {{ $riskBadgeClass }}">{{ $riskLevel }}</span>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 text-center mb-3 mb-md-0">
                    <div class="display-4 fw-bold text-primary">{{ number_format($riskScore) }}</div>
                    <div class="text-muted small">Risk score</div>
                </div>
                <div class="col-md-6">
                    <p class="mb-3">The basis below shows the per-subject grades and attendance that feed the AI risk score for <strong>{{ $currentPeriod }}</strong>. Use it to verify why the student was scored {{ $riskLevel }}.</p>
                    @php
                        $riskBand = match (true) {
                            $riskScore >= 75 => ['High', 'border-danger', 'badge-risk-high'],
                            $riskScore >= 50 => ['Moderate', 'border-warning', 'badge-risk-moderate'],
                            default => ['Low', 'border-success', 'badge-risk-low'],
                        };
                    @endphp
                    <span class="badge {{ $riskBand[2] }} fw-bold">{{ $riskBand[0] }} risk &mdash; score {{ $riskScore }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-subject grades -->
    <div class="card ah-glow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list-ol me-2"></i> Grades by Subject &mdash; {{ $currentPeriod }}</span>
            <span class="badge {{ $grades->where('numerical_grade', '<', 75)->isNotEmpty() ? 'bg-danger' : 'bg-success' }}">{{ $grades->count() }} subject(s)</span>
        </div>
        <div class="card-body p-0">
            @if ($grades->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                    <div>No grades recorded for {{ $currentPeriod }} {{ $schoolYear }}.</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="border-bottom fw-semibold">Subject Code</th>
                                <th class="border-bottom fw-semibold">Subject Name</th>
                                <th class="border-bottom fw-semibold text-end">Grade</th>
                                <th class="border-bottom fw-semibold text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grades as $grade)
                                @php
                                    $failing = (float) $grade->numerical_grade < 75;
                                    $gradeBadge = $failing ? 'bg-danger' : 'bg-success';
                                    $gradeLabel = $failing ? 'Failing' : 'Passing';
                                @endphp
                                <tr {{ $failing ? 'class="table-danger"' : '' }}>
                                    <td>{{ $grade->subject_code ?? '-' }}</td>
                                    <td>{{ $grade->subject_name ?? 'Subject' }}</td>
                                    <td class="text-end {{ $failing ? 'text-danger fw-bold' : '' }}">{{ number_format((float) $grade->numerical_grade, 2) }}</td>
                                    <td class="text-end"><span class="badge {{ $gradeBadge }}">{{ $gradeLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent small text-muted">
    <!-- Attendance -->
    <div class="card ah-glow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-calendar-check me-2"></i> Attendance by Subject &mdash; {{ $currentPeriod }}</span>
            <span class="badge {{ (float) ($attendanceAggregate->overall_attendance ?? 0) < 80 ? 'bg-warning' : 'bg-success' }}">{{ (float) ($attendanceAggregate->overall_attendance ?? 0) }}% overall</span>
        </div>
        <div class="card-body p-0">
            @if ($attendanceSummaries->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="fas fa-calendar-times fa-2x d-block mb-2"></i>
                    <div>No attendance records for {{ $currentPeriod }} {{ $schoolYear }}.</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="border-bottom fw-semibold">Subject Code</th>
                                <th class="border-bottom fw-semibold">Subject Name</th>
                                <th class="border-bottom fw-semibold text-end">Attendance</th>
                                <th class="border-bottom fw-semibold text-end">Absences</th>
                                <th class="border-bottom fw-semibold text-end">Lates</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendanceSummaries as $att)
                                @php
                                    $rate = (float) ($att->attendance_rate ?? 0);
                                    $poor = $rate < 80;
                                @endphp
                                <tr {{ $poor ? 'class="table-warning"' : '' }}>
                                    <td>{{ $att->subject_code ?? '-' }}</td>
                                    <td>{{ $att->subject_name ?? 'Subject' }}</td>
                                    <td class="text-end {{ $poor ? 'text-warning fw-bold' : '' }}">{{ number_format($rate, 2) }}%</td>
                                    <td class="text-end">{{ (int) ($att->total_absences ?? 0) }}</td>
                                    <td class="text-end">{{ (int) ($att->total_lates ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-between small">
                    <div>
                        <strong>Overall attendance:</strong> {{ (float) ($attendanceAggregate->overall_attendance ?? 0) }}% &middot;
                        <strong>Absences:</strong> {{ (int) ($attendanceAggregate->total_absences ?? 0) }} &middot;
                        <strong>Lates:</strong> {{ (int) ($attendanceAggregate->total_lates ?? 0) }}
                    </div>
                    <div class="text-end text-muted">
                        Low attendance (< 80%) rows are highlighted.
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="text-end mt-3">
        <a href="{{ route('academic-head.block', $blockId) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Return to Block Roster
        </a>
    </div>
</div>
@endsection

                    Failing threshold: below 75. Highlighted rows are below threshold.
                </div>
            @endif
        </div>
    </div>


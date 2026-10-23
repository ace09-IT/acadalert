@extends('layouts.app')

@section('title', 'My Attendance - AcadAlert')

@section('page_title', 'My Attendance')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <div class="d-inline-block">
            <select class="form-select form-select-sm d-inline-block" id="periodSelect" style="width: auto; display: inline-block;">
                @foreach($periods as $period)
                    <option value="{{ $period }}" {{ $currentPeriod == $period ? 'selected' : '' }}>{{ $period }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
@endsection

@section('content')
@php
    // The headline figure and its context line, derived once so the hero and the
    // summary tiles can never disagree.
    $overallRate = round($attendanceSummary->overall_attendance ?? 0);
@endphp

<div class="ah-page sp-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

    <div class="sp-welcome ah-reveal" style="--ah-i: 0;">
        <span class="sp-welcome-icon"><i class="fas fa-clipboard-check"></i></span>
        <div class="flex-grow-1">
            <h2 class="sp-welcome-title">{{ $overallRate }}% attendance</h2>
            <p class="sp-welcome-sub">
                {{ $attendanceSummary->total_absences ?? 0 }} absence(s)
                &middot; {{ $attendanceSummary->total_lates ?? 0 }} late(s)
                in <strong>{{ $currentPeriod }} {{ $schoolYear }}</strong>
            </p>
        </div>
    </div>

    <div class="row ah-reveal" style="--ah-i: 1;">
        <div class="col-md-3 col-6 mb-4">
            <div class="stat-card primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Overall Attendance</div>
                        <div class="stat-number">{{ $overallRate }}%</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-4">
            <div class="stat-card danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Absences</div>
                        <div class="stat-number">{{ $attendanceSummary->total_absences ?? 0 }}</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-4">
            <div class="stat-card warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Lates</div>
                        <div class="stat-number">{{ $attendanceSummary->total_lates ?? 0 }}</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-4">
            <div class="stat-card success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Excused Absences</div>
                        <div class="stat-number">{{ $attendanceSummary->total_excused ?? 0 }}</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row ah-reveal" style="--ah-i: 2;">
        <div class="col-12 mb-4">
            <div class="card ah-glow">
                <div class="card-header">
                    <i class="fas fa-table text-primary"></i> Attendance by Subject
                    <span class="badge bg-light text-primary ms-2">{{ $currentPeriod }} {{ $schoolYear }}</span>
                </div>
                <div class="card-body">
                    @if($attendanceBreakdown->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Attendance Rate</th>
                                        <th>Absences</th>
                                        <th>Lates</th>
                                        <th>Excused</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendanceBreakdown as $index => $att)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="badge bg-secondary">{{ $att->subject_code }}</span></td>
                                        <td>{{ $att->subject_name }}</td>
                                        <td>
                                            <span class="{{ $att->attendance_rate < 70 ? 'text-danger fw-bold' : ($att->attendance_rate < 80 ? 'text-warning' : 'text-success') }}">
                                                {{ round($att->attendance_rate) }}%
                                            </span>
                                        </td>
                                        <td>{{ $att->total_absences ?? 0 }}</td>
                                        <td>{{ $att->total_lates ?? 0 }}</td>
                                        <td>{{ $att->total_excused ?? 0 }}</td>
                                        <td>
                                            @if($att->attendance_rate < 70)
                                                <span class="badge bg-danger">At risk</span>
                                            @elseif($att->attendance_rate < 80)
                                                <span class="badge bg-warning">Below 80%</span>
                                            @elseif($att->attendance_rate < 90)
                                                <span class="badge bg-info">Good</span>
                                            @else
                                                <span class="badge bg-success">Excellent</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x d-block mb-3"></i>
                            <p class="mb-1">No attendance data available for this period.</p>
                            <small>Attendance is recorded by your instructor each class session.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Warnings -->
    @php
        $lowAttendance = $attendanceBreakdown->where('attendance_rate', '<', 75)->first();
    @endphp
    @if($lowAttendance)
    <div class="row ah-reveal" style="--ah-i: 3;">
        <div class="col-12 mb-4">
            <div class="card ah-glow border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i> Attendance Warning
                </div>
                <div class="card-body">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> Your attendance in <strong>{{ $lowAttendance->subject_name }}</strong> is <strong>{{ round($lowAttendance->attendance_rate) }}%</strong>, which is below the required threshold.
                        <br>
                        <small>Please see your instructor to discuss your attendance.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    document.getElementById('periodSelect')?.addEventListener('change', function() {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('period', this.value);
        window.location.href = currentUrl.toString();
    });
</script>
@endpush
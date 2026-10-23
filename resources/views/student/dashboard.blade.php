@extends('layouts.app')

@section('title', 'My Dashboard - AcadAlert')

@section('page_title', 'My Academic Standing')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <div class="d-inline-block">
            <select class="form-select form-select-sm d-inline-block" id="periodSelect" style="width: auto; display: inline-block;">
                <option value="Prelim" {{ $currentPeriod == 'Prelim' ? 'selected' : '' }}>Prelim</option>
                <option value="Midterm" {{ $currentPeriod == 'Midterm' ? 'selected' : '' }}>Midterm</option>
                <option value="Finals" {{ $currentPeriod == 'Finals' ? 'selected' : '' }}>Finals</option>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
@php
    $score = $currentRisk->risk_score ?? null;
    $level = $currentRisk->risk_level ?? 'No Data';

    $levelClass = match ($level) {
        'High' => 'badge-risk-high',
        'Moderate' => 'badge-risk-moderate',
        'Low' => 'badge-risk-low',
        default => 'bg-secondary',
    };

    $trendMap = [
        'improving' => ['label' => 'Improving', 'class' => 'bg-success', 'icon' => 'fa-arrow-down'],
        'stable' => ['label' => 'Stable', 'class' => 'bg-secondary', 'icon' => 'fa-minus'],
        'worsening' => ['label' => 'Worsening', 'class' => 'bg-danger', 'icon' => 'fa-arrow-up'],
    ];
    $trend = $trendMap[$riskTrend['overall'] ?? 'unknown']
        ?? ['label' => 'No Trend', 'class' => 'bg-light text-dark border', 'icon' => 'fa-question'];

    $levelTone = match ($level) {
        'High' => 'danger',
        'Moderate' => 'warning',
        'Low' => 'success',
        default => 'secondary',
    };

    // The greeting's first name. The student record is authoritative; the account
    // name is the fallback, so the line is never empty.
    $firstName = trim((string) ($studentInfo->first_name ?? ''));
    if ($firstName === '') {
        $firstName = explode(' ', trim((string) auth()->user()->name))[0] ?: 'Student';
    }
@endphp

<div class="ah-page sp-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="sp-welcome ah-reveal" style="--ah-i: 0;">
    <div class="sp-welcome-icon" aria-hidden="true">
        <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="sp-welcome-body">
        <h2 class="sp-welcome-title">Welcome back, {{ $firstName }}</h2>
        <p class="sp-welcome-sub">
            Here is your academic standing for
            <strong>{{ $currentPeriod }} {{ $schoolYear }}</strong>.
        </p>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-lg-5 mb-4">
        <div class="card h-100 ah-glow border-{{ $levelTone }}">
            <div class="card-header">
                <i class="fas fa-shield-alt text-primary"></i> Risk Status
                <span class="badge bg-primary ms-2">{{ $currentPeriod }} {{ $schoolYear }}</span>
            </div>
            <div class="card-body">
                <div class="sp-score">
                    @if($score !== null && $score > 0)
                        <div class="display-4 fw-bold">{{ $score }}</div>
                        <span class="badge {{ $levelClass }} p-2 fs-6">{{ strtoupper($level) }} RISK</span>
                    @else
                        <div class="display-5 fw-bold text-muted">—</div>
                        <span class="badge bg-secondary p-2 fs-6">Pending Assessment</span>
                    @endif
                </div>

                <div class="sp-trend text-center">
                    <span class="badge {{ $trend['class'] }} p-2">
                        <i class="fas {{ $trend['icon'] }} me-1"></i>{{ $trend['label'] }}
                    </span>
                    @if(!is_null($riskTrend['delta']))
                        <div class="small text-muted mt-1">
                            {{ $riskTrend['delta'] > 0 ? '+' : '' }}{{ $riskTrend['delta'] }} points vs previous period
                        </div>
                    @endif
                </div>

                <div class="sp-factors">
                    <div class="text-muted small text-uppercase mb-2">Risk Factors</div>
                    @forelse($riskFactors as $factor)
                        <div class="small mb-1 sp-factor">
                            <i class="fas fa-circle-exclamation text-warning me-2"></i>{{ $factor }}
                        </div>
                    @empty
                        <div class="small text-muted">
                            No risk factors recorded for {{ $currentPeriod }}.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i> Risk Trend
                <small class="text-muted ms-2">score by grading period</small>
            </div>
            <div class="card-body">

                {{-- CHANGED: Let the shared responsive chart stage control height. --}}
                <div class="chart-container">
                    <canvas id="studentRiskTrendChart"></canvas>
                </div>

                @if(count($riskTrend['rows']) > 0)
                    <div class="table-responsive mt-3">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Score</th>
                                    <th>Level</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riskTrend['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['period'] }}</td>
                                        <td class="fw-semibold">{{ $row['score'] }}</td>
                                        <td>
                                            <span class="badge {{ match($row['level']) {
                                                'High' => 'badge-risk-high',
                                                'Moderate' => 'badge-risk-moderate',
                                                'Low' => 'badge-risk-low',
                                                default => 'bg-secondary',
                                            } }}">{{ $row['level'] }}</span>
                                        </td>
                                        <td>
                                            @php $rowTrend = $trendMap[$row['trend']] ?? null; @endphp
                                            @if($rowTrend)
                                                <span class="badge {{ $rowTrend['class'] }}">{{ $rowTrend['label'] }}</span>
                                            @endif
                                            @if(!is_null($row['delta']))
                                                <small class="text-muted ms-1">
                                                    {{ $row['delta'] > 0 ? '+' : '' }}{{ $row['delta'] }}
                                                </small>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="small text-muted mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Score is calculated from grades (60%) and attendance (40%).
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-lg-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-book text-primary"></i> Grades Summary
                <span class="badge bg-primary ms-2">{{ $currentPeriod }} {{ $schoolYear }}</span>
                <a href="{{ route('student.grades', ['period' => $currentPeriod]) }}"
                   class="btn btn-sm btn-outline-primary float-end">Full grades</a>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3 sp-metrics">
                    <div class="col-4">
                        <div class="text-muted small text-uppercase">Average</div>
                        <div class="fw-bold fs-4 {{ $gradeSummary['average'] === null ? 'text-muted' : ($gradeSummary['average'] < 75 ? 'text-danger' : ($gradeSummary['average'] < 80 ? 'text-warning' : 'text-success')) }}">
                            {{ $gradeSummary['average'] === null ? '—' : $gradeSummary['average'] . '%' }}
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small text-uppercase">Subjects</div>
                        <div class="fw-bold fs-4">{{ $gradeSummary['total'] }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small text-uppercase">Failing</div>
                        <div class="fw-bold fs-4 {{ $gradeSummary['failing'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $gradeSummary['failing'] }}
                        </div>
                    </div>
                </div>

                @if($gradeSummary['failing'] > 0)
                    <div class="alert alert-permanent alert-danger py-2 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        You have <strong>{{ $gradeSummary['failing'] }}</strong> failing subject(s).
                        Please see your instructor.
                    </div>
                @endif

                {{-- CHANGED: Keep the grade chart sizing consistent across breakpoints. --}}
                <div class="chart-container">
                    <canvas id="subjectGradesChart"></canvas>
                </div>

                <div class="table-responsive mt-3" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subjectGrades as $grade)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">{{ $grade->subject_code }}</span>
                                        <div class="small text-muted">{{ $grade->subject_name }}</div>
                                    </td>
                                    <td class="{{ $grade->numerical_grade < 75 ? 'text-danger fw-bold' : ($grade->numerical_grade < 80 ? 'text-warning' : '') }}">
                                        {{ round($grade->numerical_grade, 2) }}%
                                    </td>
                                    <td>{{ $grade->letter_grade ?? '—' }}</td>
                                    <td>
                                        @if($grade->numerical_grade < 75)
                                            <span class="badge bg-danger">Failing</span>
                                        @elseif($grade->numerical_grade < 80)
                                            <span class="badge bg-warning">At Risk</span>
                                        @else
                                            <span class="badge bg-success">Passing</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No grades available for this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header">
                <i class="fas fa-clipboard-check text-primary"></i> Attendance Summary
                <span class="badge bg-primary ms-2">{{ $currentPeriod }}</span>
                <a href="{{ route('student.attendance', ['period' => $currentPeriod]) }}"
                   class="btn btn-sm btn-outline-primary float-end">Full attendance</a>
            </div>
            <div class="card-body">
                @php
                    $attendanceRate = (float) ($attendanceSummary->overall_attendance ?? 0);
                @endphp

                <div class="sp-rate text-center mb-3">
                    <div class="display-6 fw-bold {{ $attendanceRate < 75 ? 'text-danger' : ($attendanceRate < 85 ? 'text-warning' : 'text-success') }}">
                        {{ round($attendanceRate) }}%
                    </div>
                    <div class="text-muted small text-uppercase">Overall Attendance</div>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">
                        <i class="fas fa-times-circle text-danger me-2"></i>Total Absences
                    </span>
                    <span class="fw-bold">{{ $attendanceSummary->total_absences ?? 0 }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">
                        <i class="fas fa-clock text-warning me-2"></i>Total Lates
                    </span>
                    <span class="fw-bold">{{ $attendanceSummary->total_lates ?? 0 }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">
                        <i class="fas fa-check-circle text-success me-2"></i>Total Excused
                    </span>
                    <span class="fw-bold">{{ $attendanceSummary->total_excused ?? 0 }}</span>
                </div>

                @if($attendanceRate > 0 && $attendanceRate < 75)
                    <div class="alert alert-permanent alert-danger py-2 small mb-0 mt-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Your attendance is below the required threshold.
                        <a href="{{ route('student.counselor') }}" class="alert-link">Contact your counselor</a>.
                    </div>
                @endif

                @if($attendanceBreakdown->count() > 0)
                    <div class="table-responsive mt-3" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Rate</th>
                                    <th>Absences</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendanceBreakdown as $attendance)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">{{ $attendance->subject_code }}</span>
                                            <div class="small text-muted">{{ $attendance->subject_name }}</div>
                                        </td>
                                        <td class="{{ $attendance->attendance_rate < 75 ? 'text-danger fw-bold' : ($attendance->attendance_rate < 80 ? 'text-warning' : 'text-success') }}">
                                            {{ round($attendance->attendance_rate) }}%
                                        </td>
                                        <td>{{ $attendance->total_absences ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row ah-reveal" style="--ah-i: 3;">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-list-check text-primary"></i> Intervention Recommendations
                <span class="badge bg-light text-primary ms-2">{{ $recommendations->count() }}</span>
                <a href="{{ route('student.recommendations') }}" class="btn btn-sm btn-light float-end">
                    <i class="fas fa-list-check me-1"></i> Manage recommendations
                </a>
            </div>
            <div class="card-body">
                @if($recommendations->count() > 0)
                    <div class="list-group">
                        @foreach($recommendations as $recommendation)
                            <div class="list-group-item {{ $recommendation->is_completed ? 'list-group-item-success' : '' }}">
                                <div class="d-flex align-items-center mb-2 flex-wrap">
                                    <span class="badge {{ $recommendation->is_completed ? 'bg-success' : 'bg-warning text-dark' }} me-2">
                                        <i class="fas {{ $recommendation->is_completed ? 'fa-check' : 'fa-hourglass-half' }} me-1"></i>
                                        {{ $recommendation->is_completed ? 'Completed' : 'Not Completed' }}
                                    </span>
                                    @if(!empty($recommendation->grading_period))
                                        <span class="badge bg-info text-dark me-2">{{ $recommendation->grading_period }}</span>
                                    @endif
                                    @if(!empty($recommendation->generated_at))
                                        <small class="text-muted">
                                            Generated {{ \Carbon\Carbon::parse($recommendation->generated_at)->format('M j, Y') }}
                                        </small>
                                    @endif
                                </div>

                                @if(!empty($recommendation->risk_factors_list))
                                    <div class="mb-2">
                                        <strong class="small">Risk Factors:</strong>
                                        @foreach($recommendation->risk_factors_list as $factor)
                                            <span class="badge bg-secondary">{{ $factor }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!empty($recommendation->suggested_actions_list))
                                    <div>
                                        <strong class="small">Recommended Actions:</strong>
                                        <ul class="mb-0 mt-1">
                                            @foreach($recommendation->suggested_actions_list as $action)
                                                <li>
                                                    @if(!empty($action['priority']))
                                                        <span class="badge bg-{{ $action['priority'] === 'high' ? 'danger' : ($action['priority'] === 'medium' ? 'warning' : 'info') }}">
                                                            {{ $action['priority'] }}
                                                        </span>
                                                    @endif
                                                    <strong>{{ ucfirst(str_replace('_', ' ', $action['action'] ?? 'Action')) }}</strong>
                                                    @if(!empty($action['details']))
                                                        <span class="text-muted">— {{ $action['details'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-lightbulb fa-3x d-block mb-3 opacity-50"></i>
                        <p class="mb-1">No intervention recommendation has been generated for you yet.</p>
                        <small>
                            Recommendations are generated by your Academic Head from your grades and
                            attendance.
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/student-charts.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('periodSelect')?.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('period', this.value);
            window.location.href = url.toString();
        });
    });
</script>
@endpush
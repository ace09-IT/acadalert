@extends('layouts.app')

@section('title', 'Case Details - AcadAlert')

@php

    $riskTone = static function (?string $level): string {
        return match ((string) $level) {
            'Critical' => 'purple',
            'High' => 'danger',
            'Moderate' => 'warning',
            'Low' => 'success',
            default => 'secondary',
        };
    };

    $riskBadge = static function (?string $level): string {
        return match ((string) $level) {
            'High' => 'badge-risk-high',
            'Moderate' => 'badge-risk-moderate',
            'Low' => 'badge-risk-low',
            'Critical' => 'badge-risk-critical',
            default => 'bg-secondary',
        };
    };

    $priorityTone = static function (?string $priority): string {
        return match ((string) $priority) {
            'Critical' => 'danger',
            'High' => 'warning',
            'Medium' => 'info',
            default => 'secondary',
        };
    };

    $statusTone = static function (?string $status): string {
        return match ((string) $status) {
            'Resolved' => 'success',
            'Closed' => 'dark',
            'Reopened' => 'danger',
            'Awaiting Parent', 'Awaiting Student' => 'warning',
            'Referred' => 'info',
            'In Progress' => 'primary',
            default => 'secondary',
        };
    };

    $isClosed = in_array((string) $case->status, ['Resolved', 'Closed'], true);
    $riskToneNow = $riskTone($currentRisk ?? null);
    $studentName = trim(((string) ($student->first_name ?? '')) . ' ' . ((string) ($student->last_name ?? '')));
@endphp

@section('page_title', 'Case #' . $case->id . ' — ' . ($studentName !== '' ? $studentName : 'Student'))

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-{{ $priorityTone($case->priority) }} p-2">
            <i class="fas fa-flag me-1"></i> {{ $case->priority }}
        </span>
        <span class="badge bg-{{ $statusTone($case->status) }} p-2">
            <i class="fas fa-circle-info me-1"></i> {{ str_replace('_', ' ', (string) $case->status) }}
        </span>
        <span class="badge {{ $riskBadge($currentRisk ?? null) }} p-2">
            <i class="fas fa-triangle-exclamation me-1"></i> Risk: {{ $currentRisk ?? 'N/A' }}
        </span>
        <a href="{{ route('counselor.cases', ['scope' => $isClosed ? 'resolved' : 'open']) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<meta name="student-id" content="{{ $case->student_id ?? 0 }}">

<div class="ah-page gc-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

<div class="row ah-reveal" style="--ah-i: 0;">
    <div class="col-xl-5 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user"></i> Student Information
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small text-uppercase fw-bold">Student Name</div>
                    <div class="fs-5 fw-bold">{{ $studentName !== '' ? $studentName : 'N/A' }}</div>
                    <div class="text-muted small">
                        <i class="fas fa-hashtag me-1"></i>{{ $student->student_number ?? 'N/A' }}
                        @if(!empty($student->email))
                            &middot; <i class="fas fa-envelope me-1"></i>{{ $student->email }}
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small text-uppercase fw-bold">Program</div>
                        <div class="fw-semibold">{{ $student->program_code ?? 'N/A' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small text-uppercase fw-bold">Year Level</div>
                        <div class="fw-semibold">{{ $student->year_level ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small text-uppercase fw-bold">Block</div>
                    <div class="fw-semibold">{{ $student->block_name ?? 'N/A' }}</div>
                </div>

                <hr>

                <div class="mb-3">
                    <div class="text-muted small text-uppercase fw-bold">Primary Parent</div>
                    @if(!empty($student->parent_name))
                        <div class="fw-semibold">{{ $student->parent_name }}</div>
                        @if(!empty($student->parent_contact))
                            <div class="small">
                                <i class="fas fa-phone text-primary me-1"></i>
                                <a href="tel:{{ $student->parent_contact }}">{{ $student->parent_contact }}</a>
                            </div>
                        @endif
                        @if(!empty($student->parent_email))
                            <div class="small">
                                <i class="fas fa-envelope text-primary me-1"></i>
                                <a href="mailto:{{ $student->parent_email }}">{{ $student->parent_email }}</a>
                            </div>
                        @endif
                    @else
                        <div class="text-muted">No primary parent on record.</div>
                    @endif
                </div>

                <hr>

                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small text-uppercase fw-bold">Escalated By</div>
                        <div class="fw-semibold">{{ $case->escalated_by_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small text-uppercase fw-bold">Escalated On</div>
                        <div class="fw-semibold">
                            {{ $case->escalated_at ? \Carbon\Carbon::parse($case->escalated_at)->format('M d, Y') : 'N/A' }}
                        </div>
                    </div>
                </div>

                @if($escalationNotes ?? false)
                    <hr>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Escalation Notes</div>
                        <div class="bg-light p-2 rounded small">{{ $escalationNotes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-7 mb-4">
        <div class="card mb-4 border-{{ $riskToneNow === 'purple' ? 'primary' : $riskToneNow }} ah-glow">
            <div class="card-header bg-{{ $riskToneNow === 'purple' ? 'primary' : $riskToneNow }} text-{{ in_array($riskToneNow, ['warning'], true) ? 'dark' : 'white' }} d-flex justify-content-between align-items-center">
                <span><i class="fas fa-triangle-exclamation"></i> Risk Score &amp; Factors</span>
                <span class="badge bg-light text-dark">{{ $currentPeriod ?? 'N/A' }} &middot; {{ $schoolYear ?? '2024-2025' }}</span>
            </div>
            <div class="card-body ah-hero">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="text-center">
                        <div class="display-4 fw-bold text-{{ $riskToneNow === 'purple' ? 'primary' : $riskToneNow }}">
                            {{ $currentRiskScore ?? 'N/A' }}
                        </div>
                        <div class="small text-muted">Current risk score</div>
                    </div>
                    <div class="text-center">
                        <span class="badge {{ $riskBadge($currentRisk ?? null) }} fs-6 px-3 py-2">
                            {{ $currentRisk ?? 'N/A' }} RISK
                        </span>
                        <div class="small text-muted mt-1">
                            Colour scale: Low (green) &middot; Moderate (yellow) &middot; High (red)
                        </div>
                    </div>
                    @if(isset($improvement) && $improvement !== null)
                        <div class="text-center">
                            <div class="fs-4 fw-bold {{ $improvement > 0 ? 'text-success' : ($improvement < 0 ? 'text-danger' : 'text-muted') }}">
                                @if($improvement > 0)
                                    <i class="fas fa-arrow-down me-1"></i>{{ $improvement }}
                                @elseif($improvement < 0)
                                    <i class="fas fa-arrow-up me-1"></i>{{ abs($improvement) }}
                                @else
                                    <i class="fas fa-minus me-1"></i>0
                                @endif
                            </div>
                            <div class="small text-muted">vs escalation ({{ $case->risk_score_at_escalation ?? 'N/A' }})</div>
                        </div>
                    @endif
                </div>

                <hr>

                <div>
                    <div class="text-muted small text-uppercase fw-bold mb-2">Risk Factors</div>
                    @forelse($riskFactors ?? [] as $factor)
                        <div class="d-flex align-items-start mb-1">
                            <i class="fas fa-circle text-{{ $riskToneNow === 'purple' ? 'primary' : $riskToneNow }} mt-1 me-2" style="font-size: 0.45rem;"></i>
                            <span>{{ $factor }}</span>
                        </div>
                    @empty
                        <span class="text-muted small">No risk factors recorded for this period.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card mb-4 ah-glow">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <span><i class=""></i> Intervention Recommendation</span>
                @if(($forwardedRecommendation->edited ?? false))
                    <span class="badge bg-dark">Edited by Academic Head</span>
                @endif
            </div>
            <div class="card-body">
                @if($forwardedRecommendation)
                    <div class="bg-light border rounded p-3 ah-quote" style="white-space: pre-wrap;">{{ $forwardedRecommendation->text }}</div>

                    <div class="small text-muted mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Forwarded by the Academic Head when this case was escalated
                        @if($forwardedRecommendation->grading_period)
                            ({{ $forwardedRecommendation->grading_period }})
                        @endif
                        @if($forwardedRecommendation->generated_at)
                            &middot; generated {{ \Carbon\Carbon::parse($forwardedRecommendation->generated_at)->format('M d, Y h:i A') }}
                        @endif
                    </div>

                    @if(count($forwardedRecommendation->actions ?? []) > 0)
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#structuredRecommendation"
                                    aria-expanded="false" aria-controls="structuredRecommendation">
                                <i class="fas fa-list me-1"></i> Structured view
                            </button>
                            <div class="collapse mt-2" id="structuredRecommendation">
                                <ul class="mb-0 small">
                                    @foreach($forwardedRecommendation->actions as $action)
                                        <li class="mb-1">
                                            <span class="badge bg-{{ $action['priority'] === 'high' ? 'danger' : ($action['priority'] === 'medium' ? 'warning' : 'info') }} me-1">
                                                {{ $action['priority'] }}
                                            </span>
                                            {{ $action['details'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="alert alert-secondary mb-2">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>No recommendation provided.</strong>
                    </div>
                    <p class="text-muted small mb-0">
                        This case was escalated without an intervention recommendation. The risk score,
                        risk factors, grades, attendance and risk trend are still shown here so you can
                        decide the intervention yourself, and anything you log below is recorded on the case.
                    </p>
                @endif
            </div>
        </div>

        <div class="card ah-glow">
            <div class="card-header bg-success text-white">
                <i class="fas fa-chart-line"></i> Intervention Effectiveness
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    <i class=" text-warning me-1"></i>
                    Measured against the forwarded intervention:
                    @if($forwardedRecommendation)
                        <span class="fw-bold">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $forwardedRecommendation->text), 140) }}</span>
                        @if($forwardedRecommendation->edited)
                            <span class="badge bg-dark ms-1">edited</span>
                        @endif
                    @else
                        <span class="fst-italic">no recommendation was provided with this escalation.</span>
                    @endif
                </div>

                <div class="row g-2 mb-3 ah-compare">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded h-100">
                            <div class="text-muted small">Before Intervention</div>
                            <div class="fw-bold text-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                {{ $case->risk_score_at_escalation ?? 'N/A' }} -
                                {{ $case->risk_level_at_escalation ?? 'N/A' }}
                            </div>
                            <small class="text-muted">
                                {{ $case->escalated_at ? \Carbon\Carbon::parse($case->escalated_at)->format('M d, Y') : 'N/A' }}
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded h-100">
                            <div class="text-muted small">After Intervention</div>
                            <div class="fw-bold {{ ($improvement ?? 0) > 0 ? 'text-success' : 'text-warning' }}">
                                @if(isset($improvement) && $improvement > 0)
                                    <i class="fas fa-arrow-down me-1"></i> {{ $currentRiskScore ?? 'N/A' }}
                                @elseif(isset($improvement) && $improvement < 0)
                                    <i class="fas fa-arrow-up me-1"></i> {{ $currentRiskScore ?? 'N/A' }}
                                @else
                                    <i class="fas fa-clock me-1"></i> Pending
                                @endif
                            </div>
                            <small class="text-muted">
                                {{ $currentPeriod ?? 'N/A' }} &middot; {{ $currentRisk ?? 'N/A' }}
                            </small>
                        </div>
                    </div>
                </div>

                @if(isset($improvement) && $improvement > 0)
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Improvement: -{{ $improvement }} points — the intervention is working.
                    </div>
                @elseif(isset($improvement) && $improvement < 0)
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Worsening: +{{ abs($improvement) }} points — the intervention needs review.
                    </div>
                @else
                    <div class="alert alert-info mb-2">
                        <i class="fas fa-info-circle me-2"></i>
                        @if($nextPeriod)
                            Next grading period: <strong>{{ $nextPeriod }}</strong> — effectiveness becomes
                            measurable once it is scored.
                        @else
                            {{ $currentPeriod ?? 'This period' }} is the final grading period of the year —
                            effectiveness will be confirmed at the end of the term.
                        @endif
                    </div>
                @endif

                <div class="chart-container" style="height: 190px;">
                    <canvas id="studentRiskTrendChart" data-student-id="{{ $case->student_id ?? 0 }}"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="fas fa-history text-primary"></i> Session History
                    <span class="badge bg-secondary ms-2">{{ $sessions->count() }}</span>
                </span>
                <div class="d-flex flex-wrap gap-2 ah-actions">
                    <button type="button" class="btn btn-sm btn-primary" id="logSessionBtn"
                            data-bs-toggle="modal" data-bs-target="#sessionModal">
                        <i class="fas fa-plus me-1"></i> Log Session
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info"
                            data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        <i class="fas fa-exchange-alt me-1"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning"
                            data-bs-toggle="modal" data-bs-target="#updatePriorityModal">
                        <i class="fas fa-flag me-1"></i> Update Priority
                    </button>
                    <button type="button" class="btn btn-sm btn-success"
                            data-bs-toggle="modal" data-bs-target="#resolveModal" @disabled($isClosed)>
                        <i class="fas fa-check me-1"></i> Mark as Resolved
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#reopenModal" @disabled(!$isClosed)>
                        <i class="fas fa-redo me-1"></i> Reopen Case
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                @if($sessions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-mobile-cards">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Notes</th>
                                    <th>Action Taken</th>
                                    <th>Follow-up</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $session)
                                    @php
                                        $followUpDue = $session->follow_up_date
                                            ? \Carbon\Carbon::parse($session->follow_up_date)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td data-label="Date" class="text-nowrap">
                                            {{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}
                                        </td>
                                        <td data-label="Type">
                                            <span class="badge bg-secondary">{{ $session->session_type }}</span>
                                        </td>
                                        <td data-label="Notes">{{ $session->notes }}</td>
                                        <td data-label="Action Taken">{{ $session->action_taken ?? 'None recorded' }}</td>
                                        <td data-label="Follow-up" class="text-nowrap">
                                            @if($followUpDue)
                                                <span class="{{ $followUpDue->isPast() ? 'text-danger fw-bold' : '' }}">
                                                    {{ $followUpDue->format('M d, Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge bg-{{ $statusTone($session->status_after_session ?? 'In Progress') }}">
                                                {{ str_replace('_', ' ', (string) ($session->status_after_session ?? 'In Progress')) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-comment-dots fa-2x d-block mb-2 opacity-50"></i>
                        <p class="mb-2">No sessions logged yet.</p>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-bs-toggle="modal" data-bs-target="#sessionModal">
                            <i class="fas fa-plus me-1"></i> Log the first session
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-lg-7 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book text-primary"></i> Grades</span>
                <span class="badge bg-secondary">{{ $currentPeriod ?? 'N/A' }}</span>
            </div>
            <div class="card-body">
                @if(($grades['total'] ?? 0) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Code</th>
                                    <th class="text-end">Grade</th>
                                    <th>Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($grades['rows'] as $gradeRow)
                                    <tr class="{{ $gradeRow->numerical_grade < 75 ? 'table-danger' : ($gradeRow->numerical_grade < 80 ? 'table-warning' : '') }}">
                                        <td>{{ $gradeRow->subject_name ?: 'N/A' }}</td>
                                        <td>{{ $gradeRow->subject_code ?: 'N/A' }}</td>
                                        <td class="text-end fw-bold">{{ number_format((float) $gradeRow->numerical_grade, 2) }}</td>
                                        <td>
                                            @if($gradeRow->numerical_grade < 75)
                                                <span class="badge bg-danger">Failing</span>
                                            @else
                                                <span class="badge bg-success">Passing</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="2">Average ({{ $grades['total'] }} subject(s))</th>
                                    <th class="text-end">
                                        {{ $grades['average'] !== null ? number_format($grades['average'], 2) : 'N/A' }}
                                    </th>
                                    <th>
                                        @if($grades['failing'] > 0)
                                            <span class="badge bg-danger">{{ $grades['failing'] }} failing</span>
                                        @else
                                            <span class="badge bg-success">No failures</span>
                                        @endif
                                    </th>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-muted small">
                                        Highest {{ $grades['highest'] !== null ? number_format($grades['highest'], 2) : 'N/A' }}
                                        &middot;
                                        Lowest {{ $grades['lowest'] !== null ? number_format($grades['lowest'], 2) : 'N/A' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        No grades recorded for {{ $currentPeriod ?? 'this period' }}.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card h-100 ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clipboard-check text-primary"></i> Attendance</span>
                <span class="badge bg-secondary">{{ $currentPeriod ?? 'N/A' }}</span>
            </div>
            <div class="card-body">
                @php
                    $attendanceSummary = $attendance['summary'] ?? null;
                    $attendanceRate = $attendanceSummary->overall_attendance ?? null;
                    $attendanceRateValue = $attendanceRate !== null ? (float) $attendanceRate : null;
                @endphp

                <div class="row text-center g-2">
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Attendance Rate</div>
                            <div class="fw-bold {{ $attendanceRateValue !== null && $attendanceRateValue < 80 ? 'text-danger' : '' }}">
                                {{ $attendanceRateValue !== null ? number_format($attendanceRateValue, 2) . '%' : 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Total Absences</div>
                            <div class="fw-bold">{{ $attendanceSummary->total_absences ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Total Lates</div>
                            <div class="fw-bold">{{ $attendanceSummary->total_lates ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Total Excused</div>
                            <div class="fw-bold">{{ $attendanceSummary->total_excused ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                @if(($attendance['subjects'] ?? collect())->count() > 0)
                    <button class="btn btn-sm btn-outline-secondary mt-3" type="button"
                            data-bs-toggle="collapse" data-bs-target="#attendanceBreakdown"
                            aria-expanded="false" aria-controls="attendanceBreakdown">
                        <i class="fas fa-list me-1"></i> Per-subject breakdown
                    </button>
                    <div class="collapse mt-2" id="attendanceBreakdown">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Abs.</th>
                                        <th class="text-end">Lates</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendance['subjects'] as $subjectAttendance)
                                        <tr class="{{ (float) ($subjectAttendance->attendance_rate ?? 100) < 80 ? 'table-warning' : '' }}">
                                            <td>{{ $subjectAttendance->subject_code ?: 'N/A' }}</td>
                                            <td class="text-end">{{ number_format((float) ($subjectAttendance->attendance_rate ?? 0), 2) }}%</td>
                                            <td class="text-end">{{ $subjectAttendance->total_absences ?? 0 }}</td>
                                            <td class="text-end">{{ $subjectAttendance->total_lates ?? 0 }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="form-text mt-2">
                    Source: attendance_summaries for {{ $currentPeriod ?? 'this period' }} (mock API).
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 3;">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="fas fa-arrow-trend-up text-primary"></i> Risk Trend
                    <small class="text-muted ms-2">Prelim &rarr; Finals, period by period</small>
                </span>
                @php
                    $overallTrend = $riskTrend['overall'] ?? 'unknown';
                    $overallTone = $overallTrend === 'improving'
                        ? 'success'
                        : ($overallTrend === 'worsening' ? 'danger' : 'secondary');
                @endphp
                <span class="badge bg-{{ $overallTone }}">
                    Overall: {{ ucfirst($overallTrend) }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Score</th>
                                <th>Level</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($riskTrend['rows'] ?? [] as $trendRow)
                                <tr>
                                    <td>{{ $trendRow['period'] }}</td>
                                    <td class="text-end fw-bold">{{ $trendRow['score'] }}</td>
                                    <td>
                                        <span class="badge {{ $riskBadge($trendRow['level']) }}">
                                            {{ $trendRow['level'] }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($trendRow['trend'] === 'improving')
                                            <span class="text-success">
                                                <i class="fas fa-arrow-down me-1"></i>Improving
                                                @if($trendRow['delta'] !== null)({{ $trendRow['delta'] }})@endif
                                            </span>
                                        @elseif($trendRow['trend'] === 'worsening')
                                            <span class="text-danger">
                                                <i class="fas fa-arrow-up me-1"></i>Worsening
                                                @if($trendRow['delta'] !== null)(+{{ $trendRow['delta'] }})@endif
                                            </span>
                                        @else
                                            <span class="text-muted"><i class="fas fa-minus me-1"></i>Stable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted small text-center py-3">
                                        No risk scores recorded yet.
                                    </td>
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



<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="sessionModalLabel">
                    <i class="fas fa-plus me-2"></i> Log Session
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('counselor.session.store', $case->id) }}"
                  id="sessionForm" class="needs-validation" novalidate>
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Please fix the following:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="sessionDate">
                                Session Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="sessionDate" name="session_date"
                                   value="{{ old('session_date', date('Y-m-d')) }}" required>
                            <div class="invalid-feedback">Please choose the date the session took place.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="sessionType">
                                Session Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="sessionType" name="session_type" required>
                                <option value="" disabled @selected(!old('session_type'))>Select a type...</option>
                                @foreach(['In-person', 'Virtual', 'Phone Call', 'Email', 'Phone', 'Parent Meeting'] as $typeOption)
                                    <option value="{{ $typeOption }}" @selected(old('session_type') === $typeOption)>
                                        {{ $typeOption }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">Please choose how the session was held.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="sessionNotes">
                            Notes <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="sessionNotes" name="notes" rows="3" required
                                  minlength="5"
                                  placeholder="Observations, discussion points, what the student said...">{{ old('notes') }}</textarea>
                        <div class="invalid-feedback">
                            Please record at least a short note (5 characters or more) about the session.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="sessionAction">Action Taken</label>
                        <input type="text" class="form-control" id="sessionAction" name="action_taken"
                               value="{{ old('action_taken') }}"
                               placeholder="e.g., Referred to tutoring">
                        <div class="form-text">What you did as a result of this session.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="sessionFollowUp">Follow-up Date</label>
                            <input type="date" class="form-control" id="sessionFollowUp" name="follow_up_date"
                                   value="{{ old('follow_up_date') }}">
                            <div class="form-text">Must be after the session date, if set.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="sessionStatus">
                                Update Case Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="sessionStatus" name="status_after_session" required>
                                @foreach([
                                    'In Progress' => 'In Progress',
                                    'Awaiting Parent' => 'Awaiting Parent',
                                    'Awaiting Student' => 'Awaiting Student',
                                    'Referred' => 'Referred',
                                    'Resolved' => 'Resolved',
                                    'Closed' => 'Closed',
                                ] as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}"
                                        @selected(old('status_after_session', str_replace('_', ' ', (string) $case->status)) === $statusValue)>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">Please choose the status this session leaves the case in.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="updateStatusModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i> Update Case Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('counselor.status.update', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Status</label>
                        <p class="fw-bold mb-0">{{ str_replace('_', ' ', (string) $case->status) }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="newStatus">New Status</label>
                        <select class="form-select" id="newStatus" name="status" required>
                            @foreach([
                                'New', 'In Progress', 'Awaiting Parent', 'Awaiting Student',
                                'Referred', 'Resolved', 'Closed', 'Reopened',
                            ] as $statusValue)
                                <option value="{{ $statusValue }}" @selected($statusValue === 'In Progress')>
                                    {{ $statusValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updatePriorityModal" tabindex="-1" aria-labelledby="updatePriorityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="updatePriorityModalLabel">
                    <i class="fas fa-flag me-2"></i> Update Priority
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('counselor.priority.update', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Priority</label>
                        <p class="fw-bold mb-0">{{ $case->priority }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="newPriority">New Priority</label>
                        <select class="form-select" id="newPriority" name="priority" required>
                            @foreach(['Critical', 'High', 'Medium', 'Low'] as $priorityOption)
                                <option value="{{ $priorityOption }}" @selected($priorityOption === $case->priority)>
                                    {{ $priorityOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Priority
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="resolveModalLabel">
                    <i class="fas fa-check me-2"></i> Mark Case as Resolved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('counselor.resolve', $case->id) }}"
                  class="needs-validation" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="resolvedReason">
                            Resolution Notes <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="resolvedReason" name="resolved_reason" rows="3" required
                                  minlength="10"
                                  placeholder="Describe how the case was resolved...">{{ old('resolved_reason') }}</textarea>
                        <div class="invalid-feedback">
                            Please describe the resolution in at least 10 characters — it becomes part of the case record.
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        This marks the case <strong>Resolved</strong>, stamps the resolution date and logs a
                        closing entry in the session history.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" @disabled($isClosed)>
                        <i class="fas fa-check me-1"></i> Mark Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="reopenModal" tabindex="-1" aria-labelledby="reopenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="reopenModalLabel">
                    <i class="fas fa-redo me-2"></i> Reopen Case
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('counselor.reopen', $case->id) }}"
                  class="needs-validation" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="reopenReason">
                            Reason for Reopening <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="reopenReason" name="reopen_reason" rows="3" required
                                  minlength="10"
                                  placeholder="Explain why this case needs to be reopened...">{{ old('reopen_reason') }}</textarea>
                        <div class="invalid-feedback">Please give a reason of at least 10 characters.</div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This closes the resolved case and creates a new linked case with status
                        <strong>Reopened</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" @disabled(!$isClosed)>
                        <i class="fas fa-redo me-1"></i> Reopen Case
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var chartRendered = false;

    function pointColors(scores) {
        return scores.map(function (score) {
            if (score >= 71) { return '#dc3545'; }
            if (score >= 41) { return '#ffc107'; }
            return '#28a745';
        });
    }

    function drawTrend(labels, scores) {
        var canvas = document.getElementById('studentRiskTrendChart');

        if (chartRendered || !canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        chartRendered = true;

        new window.Chart(context, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Risk Score',
                    data: scores,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: pointColors(scores),
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Risk Score: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { stepSize: 20 }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function drawInlineTrend() {
        var history = @json($riskHistory);

        if (!history || !history.length) {
            return;
        }

        drawTrend(
            history.map(function (row) { return row.grading_period; }),
            history.map(function (row) { return row.risk_score; })
        );
    }

    function loadTrend() {
        var meta = document.querySelector('meta[name="student-id"]');
        var studentId = meta ? meta.getAttribute('content') : '0';

        if (!studentId || studentId === '0') {
            drawInlineTrend();
            return;
        }

        fetch('/charts/counselor/student-risk/' + encodeURIComponent(studentId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                var data = payload && payload.data ? payload.data : null;

                if (!payload || !payload.success || !data || !data.datasets || !data.datasets[0]) {
                    drawInlineTrend();
                    return;
                }

                drawTrend(data.labels || [], data.datasets[0].data || []);
            })
            .catch(function () {
                // The API is optional: the server-rendered history is the fallback.
                drawInlineTrend();
            });
    }

    function bindModalTriggers() {
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            return;
        }

        Array.prototype.forEach.call(
            document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target]'),
            function (trigger) {
                var target = document.querySelector(trigger.getAttribute('data-bs-target'));

                if (target) {
                    window.bootstrap.Modal.getOrCreateInstance(target);
                }
            }
        );
    }

    function bindValidation() {
        Array.prototype.forEach.call(
            document.querySelectorAll('form.needs-validation'),
            function (form) {
                form.addEventListener('submit', function (event) {
                    if (form.checkValidity()) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    form.classList.add('was-validated');

                    var firstInvalid = form.querySelector(':invalid');

                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                });
            }
        );

        // A follow-up must be AFTER the session date (server rule: after:session_date).
        var sessionDate = document.getElementById('sessionDate');
        var followUp = document.getElementById('sessionFollowUp');

        if (!sessionDate || !followUp) {
            return;
        }

        var syncMinimum = function () {
            followUp.min = sessionDate.value;

            if (followUp.value && followUp.value <= sessionDate.value) {
                followUp.value = '';
            }
        };

        sessionDate.addEventListener('change', syncMinimum);
        syncMinimum();
    }

    function announceFlash() {
        var alerts = document.querySelectorAll('.alert-success');

        if (!alerts.length || typeof window.showToast !== 'function') {
            return;
        }

        var text = alerts[0].textContent.replace(/\s+/g, ' ').trim();

        if (text) {
            window.showToast(text, 'success');
        }
    }

    function init() {
        bindModalTriggers();
        bindValidation();
        announceFlash();
        loadTrend();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush

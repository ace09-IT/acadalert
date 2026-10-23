@extends('layouts.app')

@section('title', 'Block Dashboard - AcadAlert')

@section('page_title', 'Block Dashboard')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-users me-1"></i> {{ $blockName ?? 'Block' }}
        </span>
        
        <div class="d-inline-block me-2" style="min-width: 150px;">
            <select class="form-select form-select-sm d-inline-block" id="gradingPeriodSelect" 
                    style="width: auto; display: inline-block; padding: 0.25rem 2rem 0.25rem 0.75rem; border-radius: 0.375rem; background-color: #fff; border: 1px solid #ced4da;">
                <option value="Prelim" {{ $currentPeriod == 'Prelim' ? 'selected' : '' }}>Prelim</option>
                <option value="Midterm" {{ $currentPeriod == 'Midterm' ? 'selected' : '' }}>Midterm</option>
                <option value="Finals" {{ $currentPeriod == 'Finals' ? 'selected' : '' }}>Finals</option>
            </select>
        </div>
        
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-arrow-left me-1"></i> Previous: {{ $previousPeriod ?? 'Prelim' }}
        </span>
        
        <button class="btn btn-sm btn-success" id="runRiskScoringBtn">
            <i class=""></i> Run AI Risk Scoring
        </button>
        
        <button class="btn btn-sm btn-warning" id="refreshScoringBtn" style="display: none;">
            <i class="fas fa-sync me-1"></i> Refresh Scoring
        </button>
        
        <a href="{{ route('academic-head.blocks', ['programId' => $block->program_id ?? 0]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<meta name="block-id" content="{{ $blockId ?? 0 }}">
@php
    $blockTotalStudents = (int) ($totalStudents ?? 0);
    $blockHighRisk = (int) ($highRiskCount ?? 0);
    $blockModerateRisk = (int) ($moderateRiskCount ?? 0);
    $blockLowRisk = (int) ($lowRiskCount ?? 0);
    $blockScored = $blockHighRisk + $blockModerateRisk + $blockLowRisk;
    $blockUnmonitored = max(0, $blockTotalStudents - $blockScored);
@endphp

<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="row ah-reveal" style="--ah-i: 0;">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Students in Block</div>
                    <div class="stat-number">{{ number_format($blockTotalStudents) }}</div>
                    <small class="opacity-75">{{ number_format($blockUnmonitored) }} without a {{ $currentPeriod ?? 'Midterm' }} score</small>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk</div>
                    <div class="stat-number">{{ number_format($blockHighRisk) }}</div>
                    <small class="opacity-75">escalate to the counselor</small>
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
                    <div class="stat-number">{{ number_format($blockModerateRisk) }}</div>
                    <small class="opacity-75">monitor next period</small>
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
                    <div class="stat-number">{{ number_format($blockLowRisk) }}</div>
                    <small class="opacity-75">no intervention required</small>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
</div>


<div class="row ah-reveal" style="--ah-i: 1;">
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-chart-pie text-primary"></i>
                Risk Distribution - {{ $currentPeriod ?? 'Midterm' }}
            </div>
            <div class="card-body">
                <div class="chart-container block-risk-chart-stage">
                    <canvas id="blockRiskChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge badge-risk-low me-2">Low: {{ $lowRiskCount ?? 0 }}</span>
                    <span class="badge badge-risk-moderate me-2">Moderate: {{ $moderateRiskCount ?? 0 }}</span>
                    <span class="badge badge-risk-high">High: {{ $highRiskCount ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-filter text-primary"></i>
                Filters
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" for="studentSearch">Search student</label>
                        <input type="search" class="form-control form-control-sm" id="studentSearch"
                               placeholder="Name or student number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold" for="filterRisk">Risk Level</label>
                        <select class="form-select form-select-sm" id="filterRisk">
                            <option value="all">All</option>
                            <option value="Low">Low</option>
                            <option value="Moderate">Moderate</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold" for="filterTrend">Risk Trend</label>
                        <select class="form-select form-select-sm" id="filterTrend">
                            <option value="all">All</option>
                            <option value="worsening">Worsening</option>
                            <option value="stable">Stable</option>
                            <option value="improving">Improving</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Grade</label>
                        <select class="form-select form-select-sm" id="filterGrade">
                            <option value="all">All</option>
                            <option value="below75">Below 75</option>
                            <option value="below80">Below 80</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Attendance</label>
                        <select class="form-select form-select-sm" id="filterAttendance">
                            <option value="all">All</option>
                            <option value="below80">Below 80%</option>
                            <option value="below70">Below 70%</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Status</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                            <option value="all">All</option>
                            <option value="open">Open case</option>
                            <option value="critical">Critical (repeat high risk)</option>
                            <option value="none">No escalation</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <button class="btn btn-sm btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ah-reveal" style="--ah-i: 2;">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-users text-primary"></i>
                Students
                <span class="badge bg-secondary ms-2" id="studentCount">{{ $totalStudents ?? 0 }}</span>
                <span class="text-muted small ms-2">
                    Current: {{ $currentPeriod ?? 'Midterm' }} | Previous: {{ $previousPeriod ?? 'Prelim' }}
                </span>
                <div class="float-end">
                    <span class="badge bg-success me-1"><i class="fas fa-arrow-up me-1"></i> Improving</span>
                    <span class="badge bg-warning me-1"><i class="fas fa-minus me-1"></i> Stable</span>
                    <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i> Worsening</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-mobile-cards" id="studentTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th class="sortable" data-column="1">Student <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="2">Grade <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="3">Attendance <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="4">Current Risk <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="5">Previous Risk <i class="fas fa-sort text-muted"></i></th>
                                <th>Trend</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            @forelse($students as $student)
                            <tr data-risk="{{ $student->current_risk }}"
                                data-grade="{{ $student->grade }}"
                                data-attendance="{{ $student->attendance }}"
                                data-score="{{ (int) ($student->risk_score ?? 0) }}"
                                data-trend="{{ $student->trend }}"
                                data-status="{{ $student->has_open_case ? 'open' : ($student->is_critical ? 'critical' : 'none') }}"
                                data-search="{{ strtolower($student->last_name . ' ' . $student->first_name . ' ' . $student->student_number) }}"
                                data-student-id="{{ $student->id }}"
                                class="{{ $student->is_critical ? 'table-danger' : '' }}">
                                <td data-label="">
                                    <input type="checkbox" class="student-checkbox" value="{{ $student->id }}">
                                </td>
                                <td data-label="Student">
                                    <strong>{{ $student->last_name }}, {{ $student->first_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $student->student_number }}</small>
                                </td>
                                <td data-label="Grade">
                                    <span class="{{ $student->grade_color }}">
                                        {{ $student->grade_formatted ?? 'N/A' }}%
                                    </span>
                                </td>
                                <td data-label="Attendance">
                                    <span class="{{ $student->attendance_color }}">
                                        {{ $student->attendance_formatted ?? 'N/A' }}%
                                    </span>
                                </td>
                                <td data-label="Current Risk">
                                    <span class="badge {{ $student->current_risk_class }}">
                                        {{ $student->current_risk ?? 'Low' }}
                                    </span>
                                    @if($student->risk_score > 0)
                                        <br>
                                        <small class="text-muted">Score: {{ $student->risk_score }}</small>
                                    @endif
                                </td>
                                <td data-label="Previous Risk">
                                    <span class="badge {{ $student->previous_risk_class }}">
                                        {{ $student->previous_risk ?? 'N/A' }}
                                    </span>
                                </td>
                                <td data-label="Trend">
                                    <span class="{{ $student->trend_class }}">
                                        <i class="fas {{ $student->trend_icon }} me-1"></i> {{ $student->trend_display }}
                                    </span>
                                </td>
                                <td data-label="Status" class="status-cell">
                                    @if($student->has_open_case)
                                        <span class="badge bg-info">
                                            <i class="fas fa-folder-open me-1"></i> Open Case
                                        </span>
                                        <br>
                                        <small class="text-muted">Case #{{ $student->case_id ?? 'N/A' }}</small>
                                    @elseif($student->is_critical)
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Critical
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">—</span>
                                    @endif
                                </td>
                                <td data-label="Action">
                                    @if($student->has_open_case)
                                        <button class="btn btn-sm btn-outline-danger escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}"
                                                disabled>
                                            <i class="fas fa-arrow-up me-1"></i> Escalate
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning reset-escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}">
                                            <i class="fas fa-undo me-1"></i> Reset
                                        </button>
                                        <a href="{{ route('academic-head.block.student.basis', ['blockId' => $blockId, 'studentId' => $student->id]) }}" class="btn btn-sm btn-outline-info view-basis-btn">
                                            <i class="fas fa-eye me-1"></i> View Basis
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-outline-danger escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}">
                                            <i class="fas fa-arrow-up me-1"></i> Escalate
                                        </button>
                                        <a href="{{ route('academic-head.block.student.basis', ['blockId' => $blockId, 'studentId' => $student->id]) }}" class="btn btn-sm btn-outline-info view-basis-btn">
                                            <i class="fas fa-eye me-1"></i> View Basis
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x d-block mb-2"></i>
                                    No students found in this block.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 d-flex flex-wrap gap-2" id="bulkActions">
                    <button class="btn btn-sm btn-danger" id="bulkEscalateBtn" disabled>
                        <i class="fas fa-arrow-up me-1"></i> Escalate Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-warning" id="bulkEscalateAllBtn">
                        <i class="fas fa-flag me-1"></i> Escalate All Flagged
                    </button>
                    
                    <button class="btn btn-sm btn-warning" id="bulkResetBtn" disabled>
                        <i class="fas fa-undo me-1"></i> Reset Selected (<span id="resetSelectedCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-secondary" id="bulkResetAllBtn">
                        <i class="fas fa-undo-alt me-1"></i> Reset All Escalations
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="escalationModal" tabindex="-1" aria-labelledby="escalationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="escalationModalLabel">
                    <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" aria-hidden="true" class="escalation-mark">
                    <span>Escalate to Guidance Counselor</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="escalationCount">0</span> student(s) will be escalated.
                </div>

                <!-- Escalation Warning for already escalated students -->
                <div id="escalationWarning" class="alert alert-info" style="display: none;">
                    <i class="fas fa-info-circle me-2"></i>
                    Some students already have open cases and will be skipped.
                </div>

                <div id="escalationStudentList" class="mb-3">
                    <div class="text-center py-2 text-muted small">
                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                        Loading student data...
                    </div>
                </div>

                <div class="mb-3 escalation-notes">
                    <label class="form-label fw-bold" for="escalationNotes">
                        <i class="fas fa-pen-to-square" aria-hidden="true"></i> Notes (optional)
                    </label>
                    <textarea class="form-control" id="escalationNotes" rows="3" maxlength="1000"
                              placeholder="Add any context for the counselor..."></textarea>
                    <div class="form-text">
                        Sent to the counselor together with the case, whether or not a
                        recommendation is included.
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <span class="text-muted small" id="recommendationSummary">
                    <i class=""></i> No recommendation selected yet.
                </span>
                <span class="escalation-actions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmEscalationBtn">
                        <i class="fas fa-check me-1"></i> Escalate to Counselor
                    </button>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetEscalationModal" tabindex="-1" aria-labelledby="resetEscalationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="resetEscalationModalLabel">
                    <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" aria-hidden="true" class="reset-mark">
                    <span>Reset Escalation</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This will <strong>permanently delete</strong> the escalation record and case for <span id="resetCount">0</span> student(s).
                </div>
                <div id="resetStudentList" class="mb-3"></div>
                <div class="mb-3 reset-notes">
                    <label class="form-label fw-bold">Reason for Reset</label>
                    <textarea class="form-control" id="resetReason" rows="2" 
                              placeholder="Optional: Why is this escalation being reset?"></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Only cases with status <strong>New</strong> or <strong>In Progress</strong> can be reset.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetBtn">
                    <i class="fas fa-check me-1"></i> Confirm Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scoringModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="scoringModalLabel">
                    <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" class="scoring-mark">
                    <span>AI Risk Scoring in Progress</span>
                </h5>
            </div>
            <div class="modal-body scoring-body">
                <div class="text-center">
                    <div class="scoring-panel">
                        <div class="spinner-border scoring-spinner" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="fw-bold scoring-status" id="scoringStatus">Processing student <span id="currentStudent">1</span> of <span id="totalStudents">{{ $totalStudents ?? 20 }}</span>...</p>
                        <div class="progress scoring-progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                 id="scoringProgress" style="width: 5%;">5%</div>
                        </div>
                        <p class="scoring-detail" id="currentStudentName">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const ctx = document.getElementById('blockRiskChart')?.getContext('2d');

            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Low Risk', 'Moderate Risk', 'High Risk'],
                        datasets: [{
                            data: [
                                {{ $lowRiskCount ?? 0 }},
                                {{ $moderateRiskCount ?? 0 }},
                                {{ $highRiskCount ?? 0 }}
                            ],
                            backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                            borderWidth: 2,
                            borderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true,
                        /* CHANGED: Fill the compact stage so the doughnut and legend use the card width. */
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    padding: 12,
                                }
                            }
                        },
                        cutout: '65%',
                    }
                });
            }
        } catch (error) {
            console.error('[Block Dashboard] Risk chart failed to render:', error);
        }

        bindBlockDashboardUi();

        checkScoringStatus();

        setTimeout(updateEscalationIndicators, 1500);

        document.getElementById('gradingPeriodSelect')?.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('period', this.value);
            window.location.href = currentUrl.toString();
        });
    });

    let blockDashboardUiBound = false;

    function bindBlockDashboardUi() {
        if (blockDashboardUiBound) {
            return;
        }

        blockDashboardUiBound = true;

        const CLICK_ACTIONS = {
            'bulkEscalateBtn': bulkEscalate,
            'bulkEscalateAllBtn': bulkEscalateAll,
            'bulkResetBtn': bulkResetEscalation,
            'bulkResetAllBtn': bulkResetAllEscalations,
            'confirmEscalationBtn': confirmEscalation,
            'confirmResetBtn': confirmResetEscalation,
            'runRiskScoringBtn': runRiskScoring,
            'refreshScoringBtn': refreshRiskScoring,
            'applyFiltersBtn': applyFilters,
            'resetFiltersBtn': resetFilters,
        };

        document.addEventListener('click', function(event) {
            const escalateBtn = event.target.closest('.escalate-btn');


            if (escalateBtn) {
                event.preventDefault();

                if (!escalateBtn.disabled && escalateBtn.dataset.student) {
                    openEscalationModal([escalateBtn.dataset.student]);
                }
                return;
            }

            // Sortable column headers. sortTable() existed but was never called — the
            // headers looked clickable and did nothing.
            const sortHeader = event.target.closest('th.sortable');

            if (sortHeader) {
                event.preventDefault();

                const column = parseInt(sortHeader.dataset.column, 10);

                if (!isNaN(column)) {
                    sortTable(column);
                }
                return;
            }

            const editBtn = event.target.closest('.btn-edit-recommendation');
            if (editBtn) {
                event.preventDefault();
                toggleRecommendationEditor(editBtn.dataset.student);
                return;
            }

            const resetRecBtn = event.target.closest('.btn-reset-recommendation');

            if (resetRecBtn) {
                event.preventDefault();
                resetRecommendationToGenerated(resetRecBtn.dataset.student);
                return;
            }

            const resetBtn = event.target.closest('.reset-escalate-btn');

            if (resetBtn) {
                event.preventDefault();

                if (resetBtn.dataset.student && confirm(`Reset escalation for ${resetBtn.dataset.name}?`)) {
                    openResetModal([resetBtn.dataset.student]);
                }
                return;
            }

            for (const id in CLICK_ACTIONS) {
                if (event.target.closest('#' + id)) {
                    event.preventDefault();
                    CLICK_ACTIONS[id]();
                    return;
                }
            }
        });

        document.addEventListener('change', function(event) {
            const target = event.target;

            if (target.matches('.student-checkbox')) {
                updateSelectedCount();
            }

            if (target.matches('#selectAll')) {
                document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = target.checked);
                updateSelectedCount();
            }

            if (target.matches('.include-recommendation')) {
                syncRecommendationDraft(target.dataset.student, { include: target.checked });
            }

            if (target.matches('.recommendation-textarea')) {
                syncRecommendationDraft(target.dataset.editedFor, { text: target.value });
            }

            if (target.matches('#filterRisk') || target.matches('#filterTrend')
                || target.matches('#filterGrade') || target.matches('#filterAttendance')
                || target.matches('#filterStatus')) {
                applyFilters();
            }
        });

        document.addEventListener('input', function(event) {
            if (event.target.matches('.recommendation-textarea')) {
                syncRecommendationDraft(event.target.dataset.editedFor, { text: event.target.value });
            }

            if (event.target.matches('#studentSearch')) {
                applyFilters();
            }
        });
    }

    // Re-arm after a bfcache restore: on Back/Forward `DOMContentLoaded` does not fire
    // again while the previous document's listeners are gone.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            blockDashboardUiBound = false;
            bindBlockDashboardUi();
        }
    });

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = checked;
        document.getElementById('resetSelectedCount').textContent = checked;
        document.getElementById('bulkEscalateBtn').disabled = checked === 0;
        document.getElementById('bulkResetBtn').disabled = checked === 0;
    }

    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = selectAll.checked);
        updateSelectedCount();
    }

    let sortDirection = {};

    function sortTable(columnIndex) {
        const tbody = document.getElementById('studentTableBody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        if (!sortDirection[columnIndex]) {
            sortDirection[columnIndex] = 'asc';
        } else if (sortDirection[columnIndex] === 'asc') {
            sortDirection[columnIndex] = 'desc';
        } else {
            sortDirection[columnIndex] = 'asc';
        }
        
        const direction = sortDirection[columnIndex];
        
        rows.sort((a, b) => {
            // Column 4 is "Current Risk": sorting its text would order High/Moderate/Low
            // alphabetically, so it sorts on the numeric risk score instead.
            if (columnIndex === 4) {
                const aScore = parseFloat(a.dataset.score) || 0;
                const bScore = parseFloat(b.dataset.score) || 0;

                return direction === 'asc' ? aScore - bScore : bScore - aScore;
            }

            let aVal = a.children[columnIndex]?.textContent?.trim() || '';
            let bVal = b.children[columnIndex]?.textContent?.trim() || '';
            
            const aNum = parseFloat(aVal.replace(/[^0-9.]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^0-9.]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }

    function applyFilters() {
        const searchInput = document.getElementById('studentSearch');
        const riskFilter = document.getElementById('filterRisk').value;
        const trendFilter = document.getElementById('filterTrend').value;
        const gradeFilter = document.getElementById('filterGrade').value;
        const attendanceFilter = document.getElementById('filterAttendance').value;
        const statusFilter = document.getElementById('filterStatus').value;
        const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
        
        const rows = document.querySelectorAll('#studentTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const risk = row.dataset.risk || 'Low';
            const grade = parseFloat(row.dataset.grade) || 0;
            const attendance = parseFloat(row.dataset.attendance) || 0;
            const trend = row.dataset.trend || 'stable';
            const status = row.dataset.status || 'none';
            const searchable = row.dataset.search || '';
            
            let show = true;
            
            if (term !== '' && searchable.indexOf(term) === -1) show = false;
            if (riskFilter !== 'all' && risk !== riskFilter) show = false;
            if (trendFilter !== 'all' && trend !== trendFilter) show = false;
            if (statusFilter !== 'all' && status !== statusFilter) show = false;
            if (gradeFilter === 'below75' && grade >= 75) show = false;
            if (gradeFilter === 'below80' && grade >= 80) show = false;
            if (attendanceFilter === 'below80' && attendance >= 80) show = false;
            if (attendanceFilter === 'below70' && attendance >= 70) show = false;
            
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        const counter = document.getElementById('studentCount');
        if (counter) counter.textContent = visibleCount;
    }

    function resetFilters() {
        const searchInput = document.getElementById('studentSearch');

        if (searchInput) searchInput.value = '';

        document.getElementById('filterRisk').value = 'all';
        document.getElementById('filterTrend').value = 'all';
        document.getElementById('filterGrade').value = 'all';
        document.getElementById('filterAttendance').value = 'all';
        document.getElementById('filterStatus').value = 'all';
        applyFilters();
    }


    let escalationStudents = [];

    let escalationDraft = {};

    function getModal(idOrElement) {
        const el = typeof idOrElement === 'string' ? document.getElementById(idOrElement) : idOrElement;
        const Modal = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal : null;

        if (!Modal || !el) {
            console.error('[Block Dashboard] Bootstrap Modal unavailable for "' + idOrElement
                + '" — the dialog cannot open. Check that js/vendor/bootstrap.bundle.min.js is served.');
            alert('A page dependency (Bootstrap) did not load, so this dialog cannot open.\n'
                + 'Please hard-refresh with Ctrl+F5. If it persists, contact the administrator.');

            return { show: function () {}, hide: function () {} };
        }

        return Modal.getOrCreateInstance(el);
    }

    function getEscalationModal() {
        return getModal('escalationModal');
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function currentGradingPeriod() {
        return document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
    }

    function openEscalationModal(studentIds) {
        escalationStudents = (Array.isArray(studentIds) ? studentIds : [studentIds]).map(String);
        escalationDraft = {};

        document.getElementById('escalationCount').textContent = escalationStudents.length;
        document.getElementById('escalationWarning').style.display = 'none';
        document.getElementById('recommendationSummary').innerHTML =
            '<i class=""></i> Loading recommendation(s)...';

        document.getElementById('escalationStudentList').innerHTML =
            '<div class="text-center py-2 text-muted small">' +
            '<span class="spinner-border spinner-border-sm text-primary me-2"></span>' +
            'Loading student data...</div>';

        // Paint the modal first — the click must respond before any network round-trip.
        getEscalationModal().show();

        const headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        };

        Promise.all([
            fetch('/academic-head/escalation/recommendation-preview', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ student_ids: escalationStudents, period: currentGradingPeriod() }),
            }).then(response => response.json()).catch(() => ({ success: false })),
            fetch('/academic-head/check-escalation-status', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ student_ids: escalationStudents }),
            }).then(response => response.json()).catch(() => ({ success: false })),
        ]).then(([previewData, statusData]) => {
            renderEscalationStudents(
                previewData && previewData.success ? (previewData.students || {}) : {},
                statusData && statusData.success ? (statusData.statuses || {}) : {}
            );
        });
    }


    function renderEscalationStudents(previews, statuses) {
        const list = document.getElementById('escalationStudentList');

        let html = '';
        let escalatedCount = 0;

        escalationStudents.forEach(id => {
            const preview = previews[id] || previews[String(id)] || {};
            const status = statuses[id] || statuses[String(id)] || {};

            // Fallback to the table row that launched the modal.
            const row = document.querySelector(`.student-checkbox[value="${id}"]`);
            const tr = row ? row.closest('tr') : null;
            const fallbackName = tr?.querySelector('td[data-label="Student"] strong')?.textContent?.trim() || ('Student #' + id);
            const fallbackRisk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent?.trim() || 'N/A';

            const name = preview.student_name || fallbackName;
            const risk = (preview.risk_level && preview.risk_level !== 'N/A') ? preview.risk_level : fallbackRisk;
            const score = (preview.risk_score === null || preview.risk_score === undefined) ? null : preview.risk_score;
            const isEscalated = !!status.is_escalated;
            const hasRecommendation = !!preview.has_recommendation;
            const originalText = preview.preview_text || '';
            const factors = Array.isArray(preview.risk_factors) ? preview.risk_factors : [];

            if (isEscalated) {
                escalatedCount++;
            }

            // Default: forward it (only possible when one exists).
            escalationDraft[id] = {
                include: hasRecommendation,
                original_text: originalText,
                text: originalText,
                edited: false,
                recommendation_id: preview.recommendation_id || null,
                has_recommendation: hasRecommendation,
            };

            const riskClass = risk === 'High' ? 'danger' : (risk === 'Moderate' ? 'warning' : 'secondary');

            html += `
            <div class="card mb-2 escalation-student-card" data-student-id="${escapeHtml(id)}">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <i class="fas fa-user me-2"></i><strong>${escapeHtml(name)}</strong>
                            <span class="badge bg-${riskClass} ms-2">${escapeHtml(risk)} Risk</span>
                            ${isEscalated ? `<span class="badge bg-info ms-2"><i class="fas fa-folder-open me-1"></i>Already Escalated</span>` : ''}
                        </div>
                        <small class="text-muted text-end">
                            ${score !== null ? `Risk score: <strong>${escapeHtml(score)}</strong>` : ''}
                            ${preview.student_number ? `<br>${escapeHtml(preview.student_number)}` : ''}
                        </small>
                    </div>
                    ${factors.length ? `<div class="mt-1">${factors.map(factor => `<span class="badge bg-light text-dark border me-1">${escapeHtml(factor)}</span>`).join('')}</div>` : ''}
                    ${recommendationBlockHtml(id, preview, hasRecommendation, originalText)}
                </div>
            </div>`;
        });

        list.innerHTML = html;

        const warning = document.getElementById('escalationWarning');

        if (escalatedCount > 0) {
            warning.style.display = 'block';
            warning.innerHTML = `<i class="fas fa-info-circle me-2"></i>${escalatedCount} student(s) already have open cases and will be skipped.`;
        } else {
            warning.style.display = 'none';
        }

        updateRecommendationSummary();
    }


    function recommendationBlockHtml(id, preview, hasRecommendation, originalText) {
        const checkbox = (checked, disabled, labelClass) => `
            <div class="form-check mt-2">
                <input class="form-check-input include-recommendation" type="checkbox"
                       id="includeRec-${id}" data-student="${id}"
                       ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                <label class="form-check-label small ${labelClass}" for="includeRec-${id}">
                    Include Intervention Recommendation
                </label>
            </div>`;

        if (!hasRecommendation) {
            return `
            <div class="mt-2 border-top pt-2 recommendation-block" data-student="${id}">
                <span class="small fw-bold"><i class="text-warning me-1"></i> Intervention Recommendation</span>
                <div class="alert alert-warning py-1 px-2 mb-1 mt-1 small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No intervention recommendation generated yet &mdash; generate one first before escalating.
                </div>
                ${checkbox(false, true, 'text-muted')}
            </div>`;
        }

        const generatedAt = preview.generated_at ? `Generated ${escapeHtml(preview.generated_at)}` : 'Generated copy';

        return `
        <div class="mt-2 border-top pt-2 recommendation-block" data-student="${id}">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small fw-bold"><i class="text-warning me-1"></i> Intervention Recommendation</span>
                <span>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-recommendation" data-student="${id}">
                        <i class="fas fa-pen me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-reset-recommendation d-none" data-student="${id}">
                        <i class="fas fa-undo me-1"></i> Reset to generated
                    </button>
                </span>
            </div>
            <div class="form-text small recommendation-meta">${generatedAt} &mdash; this is what the counselor will read.</div>
            <div class="bg-light border rounded p-2 small recommendation-preview" data-preview-for="${id}" style="white-space: pre-wrap;">${escapeHtml(originalText)}</div>
            <textarea class="form-control form-control-sm mt-1 d-none recommendation-textarea"
                      data-edited-for="${id}" rows="4" maxlength="5000">${escapeHtml(originalText)}</textarea>
            <div class="badge bg-info text-dark mt-2 d-none recommendation-edited-badge">Edited version will be forwarded</div>
            ${checkbox(true, false, '')}
        </div>`;
    }


    function syncRecommendationDraft(studentId, patch) {
        const key = studentId === undefined || studentId === null ? null : String(studentId);

        if (!key || !escalationDraft[key]) {
            return;
        }

        Object.assign(escalationDraft[key], patch);

        const draft = escalationDraft[key];
        draft.edited = (draft.text || '').trim() !== '' && (draft.text || '').trim() !== (draft.original_text || '').trim();

        const block = document.querySelector(`.recommendation-block[data-student="${key}"]`);
        const badge = block?.querySelector('.recommendation-edited-badge');

        if (badge) {
            badge.classList.toggle('d-none', !draft.edited);
        }

        updateRecommendationSummary();
    }

    /** Toggle the recommendation block between read-only preview and edit mode. */
    function toggleRecommendationEditor(studentId) {
        const key = String(studentId);
        const draft = escalationDraft[key];

        if (!draft || !draft.has_recommendation) {
            return;
        }

        const block = document.querySelector(`.recommendation-block[data-student="${key}"]`);

        if (!block) {
            return;
        }

        const textarea = block.querySelector('.recommendation-textarea');
        const previewEl = block.querySelector('.recommendation-preview');
        const editBtn = block.querySelector('.btn-edit-recommendation');
        const resetBtn = block.querySelector('.btn-reset-recommendation');
        const meta = block.querySelector('.recommendation-meta');
        const editing = textarea.classList.contains('d-none');

        if (editing) {
            textarea.value = draft.text || draft.original_text;
            textarea.classList.remove('d-none');
            previewEl.classList.add('d-none');
            resetBtn.classList.remove('d-none');
            editBtn.innerHTML = '<i class="fas fa-eye me-1"></i> Done';
            meta.textContent = 'Editing — the edited version is what the counselor receives.';
            textarea.focus();
        } else {
            syncRecommendationDraft(key, { text: textarea.value });
            previewEl.textContent = textarea.value;
            textarea.classList.add('d-none');
            previewEl.classList.remove('d-none');
            editBtn.innerHTML = '<i class="fas fa-pen me-1"></i> Edit';
            meta.textContent = draft.edited
                ? 'Edited version — this is what the counselor will read.'
                : 'Generated copy — this is what the counselor will read.';
        }
    }

    function resetRecommendationToGenerated(studentId) {
        const key = String(studentId);
        const draft = escalationDraft[key];

        if (!draft || !draft.has_recommendation) {
            return;
        }

        const block = document.querySelector(`.recommendation-block[data-student="${key}"]`);
        const textarea = block?.querySelector('.recommendation-textarea');
        const previewEl = block?.querySelector('.recommendation-preview');

        if (textarea) {
            textarea.value = draft.original_text;
            textarea.classList.add('d-none');
        }

        if (previewEl) {
            previewEl.textContent = draft.original_text;
            previewEl.classList.remove('d-none');
        }

        block?.querySelector('.btn-reset-recommendation')?.classList.add('d-none');
        block?.querySelector('.recommendation-edited-badge')?.classList.add('d-none');

        const editBtn = block?.querySelector('.btn-edit-recommendation');

        if (editBtn) {
            editBtn.innerHTML = '<i class="fas fa-pen me-1"></i> Edit';
        }

        const meta = block?.querySelector('.recommendation-meta');

        if (meta) {
            meta.textContent = 'Generated copy — this is what the counselor will read.';
        }

        draft.text = draft.original_text;
        draft.edited = false;

        updateRecommendationSummary();
    }

    function updateRecommendationSummary() {
        const el = document.getElementById('recommendationSummary');

        if (!el) {
            return;
        }

        const drafts = Object.keys(escalationDraft).map(key => escalationDraft[key]);
        const included = drafts.filter(draft => draft.include && (draft.text || '').trim() !== '');
        const edited = included.filter(draft => draft.edited);
        const missing = drafts.filter(draft => !draft.has_recommendation);

        let text = `<i class="fas"></i> Recommendation forwarded for `
            + `${included.length} of ${escalationStudents.length} student(s)`;

        if (edited.length > 0) {
            text += ` · ${edited.length} edited`;
        }

        if (missing.length > 0) {
            text += ` · ${missing.length} without a recommendation`;
        }

        el.innerHTML = text;
    }




    function confirmEscalation() {
        const confirmBtn = document.getElementById('confirmEscalationBtn');
        const notes = document.getElementById('escalationNotes').value;
        const blockId = {{ $blockId ?? 0 }};
        const period = currentGradingPeriod();

        if (escalationStudents.length === 0) {
            alert('No students selected for escalation.');
            return;
        }

        const recommendations = {};

        escalationStudents.forEach(id => {
            const draft = escalationDraft[String(id)];
            const text = draft ? (draft.text || '').trim() : '';
            const include = !!(draft && draft.include && text !== '');

            recommendations[String(id)] = {
                include: include,
                recommendation_id: draft ? (draft.recommendation_id || null) : null,
                // Only send an override when it is a real edit; otherwise the server
                // forwards the generated text itself.
                edited_text: (include && draft.edited) ? text : null,
            };
        });

        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        fetch('/academic-head/bulk-escalate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: escalationStudents,
                notes: notes,
                block_id: blockId,
                period: period,
                recommendations: recommendations,
            }),
        })
        .then(async response => {
            let data = null;

            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }

            if (!response.ok) {
                throw new Error((data && data.message) ? data.message : `Request failed (HTTP ${response.status})`);
            }

            if (!data) {
                throw new Error('The server returned an unexpected response.');
            }

            return data;
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Unknown error');
            }

            let message = `${data.escalated} student(s) escalated to the Guidance Counselor.`;

            if (data.recommendations_forwarded > 0) {
                message += ` ${data.recommendations_forwarded} intervention recommendation(s) forwarded.`;
            }

            if (data.already_escalated > 0) {
                message += ` ${data.already_escalated} student(s) already had open cases and were skipped.`;
            }

            resetEscalationModalState();
            getEscalationModal().hide();

            // A full refresh is required to reflect the new cases/priority counts; the
            // delegated binding + pageshow re-arm means the button still works after it.
            if (typeof showToast === 'function') {
                showToast(message, 'success');
            } else {
                alert(message);
            }

            setTimeout(() => window.location.reload(), 700);
        })
        .catch(error => {
            alert('Failed to escalate: ' + error.message);
            resetEscalationModalState();
        });
    }

    function resetEscalationModalState() {
        const notes = document.getElementById('escalationNotes');

        if (notes) {
            notes.value = '';
        }

        escalationStudents = [];
        escalationDraft = {};

        const confirmBtn = document.getElementById('confirmEscalationBtn');

        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Escalate to Counselor';
        }

        const summary = document.getElementById('recommendationSummary');

        if (summary) {
            summary.innerHTML = '<i class=""></i> No recommendation selected yet.';
        }
    }

    function bulkEscalate() {
        const selected = document.querySelectorAll('.student-checkbox:checked');
        const ids = Array.from(selected).map(cb => cb.value);

        if (ids.length > 0) {
            openEscalationModal(ids);
        }
    }

    function bulkEscalateAll() {
        const flagged = document.querySelectorAll('#studentTableBody tr:not([style*="display: none"]) .student-checkbox');
        const ids = Array.from(flagged).map(cb => cb.value);

        if (ids.length > 0) {
            openEscalationModal(ids);
        } else {
            alert('No students to escalate.');
        }
    }



    let resetStudents = [];

    function openResetModal(studentIds) {
        resetStudents = studentIds;
        const modal = getModal('resetEscalationModal');
        document.getElementById('resetCount').textContent = studentIds.length;
        
        const list = document.getElementById('resetStudentList');
        list.innerHTML = '';
        
        studentIds.forEach(id => {
            const row = document.querySelector(`.student-checkbox[value="${id}"]`);
            if (row) {
                const tr = row.closest('tr');
                const name = tr?.querySelector('td[data-label="Student"] strong')?.textContent || 'Student';
                const risk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent || 'Unknown';
                list.innerHTML += `
                    <div class="alert alert-warning py-1 mb-1">
                        <i class="fas fa-user me-2"></i> ${name} - ${risk} Risk
                        <span class="badge bg-info ms-2">Will be reset</span>
                    </div>
                `;
            }
        });
        
        modal.show();
    }

    function confirmResetEscalation() {
        const reason = document.getElementById('resetReason').value || 'Reset due to accidental escalation';
        
        if (resetStudents.length === 0) {
            alert('No students selected for reset.');
            return;
        }
        
        const confirmBtn = document.getElementById('confirmResetBtn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        
        fetch('/academic-head/bulk-reset-escalation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: resetStudents,
                reason: reason,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${data.success_count} student(s) escalation reset successfully!\n\n${data.failed_count} failed to reset.`);
            } else {
                alert('Failed to reset: ' + (data.message || 'Unknown error'));
            }
            
            document.getElementById('resetReason').value = '';
            resetStudents = [];
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Reset';
            getModal('resetEscalationModal').hide();
            window.location.reload();
        })
        .catch(error => {
            alert('❌ Error: ' + error.message);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Reset';
        });
    }

    function bulkResetEscalation() {
        const checked = document.querySelectorAll('.student-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (ids.length > 0) {
            fetch('/academic-head/check-escalation-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    student_ids: ids,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const resetableIds = ids.filter(id => data.statuses[id]?.is_escalated === true);
                    if (resetableIds.length === 0) {
                        alert('No selected students have open cases to reset.');
                        return;
                    }
                    openResetModal(resetableIds);
                }
            })
            .catch(() => {
                // Fallback: try resetting all selected
                openResetModal(ids);
            });
        }
    }

    function bulkResetAllEscalations() {
        if (!confirm('This will reset ALL escalations in this block. Are you sure?')) {
            return;
        }
        
        const blockId = {{ $blockId ?? 0 }};
        
        fetch('/academic-head/block/' + blockId + '/escalated-students', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students && data.students.length > 0) {
                if (confirm(`This will reset ${data.students.length} escalation(s). Continue?`)) {
                    openResetModal(data.students);
                }
            } else {
                alert('No escalations found in this block.');
            }
        })
        .catch(() => {
            alert('Failed to fetch escalated students.');
        });
    }


    function updateEscalationIndicators() {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        if (checkboxes.length === 0) return;
        
        const studentIds = Array.from(checkboxes).map(cb => cb.value);
        
        fetch('/academic-head/check-escalation-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: studentIds,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                checkboxes.forEach(cb => {
                    const studentId = cb.value;
                    const status = data.statuses[studentId];
                    const tr = cb.closest('tr');
                    
                    if (!tr || !status) return;
                    
                    const statusCell = tr.querySelector('td[data-label="Status"]');
                    if (!statusCell) return;
                    
                    if (status.is_escalated) {
                        if (!statusCell.querySelector('.already-escalated-badge')) {
                            // Only update if server-side didn't already show it
                            const currentBadge = statusCell.querySelector('.badge');
                            if (currentBadge && currentBadge.textContent.includes('Open Case')) {
                                return;
                            }
                            statusCell.innerHTML = `
                                <span class="badge bg-info already-escalated-badge">
                                    <i class="fas fa-folder-open me-1"></i> Open Case
                                </span>
                                <br>
                                <small class="text-muted">Case #${status.case_id}: ${status.status}</small>
                            `;
                        }
                    }
                });
            }
        })
        .catch(() => {
        });
    }

    let scoringInterval = null;

    // A PHP max_execution_time fatal (or a proxy/gateway timeout) never arrives
    // as JSON: it is a 5xx with an HTML body, a JSON parse failure, or a dropped
    // connection. Translate any of those into something the user can act on,
    // and leave genuine, descriptive AI errors (e.g. the 503 "AI risk scoring
    // unavailable: ...") untouched.
    function friendlyScoringError(error) {
        const raw = (error && error.message) ? String(error.message) : '';

        if (/maximum execution time|timed out|timeout|failed to fetch|network ?error|load failed|unexpected token|not valid json|json parse|bad gateway|gateway time|HTTP 5(0[024])\b|\b50[024]\b/i.test(raw)) {
            return 'Scoring is taking longer than expected. Please wait a moment or try a smaller batch.';
        }

        return raw || 'Scoring failed. Please try again.';
    }

    // The Retry button is created on demand. Keep at most one alive: drop any
    // previous instance (and any pending creation timer) before arming a new one,
    // so a repeat failure can never stack a second Retry button in the modal.
    let scoringRetryTimer = null;

    let scoringRetryButton = null;

    function resetScoringRetry() {
        if (scoringRetryTimer) {
            clearTimeout(scoringRetryTimer);
            scoringRetryTimer = null;
        }

        if (scoringRetryButton) {
            scoringRetryButton.remove();
            scoringRetryButton = null;
        }
    }

    function runRiskScoring() {
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        const modal = getModal('scoringModal');
        modal.show();

        // A fresh run never inherits the previous failure's stale Retry button.
        resetScoringRetry();
        
        let progress = 5;
        const progressBar = document.getElementById('scoringProgress');
        const progressText = document.getElementById('scoringStatus');
        const studentName = document.getElementById('currentStudentName');
        
        progressBar.style.width = '5%';
        progressBar.textContent = '5%';
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        progressText.textContent = 'Starting risk scoring...';
        studentName.textContent = 'Initializing connection...';
        
        // Start simulated progress (will be replaced by real progress)
        // This runs only until the AJAX response arrives
        let simProgress = 5;
        if (scoringInterval) clearInterval(scoringInterval);
        
        scoringInterval = setInterval(() => {
            simProgress += 2;
            if (simProgress > 85) {
                // Don't exceed 85% until real response
                return;
            }
            progressBar.style.width = simProgress + '%';
            progressBar.textContent = simProgress + '%';
            
            const messages = [
                'Analyzing student data...',
                'Connecting to AI service...',
                'Processing student records...',
                'Calculating risk factors...',
                'Generating risk scores...'
            ];
            const idx = Math.floor(simProgress / 20);
            studentName.textContent = messages[Math.min(idx, messages.length - 1)];
        }, 800);
        
        fetch(`/academic-head/block/${blockId}/run-scoring`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                period: period,
                school_year: '2024-2025',
            }),
        })
        .then(response => {
            if (scoringInterval) {
                clearInterval(scoringInterval);
                scoringInterval = null;
            }
            
            if (!response.ok) {
                // A timeout/500 body is not JSON; fall back to the status so the
                // catch below can still classify it instead of throwing a
                // JSON-parse error.
                return response.json()
                    .catch(() => ({ message: `HTTP ${response.status}` }))
                    .then(data => {
                        throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
                    });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                if (data.existing) {
                    progressText.textContent = '' + data.message;
                    progressBar.className = 'progress-bar bg-warning';
                    progressBar.style.width = '100%';
                    progressBar.textContent = 'Already Scored';
                    setTimeout(() => modal.hide(), 2000);
                    return;
                }
                throw new Error(data.message || 'Scoring failed');
            }
            
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.className = 'progress-bar bg-success';
            progressText.textContent = 'Risk scoring complete!';
            studentName.textContent = `Processed ${data.processed} students via AI engine`;
            
            setTimeout(() => {
                modal.hide();
                window.location.reload();
            }, 1500);
        })
        .catch(error => {
            if (scoringInterval) {
                clearInterval(scoringInterval);
                scoringInterval = null;
            }
            
            progressText.textContent = '❌ ' + friendlyScoringError(error);
            progressBar.className = 'progress-bar bg-danger';
            progressBar.style.width = '100%';
            progressBar.textContent = 'Failed';
            studentName.textContent = 'Please try again or contact support.';
            
            scoringRetryTimer = setTimeout(() => {
                scoringRetryTimer = null;

                // Belt-and-braces: never append a second Retry button.
                resetScoringRetry();

                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn btn-primary mt-3';
                retryBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Retry';
                retryBtn.onclick = function() {
                    modal.hide();
                    setTimeout(() => runRiskScoring(), 500);
                };
                document.querySelector('#scoringModal .modal-body .text-center').appendChild(retryBtn);
                scoringRetryButton = retryBtn;
            }, 3000);
        });
    }

    function refreshRiskScoring() {
        if (!confirm('This will DELETE existing risk scores for this period and re-run the AI scoring. Continue?')) {
            return;
        }
        
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        document.getElementById('refreshScoringBtn').disabled = true;
        document.getElementById('runRiskScoringBtn').disabled = true;
        
        const refreshBtn = document.getElementById('refreshScoringBtn');
        const originalHtml = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        
        fetch(`/academic-head/block/${blockId}/refresh-scoring`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                period: period,
                school_year: '2024-2025',
            }),
        })
        .then(response => response.json())
        .then(data => {
            refreshBtn.innerHTML = originalHtml;
            refreshBtn.disabled = false;
            document.getElementById('runRiskScoringBtn').disabled = false;
            
            if (data.success) {
                document.getElementById('refreshScoringBtn').style.display = 'none';
                document.getElementById('runRiskScoringBtn').style.display = 'inline-block';
                document.getElementById('runRiskScoringBtn').disabled = false;
                
                showToast('' + data.message, 'success');
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast('❌ Failed to refresh: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            refreshBtn.innerHTML = originalHtml;
            refreshBtn.disabled = false;
            document.getElementById('runRiskScoringBtn').disabled = false;
            showToast('❌ Error: ' + error.message, 'error');
        });
    }

    function checkScoringStatus() {
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        fetch(`/academic-head/block/${blockId}/scoring-status?period=${period}`)
            .then(response => response.json())
            .then(data => {
                const runBtn = document.getElementById('runRiskScoringBtn');
                const refreshBtn = document.getElementById('refreshScoringBtn');
                
                if (data.processed > 0 && data.total > 0) {
                    refreshBtn.style.display = 'inline-block';
                    runBtn.style.display = 'none';
                    runBtn.disabled = true;
                    
                    const infoSpan = document.getElementById('scoringInfo');
                    if (!infoSpan) {
                        const newInfo = document.createElement('span');
                        newInfo.className = 'badge bg-info ms-2';
                        newInfo.id = 'scoringInfo';
                        newInfo.textContent = `${data.processed}/${data.total} scored`;
                        refreshBtn.parentNode.insertBefore(newInfo, refreshBtn.nextSibling);
                    } else {
                        infoSpan.textContent = `${data.processed}/${data.total} scored`;
                    }
                } else {
                    refreshBtn.style.display = 'none';
                    runBtn.style.display = 'inline-block';
                    runBtn.disabled = false;
                    
                    const oldInfo = document.getElementById('scoringInfo');
                    if (oldInfo) oldInfo.remove();
                }
            })
            .catch(() => {
                document.getElementById('runRiskScoringBtn').style.display = 'inline-block';
                document.getElementById('refreshScoringBtn').style.display = 'none';
            });
    }

    function showToast(message, type = 'success') {
        const colors = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center ${colors[type] || colors.info} border-0 show`;
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 5000);
    }
</script>
@endpush

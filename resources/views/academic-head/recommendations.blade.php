@extends('layouts.app')

@section('title', 'Recommendation Management - AcadAlert')

@section('page_title', 'Intervention Recommendation Management')
@section('page_actions')
    <div>
        <a href="{{ route('academic-head.department') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Department
        </a>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<input type="hidden" id="activePeriod" value="{{ $period }}">
<input type="hidden" id="activeSchoolYear" value="{{ $schoolYear }}">

<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">
<div id="progressContainer" style="display: none;" class="mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-spinner fa-spin me-2"></i>
            Generating Recommendations
            <span id="progressStatus" class="badge bg-light text-primary ms-2">Processing...</span>
        </div>
        <div class="card-body">
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     id="mainProgressBar" style="width: 0%;">
                    0%
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Processed</div>
                        <div class="fw-bold" id="processedCount">0 / <span id="totalCount">0</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Successful</div>
                        <div class="fw-bold text-success" id="successCount">0</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Failed</div>
                        <div class="fw-bold text-danger" id="failedCount">0</div>
                    </div>
                </div>
            </div>
            <div id="progressDetails" class="mt-2" style="max-height: 200px; overflow-y: auto;">
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card recs-frame" id="recsSelectorCard">
            <div class="card-header recs-band bg-primary text-white">
                <img src="{{ asset('images/logo/acadalert_notxt.png') }}"
                     alt="" aria-hidden="true" class="recs-band-mark">
                <span class="recs-band-title">
                    <span class="recs-band-eyebrow">AI Intervention Workspace</span>
                    Select Block to Manage Recommendations
                </span>
                <span class="recs-band-note">
                    <i class="fas fa-calendar-days"></i>{{ $period }} &middot; {{ $schoolYear }}
                </span>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('academic-head.recommendations') }}" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="block_id" required>
                            <option value="">-- Select a Block --</option>
                            @foreach($blocks as $block)
                                <option value="{{ $block->id }}" {{ $blockId == $block->id ? 'selected' : '' }}>
                                    {{ $block->program_code }} - Year {{ $block->year_number }} - {{ $block->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="period" title="Grading Period">
                            @foreach($periods as $p)
                                <option value="{{ $p }}" {{ $period === $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-eye me-1"></i> View Block
                        </button>
                    </div>
                    @if($selectedBlock)
                        <div class="col-md-3">
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#generateAllModal">
                                <i class="fas fa-wand-magic-sparkles me-1"></i> Generate AI Recommendations
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

@if(!$selectedBlock)
<section class="recs-hero" id="recsHero" aria-labelledby="recsHeroTitle">
    <div class="recs-hero-body">
        <p class="recs-hero-eyebrow">
            <span class="recs-hero-pulse" aria-hidden="true"></span>
            AI Intervention Workspace
        </p>

        <h2 class="recs-hero-title" id="recsHeroTitle">Pick a block to open its intervention workspace</h2>

        <p class="recs-hero-lead">
            A block is one program, one year level, one grading period. Pick it above and the engine
            reads every risk score recorded for that slice, drafts an intervention plan per student,
            and queues each one here for your review.
        </p>

        <ol class="recs-steps">
            <li class="recs-step">
                <span class="recs-step-index" aria-hidden="true">01</span>
                <span class="recs-step-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                <h3 class="recs-step-title">Select a block</h3>
                <p class="recs-step-copy">Only the blocks assigned to your department are listed.</p>
            </li>
            <li class="recs-step">
                <span class="recs-step-index" aria-hidden="true">02</span>
                <span class="recs-step-icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></span>
                <h3 class="recs-step-title">Scope the period</h3>
                <p class="recs-step-copy">One grading period at a time, so plans never mix terms.</p>
            </li>
            <li class="recs-step">
                <span class="recs-step-index" aria-hidden="true">03</span>
                <span class="recs-step-icon" aria-hidden="true"><i class="fas fa-bolt"></i></span>
                <h3 class="recs-step-title">Generate with AI</h3>
                <p class="recs-step-copy">Every recommendation stays editable before it is used.</p>
            </li>
        </ol>

        <ul class="recs-hero-facts">
            <li>
                <span class="recs-fact-value">{{ count($blocks) }}</span>
                <span class="recs-fact-label">Blocks in your department</span>
            </li>
            <li>
                <span class="recs-fact-value">{{ count($periods) }}</span>
                <span class="recs-fact-label">Grading periods</span>
            </li>
            <li>
                <span class="recs-fact-value">{{ $period }}</span>
                <span class="recs-fact-label">Period in view</span>
            </li>
            <li>
                <span class="recs-fact-value">AY {{ $schoolYear }}</span>
                <span class="recs-fact-label">Academic year</span>
            </li>
        </ul>
    </div>
</section>
@endif

@if($selectedBlock)
<div class="row">
    <div class="col-12 mb-4">
        <div class="card recs-frame recs-frame-success" id="recsBlockInfo">
            <div class="card-header recs-band recs-band-success bg-success text-white">
                <i class="fas fa-info-circle me-2"></i>
                <span class="recs-band-title">
                    <span class="recs-band-eyebrow">Selected block</span>
                    {{ $selectedBlock->program_code }} - Year {{ $selectedBlock->year_number }} - {{ $selectedBlock->name }}
                </span>
                <span class="badge bg-light text-success ms-2 ms-auto">{{ count($students) }} students</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Program</div>
                            <div class="fw-bold">{{ $selectedBlock->program_code }} - {{ $selectedBlock->program_name }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Year Level</div>
                            <div class="fw-bold">Year {{ $selectedBlock->year_number }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Total Students</div>
                            <div class="fw-bold">{{ count($students) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Recommendations &middot; {{ $period }}</div>
                            <div class="fw-bold">
                                @php
                                    $recCount = 0;
                                    foreach ($students as $student) {
                                        if (isset($recommendations[$student->id]) && $recommendations[$student->id]->count() > 0) {
                                            $recCount++;
                                        }
                                    }
                                @endphp
                                {{ $recCount }} / {{ count($students) }} students have recommendations
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>
                        <i class="fas fa-list text-primary me-2"></i> Students &amp; Recommendations
                        <span class="badge bg-primary ms-2">{{ $period }} &middot; {{ $schoolYear }}</span>
                        <span class="badge bg-secondary ms-2" id="recommendationVisibleCount">{{ count($students) }}</span>
                        @if(($pendingCount ?? 0) > 0)
                            <span class="badge bg-info text-dark ms-2" title="Recommendations not yet acted upon">
                                {{ number_format((int) $pendingCount) }} pending
                            </span>
                        @endif
                        @if(($escalatedCount ?? 0) > 0)
                            <span class="badge bg-warning text-dark ms-2" title="Students with a live case">
                                {{ number_format((int) $escalatedCount) }} escalated
                            </span>
                        @endif
                    </span>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input type="search" class="form-control form-control-sm" id="recommendationSearch"
                               style="min-width: 200px;" placeholder="Search name or student number">
                        <select class="form-select form-select-sm" id="recommendationRiskFilter" aria-label="Risk level">
                            <option value="all">All risk levels</option>
                            <option value="High">High only</option>
                            <option value="Moderate">Moderate only</option>
                            <option value="Low">Low only</option>
                            <option value="none">No recommendation yet</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetRecommendationFilters">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-mobile-cards" id="recommendationTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Program / Block</th>
                                <th>Risk Level</th>
                                <th>Recommendation</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recommendationTableBody">
                            @forelse($students as $index => $student)
                            @php
                                $studentRecs = $recommendations[$student->id] ?? collect();
                                $hasRec = $studentRecs->count() > 0;
                                $latestRec = $hasRec ? $studentRecs->first() : null;
                                $riskLevel = $riskLevels[$student->id] ?? 'N/A';
                                $riskClass = match($riskLevel) {
                                    'High' => 'badge-risk-high',
                                    'Moderate' => 'badge-risk-moderate',
                                    'Low' => 'badge-risk-low',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <tr data-risk="{{ $riskLevel }}" data-has-rec="{{ $hasRec ? '1' : '0' }}"
                                data-search="{{ strtolower($student->first_name . ' ' . $student->last_name . ' ' . $student->student_number) }}">
                                <td>{{ $index + 1 }}</td>
                                <td data-label="Student">
                                    <strong>{{ $student->last_name }}, {{ $student->first_name }}</strong>
                                </td>
                                <td data-label="Student Number">{{ $student->student_number }}</td>
                                <td data-label="Program / Block">
                                    <div class="small fw-semibold">{{ $selectedBlock->program_code ?? '' }}</div>
                                    <small class="text-muted">
                                        Year {{ $selectedBlock->year_number ?? '?' }} &middot; {{ $selectedBlock->name ?? '' }}
                                    </small>
                                </td>
                                <td data-label="Risk Level">
                                    <span class="badge {{ $riskClass }}">
                                        {{ $riskLevel }}
                                    </span>
                                </td>
                                <td data-label="Recommendation">
                                    @if($hasRec)
                                        @php
                                            $actions = json_decode($latestRec->suggested_actions, true);
                                            
                                            // Safety check: ensure $actions is an array
                                            if (!is_array($actions)) {
                                                $actions = is_string($latestRec->suggested_actions) 
                                                    ? json_decode($latestRec->suggested_actions, true) 
                                                    : [];
                                                
                                                if (!is_array($actions) || empty($actions)) {
                                                    $actions = [
                                                        [
                                                            'action' => 'custom',
                                                            'details' => is_string($latestRec->suggested_actions) 
                                                                ? $latestRec->suggested_actions 
                                                                : 'No details available',
                                                            'priority' => 'medium'
                                                        ]
                                                    ];
                                                }
                                            }
                                            
                                            $actions = array_map(function($action) {
                                                return [
                                                    'action' => $action['action'] ?? 'custom',
                                                    'details' => $action['details'] ?? 'No details provided',
                                                    'priority' => $action['priority'] ?? 'medium',
                                                ];
                                            }, $actions);
                                        @endphp
                                        <div class="recommendation-preview">
                                            @if(count($actions) > 0)
                                                <div class="small">
                                                    @foreach($actions as $action)
                                                        <div class="mb-1">
                                                            @php
                                                                $priorityClass = match($action['priority'] ?? 'medium') {
                                                                    'high' => 'danger',
                                                                    'medium' => 'warning',
                                                                    'low' => 'info',
                                                                    default => 'secondary'
                                                                };
                                                            @endphp
                                                            <span class="badge bg-{{ $priorityClass }} me-1">
                                                                {{ ucfirst(str_replace('_', ' ', $action['action'] ?? 'custom')) }}
                                                            </span>
                                                            <span class="text-muted">{{ $action['details'] ?? 'No details' }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">No actions defined</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge bg-secondary">Pending</span>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    @php $caseState = $escalationStates[$student->id] ?? null; @endphp
                                    @if($caseState)
                                        <span class="badge bg-info" title="Case #{{ $caseState->case_id }} with the Guidance Counselor">
                                            <i class="fas fa-folder-open me-1"></i> {{ $caseState->status }}
                                        </span>
                                        @if($caseState->priority !== '')
                                            <div class="small text-muted">{{ $caseState->priority }} priority</div>
                                        @endif
                                    @elseif($hasRec)
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-hourglass-half me-1"></i> Not acted upon
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">No recommendation</span>
                                    @endif
                                </td>
                                <td data-label="Generated">
                                    @if($hasRec)
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($latestRec->generated_at)->format('M d, Y') }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="d-flex gap-1">
                                        @if($hasRec)
                                            <button type="button" class="btn btn-sm btn-outline-info btn-view-rec"
                                                    data-rec="{{ $latestRec->id }}" title="View Full AI Content">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="{{ route('academic-head.recommendation.edit', $latestRec->id) }}?block_id={{ $blockId }}"
                                               class="btn btn-sm btn-outline-primary" title="Edit Recommendation">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-rec"
                                                    data-rec="{{ $latestRec->id }}" title="Delete Recommendation">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-success btn-generate-rec"
                                                    data-student="{{ $student->id }}">
                                                <i class="fas fa-wand-magic-sparkles me-1"></i> Generate
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No students found in this block.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>

<div class="modal fade" id="generateAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class=""></i> Generate AI Recommendations
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('academic-head.recommendations.generate.block') }}"
                  onsubmit="event.preventDefault(); generateAll(true);">
                @csrf
                <input type="hidden" name="block_id" value="{{ $blockId }}">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will generate AI-powered intervention recommendations for <strong>ALL students</strong> in this block.
                        <br><br>
                        Only the selected period's recommendations are replaced — the other
                        periods are left untouched.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Grading Period</label>
                        <select class="form-select" name="period">
                            @foreach($periods as $p)
                                <option value="{{ $p }}" {{ $period === $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Generate All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewRecommendationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i> AI-Generated Recommendation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewRecommendationContent">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editRecommendationBtn" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i> Edit Recommendation
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this recommendation?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let deleteRecId = null;
    // The recommendation currently shown in the View modal, so the Edit button can
    // be pointed at it.
    let viewingRecId = null;

    function viewRecommendation(recId) {
        viewingRecId = recId;

        const modalEl = document.getElementById('viewRecommendationModal');
        const content = document.getElementById('viewRecommendationContent');

        if (!modalEl || !content) {
            window.alert('The recommendation viewer is unavailable on this page.');
            return;
        }

        content.innerHTML = '<div class="text-center py-3">'
            + '<div class="spinner-border text-primary" role="status">'
            + '<span class="visually-hidden">Loading...</span></div></div>';

        showRecommendationModal(modalEl);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            : '';

        window.fetch('/academic-head/recommendations/' + recId + '/view', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    content.innerHTML = '<div class="alert alert-danger mb-0">'
                        + (data.message || 'Could not load this recommendation.') + '</div>';
                    return;
                }

                content.innerHTML = renderRecommendationPayload(data);
            })
            .catch(function (error) {
                content.innerHTML = '<div class="alert alert-danger mb-0">'
                    + 'Failed to load the recommendation: ' + error.message + '</div>';
            });
    }

    function showRecommendationModal(modalEl) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            window.alert('The dialog library (Bootstrap) did not load, so the recommendation '
                + 'window cannot open. Reload the page; if it persists, report the missing '
                + 'public/js/vendor/bootstrap.bundle.min.js file.');
            return;
        }

        const existing = bootstrap.Modal.getInstance(modalEl);

        if (existing) {
            existing.show();
            return;
        }

        new bootstrap.Modal(modalEl).show();
    }

    function renderRecommendationPayload(data) {
        const student = data.student || {};
        const factors = data.risk_factors || [];
        const actions = data.suggested_actions || [];

        let html = '<div class="mb-3">'
            + '<div class="fw-bold">' + escapeHtml(student.last_name || '') + ', '
            + escapeHtml(student.first_name || '') + '</div>'
            + '<small class="text-muted">' + escapeHtml(student.student_number || '') + '</small>'
            + '</div>';

        html += '<h6 class="fw-bold">Risk Factors</h6>';

        if (factors.length === 0) {
            html += '<p class="text-muted small">No risk factors recorded.</p>';
        } else {
            html += '<ul class="small">';
            factors.forEach(function (factor) {
                html += '<li>' + escapeHtml(String(factor)) + '</li>';
            });
            html += '</ul>';
        }

        html += '<h6 class="fw-bold mt-3">Recommended Actions</h6>';

        if (actions.length === 0) {
            html += '<p class="text-muted small">No actions were generated.</p>';
        } else {
            html += '<div class="list-group">';
            actions.forEach(function (action) {
                const priority = String(action.priority || 'medium').toLowerCase();
                const tone = priority === 'high' ? 'danger' : (priority === 'medium' ? 'warning' : 'info');

                html += '<div class="list-group-item">'
                    + '<span class="badge bg-' + tone + ' me-2">' + escapeHtml(priority) + '</span>'
                    + '<strong>' + escapeHtml(String(action.action || 'custom').replace(/_/g, ' ')) + '</strong>'
                    + '<div class="small text-muted">' + escapeHtml(String(action.details || '')) + '</div>'
                    + '</div>';
            });
            html += '</div>';
        }

        html += '<div class="alert alert-light border small mt-3 mb-0">'
            + '<i class="fas fa-clock me-1"></i> Generated ' + escapeHtml(String(data.generated_at || 'unknown'))
            + '</div>';

        return html;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function safeGetElement(id) {
        const el = document.getElementById(id);
        if (!el) {
            console.warn(`Element with ID "${id}" not found.`);
        }
        return el;
    }

    function safeSetText(id, text) {
        const el = safeGetElement(id);
        if (el) {
            el.textContent = text;
            return true;
        }
        return false;
    }

    function safeSetStyle(id, property, value) {
        const el = safeGetElement(id);
        if (el) {
            el.style[property] = value;
            return true;
        }
        return false;
    }

    function showProgressContainer() {
        const container = safeGetElement('progressContainer');
        if (container) {
            container.style.display = 'block';
        } else {
            // Fallback: create a simple progress indicator
            console.warn('Progress container not found, creating fallback...');
            createFallbackProgress();
        }
    }

    function createFallbackProgress() {
        const container = document.createElement('div');
        container.id = 'progressContainer';
        container.className = 'mb-4';
        container.innerHTML = `
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Generating Recommendations
                    <span id="progressStatus" class="badge bg-light text-primary ms-2">Processing...</span>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="mainProgressBar" style="width: 0%;">0%</div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Processed</div>
                                <div class="fw-bold" id="processedCount">0 / <span id="totalCount">0</span></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Successful</div>
                                <div class="fw-bold text-success" id="successCount">0</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Failed</div>
                                <div class="fw-bold text-danger" id="failedCount">0</div>
                            </div>
                        </div>
                    </div>
                    <div id="progressDetails" class="mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>
        `;
        
        const blockSelection = document.querySelector('.row:first-child');
        if (blockSelection && blockSelection.parentNode) {
            blockSelection.parentNode.insertBefore(container, blockSelection.nextSibling);
        } else {
            // Fallback: insert at the beginning of the content area
            const content = document.querySelector('.container-fluid');
            if (content) {
                content.insertBefore(container, content.firstChild);
            }
        }
        console.log('Fallback progress container created.');
    }

    function updateProgress(percentage, total, success, failed) {
        const progressBar = safeGetElement('mainProgressBar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage + '%';
            
            if (percentage >= 100) {
                progressBar.className = 'progress-bar bg-success';
            } else if (percentage > 0) {
                progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
            }
        }
        
        safeSetText('totalCount', total);
        safeSetText('processedCount', (success + failed) + ' / ' + total);
        safeSetText('successCount', success);
        safeSetText('failedCount', failed);
    }

    function showProgressDetails(progressData) {
        const container = safeGetElement('progressDetails');
        if (!container) return;
        
        let html = '<div class="list-group list-group-flush">';
        
        if (progressData && progressData.length > 0) {
            progressData.forEach(item => {
                const statusIcon = item.status === 'success' ? '' : (item.status === 'failed' ? '' : '');
                const statusClass = item.status === 'success' ? 'text-success' : (item.status === 'failed' ? 'text-danger' : 'text-muted');
                const studentName = item.student_name || 'Student ' + item.student_id;
                
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span class="${statusClass}">
                            ${statusIcon} ${studentName}
                        </span>
                        <span class="small">
                            ${item.status === 'success' ? 'Done' : (item.status === 'failed' ? 'Failed: ' + (item.error || 'Unknown') : 'Processing...')}
                        </span>
                    </div>
                `;
            });
        } else {
            html += `
                <div class="list-group-item text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i> Waiting for results...
                </div>
            `;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    function currentPeriod() {
        return document.getElementById('activePeriod')?.value || '{{ $period }}';
    }

    function generateAll(useModalPeriod = false) {
        const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
        // The modal's choice wins when generation is launched from the modal;
        // otherwise the period currently being viewed drives the call.
        const period = useModalPeriod
            ? (document.querySelector('#generateAllModal select[name="period"]')?.value || currentPeriod())
            : currentPeriod();
        
        if (!blockId || blockId === '0') {
            alert('Please select a block first.');
            return;
        }
        
        if (!useModalPeriod && !confirm(`Generate AI recommendations for ALL students in this block? Only the ${period} recommendations will be replaced — the other periods are kept.`)) {
            return;
        }
        
        showProgressContainer();
        safeSetText('progressStatus', 'Starting...');
        updateProgress(0, 0, 0, 0);
        
        const details = safeGetElement('progressDetails');
        if (details) {
            details.innerHTML = `
                <div class="list-group-item text-center text-muted py-3">
                    <i class="fas fa-spinner fa-spin me-2"></i> Initializing generation...
                </div>
            `;
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('generateAllModal'));
        if (modal) modal.hide();
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/academic-head/recommendations/generate-block', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                block_id: parseInt(blockId),
                period: period,
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                const total = data.total || 0;
                const success = data.success_count || 0;
                const failed = data.failed_count || 0;
                
                updateProgress(100, total, success, failed);
                safeSetText('progressStatus', 'Complete!');
                
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-success ms-2';
                
                if (data.progress && data.progress.length > 0) {
                    showProgressDetails(data.progress);
                }
                
                setTimeout(() => {
                    alert('' + data.message);
                    window.location.reload();
                }, 1500);
            } else {
                safeSetText('progressStatus', '❌ Failed');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-danger ms-2';
                alert('' + (data.message || 'Unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            safeSetText('progressStatus', '❌ Error');
            const statusEl = safeGetElement('progressStatus');
            if (statusEl) statusEl.className = 'badge bg-danger ms-2';
            alert('❌ Failed to generate recommendations: ' + error.message);
        });
    }

    function generateStudent(studentId) {
        const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
        
        let studentName = 'Student';
        const btns = document.querySelectorAll(`button[onclick*="generateStudent(${studentId})"]`);
        if (btns.length > 0) {
            const row = btns[0].closest('tr');
            if (row) {
                const nameCell = row.querySelector('td:nth-child(2)');
                if (nameCell) {
                    studentName = nameCell.textContent.trim();
                }
            }
        }
        
        if (!blockId || blockId === '0') {
            alert('Please select a block first.');
            return;
        }
        
        if (!confirm(`Generate AI recommendation for ${studentName} for the ${currentPeriod()} period?`)) {
            return;
        }
        
        showProgressContainer();
        safeSetText('progressStatus', 'Processing...');
        updateProgress(0, 1, 0, 0);
        
        const details = safeGetElement('progressDetails');
        if (details) {
            details.innerHTML = `
                <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted"> ${studentName}</span>
                    <span class="small">Processing...</span>
                </div>
            `;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/academic-head/recommendations/generate-selected', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: [parseInt(studentId)],
                block_id: parseInt(blockId),
                period: currentPeriod(),
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateProgress(100, 1, 1, 0);
                safeSetText('progressStatus', ' Complete!');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-success ms-2';
                
                if (details) {
                    details.innerHTML = `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <span class="text-success"> ${studentName}</span>
                            <span class="small">Success</span>
                        </div>
                    `;
                }
                
                setTimeout(() => {
                    alert('' + data.message);
                    window.location.reload();
                }, 1500);
            } else {
                updateProgress(100, 1, 0, 1);
                safeSetText('progressStatus', ' Failed');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-danger ms-2';
                
                if (details) {
                    details.innerHTML = `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <span class="text-danger">❌ ${studentName}</span>
                            <span class="small">Failed: ${data.message || 'Unknown'}</span>
                        </div>
                    `;
                }
                
                alert('' + (data.message || 'Unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            updateProgress(100, 1, 0, 1);
            safeSetText('progressStatus', '❌ Error');
            const statusEl = safeGetElement('progressStatus');
            if (statusEl) statusEl.className = 'badge bg-danger ms-2';
            
            if (details) {
                details.innerHTML = `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span class="text-danger">❌ ${studentName}</span>
                        <span class="small">Error: ${error.message}</span>
                    </div>
                `;
            }
            
            alert('❌ Failed to generate recommendation: ' + error.message);
        });
    }

    function deleteRec(recId) {
    deleteRecId = recId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
    if (!deleteRecId) return;
    
    const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    const url = `/academic-head/recommendations/${deleteRecId}/delete`;
    
    fetch(url, {
        method: 'DELETE',  // Ensure this is DELETE, not POST
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            block_id: parseInt(blockId),
        }),
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Recommendation deleted successfully.');
            window.location.reload();
        } else {
            alert('' + (data.message || 'Failed to delete.'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to delete recommendation: ' + error.message);
    })
    .finally(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
        if (modal) modal.hide();
    });
});

document.addEventListener('click', function (event) {
    const target = event.target;

    if (!target || typeof target.closest !== 'function') {
        return;
    }

    const viewBtn = target.closest('.btn-view-rec');

    if (viewBtn) {
        event.preventDefault();
        viewRecommendation(viewBtn.getAttribute('data-rec'));
        return;
    }

    const deleteBtn = target.closest('.btn-delete-rec');

    if (deleteBtn) {
        event.preventDefault();
        deleteRec(deleteBtn.getAttribute('data-rec'));
        return;
    }

    const generateBtn = target.closest('.btn-generate-rec');

    if (generateBtn) {
        event.preventDefault();
        generateStudent(generateBtn.getAttribute('data-student'));
        return;
    }

    if (target.closest('#resetRecommendationFilters')) {
        event.preventDefault();
        resetRecommendationFilters();
    }
});

function applyRecommendationFilters() {
    const searchInput = document.getElementById('recommendationSearch');
    const riskSelect = document.getElementById('recommendationRiskFilter');
    const rows = document.querySelectorAll('#recommendationTableBody tr[data-risk]');

    const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const risk = riskSelect ? riskSelect.value : 'all';

    let visible = 0;

    Array.prototype.forEach.call(rows, function (row) {
        const haystack = row.getAttribute('data-search') || '';
        const rowRisk = row.getAttribute('data-risk') || '';
        const hasRec = row.getAttribute('data-has-rec') === '1';

        let show = true;

        if (term !== '' && haystack.indexOf(term) === -1) {
            show = false;
        }

        if (risk === 'none') {
            if (hasRec) {
                show = false;
            }
        } else if (risk !== 'all' && rowRisk !== risk) {
            show = false;
        }

        row.style.display = show ? '' : 'none';

        if (show) {
            visible++;
        }
    });

    const counter = document.getElementById('recommendationVisibleCount');

    if (counter) {
        counter.textContent = visible;
    }
}

function resetRecommendationFilters() {
    const searchInput = document.getElementById('recommendationSearch');
    const riskSelect = document.getElementById('recommendationRiskFilter');

    if (searchInput) {
        searchInput.value = '';
    }

    if (riskSelect) {
        riskSelect.value = 'all';
    }

    applyRecommendationFilters();
}

const recommendationSearchInput = document.getElementById('recommendationSearch');
const recommendationRiskSelect = document.getElementById('recommendationRiskFilter');

if (recommendationSearchInput) {
    recommendationSearchInput.addEventListener('input', applyRecommendationFilters);
}

if (recommendationRiskSelect) {
    recommendationRiskSelect.addEventListener('change', applyRecommendationFilters);
}

(function () {
    const editBtn = document.getElementById('editRecommendationBtn');
    const modalEl = document.getElementById('viewRecommendationModal');

    if (!editBtn || !modalEl) {
        return;
    }

    modalEl.addEventListener('show.bs.modal', function () {
        if (viewingRecId) {
            editBtn.setAttribute('href',
                '/academic-head/recommendations/' + viewingRecId + '/edit?block_id=' + encodeURIComponent('{{ $blockId ?? "" }}'));
        }
    });
})();
</script>
@endpush
@extends('layouts.app')

@section('title', 'My Grades - AcadAlert')

@section('page_title', 'My Grades')
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
    // Roll the roster up once, here, so the hero and the four metrics capsules
    // all read from the same figures rather than each re-deriving them.
    $gradeCount = $subjectGrades->count();
    $average    = $gradeCount > 0 ? round($subjectGrades->avg('numerical_grade'), 1) : null;
    $failing    = $subjectGrades->where('numerical_grade', '<', 75)->count();
    $atRisk     = $subjectGrades->where('numerical_grade', '>=', 75)->where('numerical_grade', '<', 80)->count();
    $passing    = max($gradeCount - $failing - $atRisk, 0);

    $averageTone = $average === null
        ? 'text-muted'
        : ($average < 75 ? 'text-danger' : ($average < 80 ? 'text-warning' : 'text-success'));
@endphp

<div class="ah-page sp-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

    <div class="sp-welcome ah-reveal" style="--ah-i: 0;">
        <span class="sp-welcome-icon"><i class="fas fa-book"></i></span>
        <div class="flex-grow-1">
            <h2 class="sp-welcome-title">
                {{ $average === null ? 'No grades recorded yet' : $average . '% average' }}
            </h2>
            <p class="sp-welcome-sub">
                {{ $gradeCount }} subject(s) in <strong>{{ $currentPeriod }} {{ $schoolYear }}</strong>
                &middot; {{ $studentInfo->program_code }} — {{ $studentInfo->year_level }}
            </p>
        </div>
    </div>

    <div class="row ah-reveal sp-metrics text-center mb-4" style="--ah-i: 1;">
        <div class="col-6 col-lg-3">
            <div class="text-muted small text-uppercase">Average</div>
            <div class="fw-bold fs-4 {{ $averageTone }}">
                {{ $average === null ? '—' : $average . '%' }}
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="text-muted small text-uppercase">Passing</div>
            <div class="fw-bold fs-4 {{ $passing > 0 ? 'text-success' : 'text-muted' }}">{{ $passing }}</div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="text-muted small text-uppercase">At Risk</div>
            <div class="fw-bold fs-4 {{ $atRisk > 0 ? 'text-warning' : 'text-muted' }}">{{ $atRisk }}</div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="text-muted small text-uppercase">Failing</div>
            <div class="fw-bold fs-4 {{ $failing > 0 ? 'text-danger' : 'text-success' }}">{{ $failing }}</div>
        </div>
    </div>

    <div class="row ah-reveal" style="--ah-i: 2;">
        <div class="col-12 mb-4">
            <div class="card ah-glow">
                <div class="card-header">
                    <i class="fas fa-list-ol text-primary"></i> Subject Grades
                    <span class="badge bg-light text-primary ms-2">{{ $currentPeriod }} {{ $schoolYear }}</span>
                </div>
            <div class="card-body">
                @if($subjectGrades->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subjectGrades as $index => $grade)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="badge bg-secondary">{{ $grade->subject_code }}</span></td>
                                    <td>{{ $grade->subject_name }}</td>
                                    <td>
                                        <span class="{{ $grade->numerical_grade < 75 ? 'text-danger fw-bold' : ($grade->numerical_grade < 80 ? 'text-warning' : '') }}">
                                            {{ $grade->numerical_grade }}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $grade->letter_grade === 'F' ? 'bg-danger' : ($grade->letter_grade === 'D' ? 'bg-warning' : 'bg-success') }}">
                                            {{ $grade->letter_grade ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($grade->numerical_grade < 75)
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Failing</span>
                                        @elseif($grade->numerical_grade < 80)
                                            <span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i> At Risk</span>
                                        @else
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Passing</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <td colspan="3"><strong>Average</strong></td>
                                    <td>
                                        <strong>
                                            @php
                                                $avg = $subjectGrades->avg('numerical_grade');
                                            @endphp
                                            <span class="{{ $avg < 75 ? 'text-danger' : ($avg < 80 ? 'text-warning' : 'text-success') }}">
                                                {{ round($avg, 1) }}%
                                            </span>
                                        </strong>
                                    </td>
                                    <td colspan="2">
                                        @php
                                            $failingCount = $subjectGrades->where('numerical_grade', '<', 75)->count();
                                        @endphp
                                        @if($failingCount > 0)
                                            <span class="badge bg-danger"> {{ $failingCount }} failing subject(s)</span>
                                        @else
                                            <span class="badge bg-success">All subjects passed</span>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-book fa-3x d-block mb-3"></i>
                        <p class="mb-1">No grades available for this period.</p>
                        <small>Grades are posted by your instructor once a grading period closes.</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

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
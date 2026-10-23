@extends('layouts.app')

@section('title', 'My Profile - AcadAlert')

@section('page_title', 'My Profile')

@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ $user->name }}
        </span>
        <a href="{{ route('settings') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-cog me-1"></i> Account Settings
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-id-card"></i> Identity
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                         style="width: 72px; height: 72px; font-weight: 600; font-size: 26px;">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold">{{ $user->name }}</h4>
                        <span class="badge bg-secondary">
                            {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                        </span>
                        @if($isStudent && $student)
                            <span class="badge bg-light text-dark border ms-1">{{ $student->student_number }}</span>
                        @endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small text-uppercase">Sign-in email</div>
                        <div class="fw-semibold">
                            <i class="fas fa-envelope text-primary me-2"></i>{{ $user->email }}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small text-uppercase">Account status</div>
                        <div class="fw-semibold">
                            @if((int) ($user->is_active ?? 1) === 1)
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i> Active</span>
                            @else
                                <span class="badge bg-danger"><i class="fas fa-ban me-1"></i> Inactive</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 1;">
            <div class="card-header">
                <i class="fas fa-clock text-primary"></i> Account
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Role</span>
                    <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Account created</span>
                    <span class="fw-semibold">
                        {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M j, Y') : '—' }}
                    </span>
                </div>
                @if($isStudent && $placement)
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Department</span>
                        <span class="fw-semibold">{{ $placement['program_code'] ?? '—' }}</span>
                    </div>
                @elseif($department)
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Department</span>
                        <span class="fw-semibold">{{ $department->code ?? $department->name }}</span>
                    </div>
                @endif
                <div class="alert alert-permanent alert-info mt-3 mb-0 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Name, email and password are editable in
                    <a href="{{ route('settings') }}" class="alert-link">Account Settings</a>.
                </div>
            </div>
        </div>
    </div>
</div>

@if($isStudent)
    @if($student)
        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 2;">
                    <div class="card-header">
                        <i class="fas fa-user-graduate text-primary"></i> Personal Information
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Full name</div>
                                <div class="fw-semibold">{{ $student->first_name }} {{ $student->last_name }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Student number</div>
                                <div class="fw-semibold">{{ $student->student_number ?? '—' }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Program</div>
                                <div class="fw-semibold">
                                    {{ $placement['program_code'] ?? '—' }}
                                    <div class="text-muted small fw-normal">{{ $placement['program_name'] ?? '' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Year level</div>
                                <div class="fw-semibold">{{ $placement['year_level_name'] ?? '—' }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Block</div>
                                <div class="fw-semibold">{{ $placement['block_name'] ?? '—' }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Email</div>
                                <div class="fw-semibold text-break">{{ $student->email ?? $user->email }}</div>
                            </div>
                            <div class="col-md-6 mb-0">
                                <div class="text-muted small text-uppercase">Enrolment status</div>
                                <div class="fw-semibold">
                                    @if((string) ($student->status ?? '') === 'Active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $student->status ?? '—' }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-0">
                                <div class="text-muted small text-uppercase">Year enrolled</div>
                                <div class="fw-semibold">{{ $student->year_enrolled ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mb-4">
                <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 3;">
                    <div class="card-header">
                        <i class="fas fa-people-roof text-primary"></i> Parent / Guardian Information
                    </div>
                    <div class="card-body">
                        @if($parents->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($parents as $parent)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $parent->full_name }}</div>
                                                    <small class="text-muted">{{ $parent->relationship ?? 'Guardian' }}</small>
                                                    @if((int) ($parent->is_primary_contact ?? 0) === 1)
                                                        <span class="badge bg-primary ms-1">Primary</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <i class="fas fa-phone text-primary me-1"></i>
                                                        {{ $parent->contact_number ?? '—' }}
                                                    </div>
                                                    @if(!empty($parent->email))
                                                        <div class="small text-break">
                                                            <i class="fas fa-envelope text-primary me-1"></i>
                                                            {{ $parent->email }}
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-people-roof fa-2x d-block mb-2 opacity-50"></i>
                                <p class="mb-0">No parent or guardian record on file.</p>
                                <small>Please contact the Registrar to add one.</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card border-warning ah-glow ah-reveal" style="--ah-i: 2;">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-link-slash fa-3x text-warning d-block mb-3"></i>
                        <h5>No student record linked yet</h5>
                        <p class="text-muted mb-0">
                            Your account is not connected to a student record on the student information
                            system. Please contact the Registrar or your Academic Head.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@else
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card ah-glow ah-reveal" style="--ah-i: 2;">
                <div class="card-header">
                    <i class="fas fa-briefcase text-primary"></i> Staff Information
                </div>
                <div class="card-body">
                    @if($staffProfile)
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Employee number</div>
                                <div class="fw-semibold">{{ $staffProfile->employee_number ?? '—' }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Department</div>
                                <div class="fw-semibold">
                                    {{ $department->code ?? '—' }}
                                    <div class="text-muted small fw-normal">{{ $department->name ?? '' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Specialization</div>
                                <div class="fw-semibold">{{ $staffProfile->specialization ?? '—' }}</div>
                            </div>
                            @if(isset($staffProfile->office_location))
                                <div class="col-md-6 mb-3">
                                    <div class="text-muted small text-uppercase">Office</div>
                                    <div class="fw-semibold">{{ $staffProfile->office_location }}</div>
                                    <div class="text-muted small">{{ $staffProfile->office_hours ?? '' }}</div>
                                </div>
                                <div class="col-md-6 mb-0">
                                    <div class="text-muted small text-uppercase">Contact number</div>
                                    <div class="fw-semibold">{{ $staffProfile->phone_number ?? '—' }}</div>
                                </div>
                                <div class="col-md-6 mb-0">
                                    <div class="text-muted small text-uppercase">Max caseload</div>
                                    <div class="fw-semibold">{{ $staffProfile->max_caseload ?? '—' }}</div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-id-badge fa-2x d-block mb-2 opacity-50"></i>
                            <p class="mb-0">No staff profile record on file for this account.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
</div>

@endsection

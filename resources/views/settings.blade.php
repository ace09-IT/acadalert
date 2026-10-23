@extends('layouts.app')

@section('title', 'Account Settings - AcadAlert')

@section('page_title', 'Account Settings')

@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ $user->name }}
        </span>
        <a href="{{ route('profile') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-id-card me-1"></i> View Profile
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

@if($errors->any())
    <div class="row">
        <div class="col-12 mb-4">
            <div class="alert alert-permanent alert-danger mb-0">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user-edit"></i> Account Details
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.profile') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Display name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name"
                               value="{{ old('name', $user->name) }}"
                               maxlength="255" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Shown in the top bar, the sidebar and every list that names you.</div>
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Sign-in email <span class="text-danger">*</span></label>
                        <input type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email"
                               value="{{ old('email', $user->email) }}"
                               maxlength="100" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($isStudent)
                            <div class="form-text">
                                This is the address your student record is matched on — changing it also updates
                                your student record so your dashboard keeps working.
                            </div>
                        @else
                            <div class="form-text">Used to sign in. Must be unique.</div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100 ah-glow ah-reveal" style="--ah-i: 1;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-lock"></i> Change Password
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.password') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password <span class="text-danger">*</span></label>
                        <input type="password"
                               class="form-control @error('current_password') is-invalid @enderror"
                               id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password <span class="text-danger">*</span></label>
                        <input type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password"
                               autocomplete="new-password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm new password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control"
                               id="password_confirmation" name="password_confirmation"
                               autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 2;">
            <div class="card-header">
                <i class="fas fa-circle-info text-primary"></i> Account Information
                <span class="badge bg-secondary ms-2">Read only</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small text-uppercase">Role</div>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</div>
                    </div>
                    @if($isStudent && $student)
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small text-uppercase">Student number</div>
                            <div class="fw-semibold">{{ $student->student_number ?? '—' }}</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small text-uppercase">Program / year level / block</div>
                            <div class="fw-semibold">
                                {{ $placement['program_code'] ?? '—' }}
                                <span class="text-muted">•</span>
                                {{ $placement['year_level_name'] ?? '—' }}
                                <span class="text-muted">•</span>
                                {{ $placement['block_name'] ?? '—' }}
                            </div>
                        </div>
                    @elseif($staffProfile)
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small text-uppercase">Employee number</div>
                            <div class="fw-semibold">{{ $staffProfile->employee_number ?? '—' }}</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small text-uppercase">Specialization</div>
                            <div class="fw-semibold">{{ $staffProfile->specialization ?? '—' }}</div>
                        </div>
                    @endif
                </div>
                <div class="alert alert-permanent alert-info mb-0 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Role, placement and staff records are managed by the Administrator and your Academic Head.
                    Contact them if any of the above is incorrect.
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

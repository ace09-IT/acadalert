@extends('layouts.app')

@section('title', 'Add User - AcadAlert')

@section('page_title', 'Add New User')
@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Users
    </a>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user-plus me-2"></i> Create New User
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               name="email" value="{{ old('email') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select @error('role') is-invalid @enderror" 
                                name="role" id="roleSelect" required>
                            <option value="">Select Role</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="academic_head" {{ old('role') == 'academic_head' ? 'selected' : '' }}>Academic Head</option>
                            <option value="guidance_counselor" {{ old('role') == 'guidance_counselor' ? 'selected' : '' }}>Guidance Counselor</option>
                            <option value="student" {{ old('role') == 'student' ? 'selected' : '' }}>Student</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3" id="departmentSection">
                        <label class="form-label fw-bold">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">Select Department (Optional)</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                               name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm Password</label>
                        <input type="password" class="form-control" name="password_confirmation" required>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
    document.getElementById('roleSelect').addEventListener('change', function() {
        const deptSection = document.getElementById('departmentSection');
        if (this.value === 'admin') {
            deptSection.style.display = 'none';
        } else {
            deptSection.style.display = 'block';
        }
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        const event = new Event('change');
        document.getElementById('roleSelect').dispatchEvent(event);
    });
</script>
@endpush
@extends('layouts.app')

@section('title', 'Edit User - AcadAlert')

@section('page_title', 'Edit User')
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
                <i class="fas fa-user-edit me-2"></i> Edit User: {{ $user->name }}
            </div>
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user->id) }}" id="editUserForm">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select @error('role') is-invalid @enderror" name="role" id="roleSelect" required>
                            <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="academic_head" {{ old('role', $user->role) == 'academic_head' ? 'selected' : '' }}>Academic Head</option>
                            <option value="guidance_counselor" {{ old('role', $user->role) == 'guidance_counselor' ? 'selected' : '' }}>Guidance Counselor</option>
                            <option value="student" {{ old('role', $user->role) == 'student' ? 'selected' : '' }}>Student</option>
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3" id="departmentSection">
                        <label class="form-label fw-bold">Department</label>
                        <select class="form-select" name="department_id" id="departmentSelect">
                            <option value="">None</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" 
                                    {{ old('department_id', $additional->department_id ?? '') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->code }} - {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Required for Academic Head and Counselor roles</small>
                    </div>
                    
                    <div id="studentFields" style="display: none;">
                        <hr>
                        <h6 class="fw-bold text-primary">Student Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">First Name</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="{{ old('first_name', $studentDetails->first_name ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Last Name</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="{{ old('last_name', $studentDetails->last_name ?? '') }}">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Student Number</label>
                            <input type="text" class="form-control" name="student_number" 
                                   value="{{ old('student_number', $studentDetails->student_number ?? '') }}">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Department</label>
                            <select class="form-select" name="student_department_id" id="studentDepartmentSelect">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" 
                                        {{ old('student_department_id', $studentDetails->program_id ?? '') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->code }} - {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Program</label>
                            <select class="form-select" name="program_id" id="programSelect">
                                <option value="">Select Program</option>
                                @if(isset($studentDetails->program_id))
                                    @foreach($programs->where('id', $studentDetails->program_id) as $program)
                                        <option value="{{ $program->id }}" selected>{{ $program->code }} - {{ $program->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Year Level</label>
                            <select class="form-select" name="year_level_id" id="yearLevelSelect">
                                <option value="">Select Year Level</option>
                                @if(isset($studentDetails->year_level_id))
                                    @foreach($yearLevels->where('id', $studentDetails->year_level_id) as $yearLevel)
                                        <option value="{{ $yearLevel->id }}" selected>{{ $yearLevel->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Block</label>
                            <select class="form-select" name="block_id" id="blockSelect">
                                <option value="">Select Block</option>
                                @if(isset($studentDetails->block_id))
                                    @foreach($blocks->where('id', $studentDetails->block_id) as $block)
                                        <option value="{{ $block->id }}" selected>{{ $block->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Student Status</label>
                            <select class="form-select" name="status">
                                <option value="Active" {{ old('status', $studentDetails->status ?? '') == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Inactive" {{ old('status', $studentDetails->status ?? '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Status</label>
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="form-check-input" name="is_active" id="isActive" value="1"
                                   {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm Password</label>
                        <input type="password" class="form-control" name="password_confirmation">
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="resetConfirmationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Student Transfer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Warning:</strong> Changing a student's department, program, year level, or block will <strong>reset all academic data</strong> including:
                </div>
                <ul>
                    <li>Grades</li>
                    <li>Attendance records</li>
                    <li>Risk scores</li>
                    <li>Flags and alerts</li>
                    <li>Intervention recommendations</li>
                    <li>Open cases will be closed</li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    The student will start fresh with new subjects assigned to their new program.
                </div>
                <p class="text-muted small mt-2">Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetBtn">
                    <i class="fas fa-sync me-1"></i> Confirm Transfer & Reset
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('roleSelect');
        const departmentSection = document.getElementById('departmentSection');
        const studentFields = document.getElementById('studentFields');
        const deptSelect = document.getElementById('studentDepartmentSelect');
        const programSelect = document.getElementById('programSelect');
        const yearLevelSelect = document.getElementById('yearLevelSelect');
        const blockSelect = document.getElementById('blockSelect');
        
        const form = document.getElementById('editUserForm');
        const resetModal = new bootstrap.Modal(document.getElementById('resetConfirmationModal'));
        
        let originalBlockId = '{{ $studentDetails->block_id ?? '' }}';
        let originalProgramId = '{{ $studentDetails->program_id ?? '' }}';
        let originalYearLevelId = '{{ $studentDetails->year_level_id ?? '' }}';
        
        function toggleFields() {
            const role = roleSelect.value;
            
            if (role === 'admin') {
                departmentSection.style.display = 'none';
            } else {
                departmentSection.style.display = 'block';
            }
            
            if (role === 'student') {
                studentFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
            }
        }
        
        function loadPrograms(departmentId, selectedProgramId) {
            programSelect.innerHTML = '<option value="">Loading...</option>';
            fetch(`/admin/get-programs/${departmentId}`)
                .then(response => response.json())
                .then(data => {
                    programSelect.innerHTML = '<option value="">Select Program</option>';
                    data.forEach(program => {
                        const option = document.createElement('option');
                        option.value = program.id;
                        option.textContent = program.code + ' - ' + program.name;
                        if (program.id == selectedProgramId) {
                            option.selected = true;
                        }
                        programSelect.appendChild(option);
                    });
                    if (selectedProgramId) {
                        loadYearLevels(selectedProgramId);
                    }
                })
                .catch(() => {
                    programSelect.innerHTML = '<option value="">Error loading programs</option>';
                });
        }
        
        function loadYearLevels(programId, selectedYearLevelId) {
            yearLevelSelect.innerHTML = '<option value="">Loading...</option>';
            fetch(`/admin/get-year-levels/${programId}`)
                .then(response => response.json())
                .then(data => {
                    yearLevelSelect.innerHTML = '<option value="">Select Year Level</option>';
                    data.forEach(yearLevel => {
                        const option = document.createElement('option');
                        option.value = yearLevel.id;
                        option.textContent = yearLevel.name;
                        if (yearLevel.id == selectedYearLevelId) {
                            option.selected = true;
                        }
                        yearLevelSelect.appendChild(option);
                    });
                    if (selectedYearLevelId) {
                        loadBlocks(selectedYearLevelId);
                    }
                })
                .catch(() => {
                    yearLevelSelect.innerHTML = '<option value="">Error loading year levels</option>';
                });
        }
        
        function loadBlocks(yearLevelId, selectedBlockId) {
            blockSelect.innerHTML = '<option value="">Loading...</option>';
            fetch(`/admin/get-blocks/${yearLevelId}`)
                .then(response => response.json())
                .then(data => {
                    blockSelect.innerHTML = '<option value="">Select Block</option>';
                    data.forEach(block => {
                        const option = document.createElement('option');
                        option.value = block.id;
                        option.textContent = block.name;
                        if (block.id == selectedBlockId) {
                            option.selected = true;
                        }
                        blockSelect.appendChild(option);
                    });
                })
                .catch(() => {
                    blockSelect.innerHTML = '<option value="">Error loading blocks</option>';
                });
        }
        
        form.addEventListener('submit', function(e) {
            const role = roleSelect.value;
            
            // Only check for students
            if (role !== 'student') {
                return;
            }
            
            const newBlockId = document.getElementById('blockSelect').value;
            const newProgramId = document.getElementById('programSelect').value;
            const newYearLevelId = document.getElementById('yearLevelSelect').value;
            
            const blockChanged = originalBlockId && newBlockId && originalBlockId != newBlockId;
            const programChanged = originalProgramId && newProgramId && originalProgramId != newProgramId;
            const yearLevelChanged = originalYearLevelId && newYearLevelId && originalYearLevelId != newYearLevelId;
            
            if (blockChanged || programChanged || yearLevelChanged) {
                e.preventDefault();
                resetModal.show();
            }
        });
        
        document.getElementById('confirmResetBtn').addEventListener('click', function() {
            form.submit();
            resetModal.hide();
        });
        
        roleSelect.addEventListener('change', toggleFields);
        
        deptSelect.addEventListener('change', function() {
            const selectedProgramId = programSelect.value;
            if (this.value) {
                loadPrograms(this.value, selectedProgramId);
            } else {
                programSelect.innerHTML = '<option value="">Select Program</option>';
                yearLevelSelect.innerHTML = '<option value="">Select Year Level</option>';
                blockSelect.innerHTML = '<option value="">Select Block</option>';
            }
        });
        
        programSelect.addEventListener('change', function() {
            if (this.value) {
                loadYearLevels(this.value);
            } else {
                yearLevelSelect.innerHTML = '<option value="">Select Year Level</option>';
                blockSelect.innerHTML = '<option value="">Select Block</option>';
            }
        });
        
        yearLevelSelect.addEventListener('change', function() {
            if (this.value) {
                loadBlocks(this.value);
            } else {
                blockSelect.innerHTML = '<option value="">Select Block</option>';
            }
        });
        
        toggleFields();
        
        if (roleSelect.value === 'student') {
            const initialDeptId = document.getElementById('studentDepartmentSelect').value;
            const initialProgramId = programSelect.value;
            if (initialDeptId) {
                loadPrograms(initialDeptId, initialProgramId);
            }
        }
    });
</script>
@endpush
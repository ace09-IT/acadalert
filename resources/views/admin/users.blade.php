@extends('layouts.app')

@section('title', 'Users Management - AcadAlert')

@section('page_title', 'Users Management')
@section('page_actions')
    <div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add User
        </a>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-filter me-2"></i> Filters
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.users.index') }}" id="filterForm">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Role</label>
                            <select class="form-select form-select-sm" name="role" id="roleFilter">
                                <option value="all">All Roles</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ $roleFilter == $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Department</label>
                            <select class="form-select form-select-sm" name="department" id="departmentFilter">
                                <option value="all">All Departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ $departmentFilter == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Program</label>
                            <select class="form-select form-select-sm" name="program" id="programFilter">
                                <option value="all">All Programs</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ $programFilter == $program->id ? 'selected' : '' }}>
                                        {{ $program->code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Year Level</label>
                            <select class="form-select form-select-sm" name="year_level" id="yearLevelFilter">
                                <option value="all">All Year Levels</option>
                                @foreach($yearLevels as $yearLevel)
                                    <option value="{{ $yearLevel->id }}" {{ $yearLevelFilter == $yearLevel->id ? 'selected' : '' }}>
                                        {{ $yearLevel->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Block</label>
                            <select class="form-select form-select-sm" name="block" id="blockFilter">
                                <option value="all">All Blocks</option>
                                @foreach($blocks as $block)
                                    <option value="{{ $block->id }}" {{ $blockFilter == $block->id ? 'selected' : '' }}>
                                        {{ $block->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Search</label>
                            <div class="d-flex gap-1">
                                <input type="text" class="form-control form-control-sm" name="search" 
                                       placeholder="Name, email, student #" value="{{ $searchFilter }}">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                            <span class="text-muted small ms-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing {{ $users->total() }} users
                            </span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 1;">
            <div class="card-header">
                <i class="fas fa-users text-primary me-2"></i>
                Users List
                <span class="badge bg-secondary ms-2">{{ $users->total() }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Program/Block</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge {{ $user->role === 'admin' ? 'bg-danger' : ($user->role === 'academic_head' ? 'bg-primary' : ($user->role === 'guidance_counselor' ? 'bg-success' : 'bg-secondary')) }}">
                                        {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $deptCode = 'N/A';
                                        if ($user->role === 'academic_head') {
                                            // departments is served by the mock API, so the
                                            // id -> code map is passed in from the controller.
                                            $deptCode = $departmentCodes[$user->mt_department_id] ?? 'N/A';
                                        } elseif ($user->role === 'guidance_counselor') {
                                            $deptCode = $departmentCodes[$user->counselor_department_id] ?? 'N/A';
                                        } elseif ($user->role === 'student') {
                                            $deptCode = $user->student_department_code ?? 'N/A';
                                        }
                                    @endphp
                                    {{ $deptCode }}
                                </td>
                                <td>
                                    @if($user->role === 'student')
                                        @if($user->program_code)
                                            <span class="badge bg-secondary">{{ $user->program_code }}</span>
                                        @endif
                                        @if($user->year_level_name)
                                            <span class="badge bg-info">{{ $user->year_level_name }}</span>
                                        @endif
                                        @if($user->block_name)
                                            <span class="badge bg-primary">{{ $user->block_name }}</span>
                                        @endif
                                    @elseif($user->role === 'academic_head' || $user->role === 'guidance_counselor')
                                        <span class="text-muted small">—</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @if($user->id !== auth()->id())
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser({{ $user->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x d-block mb-2"></i>
                                    No users found matching the selected filters.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $users->links() }}
            </div>
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
                <p>Are you sure you want to delete this user?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i> Delete User
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let deleteUserId = null;

    function deleteUser(id) {
        deleteUserId = id;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deleteUserId) return;
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        fetch(`/admin/users/${deleteUserId}/delete`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                window.location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Error: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash me-1"></i> Delete User';
            deleteUserId = null;
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const departmentFilter = document.getElementById('departmentFilter');
        const programFilter = document.getElementById('programFilter');
        const yearLevelFilter = document.getElementById('yearLevelFilter');
        const blockFilter = document.getElementById('blockFilter');
        const roleFilter = document.getElementById('roleFilter');
        const filterForm = document.getElementById('filterForm');

        const programOptions = programFilter.innerHTML;
        const yearLevelOptions = yearLevelFilter.innerHTML;
        const blockOptions = blockFilter.innerHTML;

        departmentFilter.addEventListener('change', function() {
            const departmentId = this.value;
            
            if (departmentId !== 'all') {
                fetch(`/admin/get-programs/${departmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        programFilter.innerHTML = '<option value="all">All Programs</option>';
                        data.forEach(program => {
                            const option = document.createElement('option');
                            option.value = program.id;
                            option.textContent = program.code + ' - ' + program.name;
                            programFilter.appendChild(option);
                        });
                        
                        yearLevelFilter.innerHTML = '<option value="all">All Year Levels</option>';
                        blockFilter.innerHTML = '<option value="all">All Blocks</option>';
                    })
                    .catch(() => {
                        programFilter.innerHTML = programOptions;
                    });
            } else {
                programFilter.innerHTML = programOptions;
                yearLevelFilter.innerHTML = yearLevelOptions;
                blockFilter.innerHTML = blockOptions;
            }
        });

        programFilter.addEventListener('change', function() {
            const programId = this.value;
            
            if (programId !== 'all') {
                fetch(`/admin/get-year-levels/${programId}`)
                    .then(response => response.json())
                    .then(data => {
                        yearLevelFilter.innerHTML = '<option value="all">All Year Levels</option>';
                        data.forEach(yearLevel => {
                            const option = document.createElement('option');
                            option.value = yearLevel.id;
                            option.textContent = yearLevel.name;
                            yearLevelFilter.appendChild(option);
                        });
                        
                        blockFilter.innerHTML = '<option value="all">All Blocks</option>';
                    })
                    .catch(() => {
                        yearLevelFilter.innerHTML = yearLevelOptions;
                    });
            } else {
                yearLevelFilter.innerHTML = yearLevelOptions;
                blockFilter.innerHTML = blockOptions;
            }
        });

        yearLevelFilter.addEventListener('change', function() {
            const yearLevelId = this.value;
            
            if (yearLevelId !== 'all') {
                fetch(`/admin/get-blocks/${yearLevelId}`)
                    .then(response => response.json())
                    .then(data => {
                        blockFilter.innerHTML = '<option value="all">All Blocks</option>';
                        data.forEach(block => {
                            const option = document.createElement('option');
                            option.value = block.id;
                            option.textContent = block.name;
                            blockFilter.appendChild(option);
                        });
                    })
                    .catch(() => {
                        blockFilter.innerHTML = blockOptions;
                    });
            } else {
                blockFilter.innerHTML = blockOptions;
            }
        });

        const autoSubmitFilters = [departmentFilter, programFilter, yearLevelFilter, blockFilter, roleFilter];
        autoSubmitFilters.forEach(el => {
            if (el) {
                el.addEventListener('change', function() {
                    // Only submit if not triggered by initial load
                    if (!this.dataset.initializing) {
                        filterForm.submit();
                    }
                });
            }
        });

        document.querySelectorAll('select').forEach(el => {
            el.dataset.initializing = 'true';
            setTimeout(() => {
                el.dataset.initializing = 'false';
            }, 500);
        });
    });
</script>
@endpush
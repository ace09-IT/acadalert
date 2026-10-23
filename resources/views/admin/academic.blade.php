@extends('layouts.app')

@section('title', 'Academic Structure - AcadAlert')

@section('page_title', 'Academic Structure Management')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="fas fa-plus me-1"></i> Add Department
        </button>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addProgramModal">
            <i class="fas fa-plus me-1"></i> Add Program
        </button>
        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus me-1"></i> Add Subject
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-building me-2"></i> Departments
                <span class="badge bg-light text-primary ms-2">{{ count($departments) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Programs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $dept)
                            <tr id="dept-row-{{ $dept->id }}">
                                <td><span class="badge bg-primary">{{ $dept->code }}</span></td>
                                <td>{{ $dept->name }}</td>
                                <td>{{ $dept->programs_count ?? 0 }}</td>
                                <td>
                                    <a href="{{ route('admin.academic.department.edit', $dept->id) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteDepartment({{ $dept->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted">No departments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 1;">
            <div class="card-header bg-success text-white">
                <i class="fas fa-graduation-cap me-2"></i> Programs
                <span class="badge bg-light text-success ms-2">{{ count($programs) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programs as $program)
                            <tr id="program-row-{{ $program->id }}">
                                <td><span class="badge bg-success">{{ $program->code }}</span></td>
                                <td>{{ $program->name }}</td>
                                <td>{{ $program->department_code }}</td>
                                <td>{{ $program->total_students }}</td>
                                <td>
                                    <a href="{{ route('admin.academic.program.edit', $program->id) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteProgram({{ $program->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted">No programs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 2;">
            <div class="card-header bg-info text-white">
                <i class="fas fa-book me-2"></i> Subjects
                <span class="badge bg-light text-info ms-2">{{ count($subjects) }}</span>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year</th>
                                <th>Sem</th>
                                <th>Units</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subjects as $subject)
                            <tr id="subject-row-{{ $subject->id }}">
                                <td><span class="badge bg-info">{{ $subject->subject_code }}</span></td>
                                <td>{{ $subject->subject_name }}</td>
                                <td>{{ $subject->program_code }}</td>
                                <td>{{ $subject->year_level }}</td>
                                <td>{{ $subject->semester }}</td>
                                <td>{{ $subject->units }}</td>
                                <td>
                                    <a href="{{ route('admin.academic.subject.edit', $subject->id) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSubject({{ $subject->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No subjects found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.academic.department.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g., SITE" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" placeholder="School of IT Education" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.academic.program.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Department</label>
                        <select class="form-select" name="department_id" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g., BSIT" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" placeholder="BS Information Technology" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Total Students</label>
                        <input type="number" class="form-control" name="total_students" value="160" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.academic.subject.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Program</label>
                        <select class="form-select" name="program_id" required>
                            <option value="">Select Program</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->code }} - {{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Year Level</label>
                            <select class="form-select" name="year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Semester</label>
                            <select class="form-select" name="semester" required>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Subject Name</label>
                            <input type="text" class="form-control" name="subject_name" placeholder="e.g., Computer Programming 1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" placeholder="e.g., ITP101" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Units</label>
                        <input type="number" class="form-control" name="units" value="3" min="1" max="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this item?</p>
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
    let deleteType = null;
    let deleteId = null;
    let deleteUrl = '';

    function deleteDepartment(id) {
        deleteType = 'department';
        deleteId = id;
        deleteUrl = '{{ route("admin.academic.department.delete", ":id") }}'.replace(':id', id);
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    function deleteProgram(id) {
        deleteType = 'program';
        deleteId = id;
        deleteUrl = '{{ route("admin.academic.program.delete", ":id") }}'.replace(':id', id);
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    function deleteSubject(id) {
        deleteType = 'subject';
        deleteId = id;
        deleteUrl = '{{ route("admin.academic.subject.delete", ":id") }}'.replace(':id', id);
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deleteId || !deleteType || !deleteUrl) return;
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        let rowId = '';
        if (deleteType === 'department') {
            rowId = `dept-row-${deleteId}`;
        } else if (deleteType === 'program') {
            rowId = `program-row-${deleteId}`;
        } else if (deleteType === 'subject') {
            rowId = `subject-row-${deleteId}`;
        }

        fetch(deleteUrl, {
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
                const row = document.getElementById(rowId);
                if (row) row.remove();
                showToast('' + data.message, 'success');
            } else {
                showToast('' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message, 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash me-1"></i> Delete';
            deleteId = null;
            deleteType = null;
            deleteUrl = '';
        });
    });

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
        setTimeout(() => toast.remove(), 4000);
    }
</script>
@endpush
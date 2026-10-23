@extends('layouts.app')

@section('title', 'School Year Management - AcadAlert')

@section('page_title', 'School Year Management')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSchoolYearModal">
            <i class="fas fa-plus me-1"></i> Create School Year
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-alt me-2"></i> School Years
                <span class="badge bg-light text-primary ms-2">{{ count($schoolYears) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>School Year</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Archived</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schoolYears as $year)
                            <tr>
                                <td><strong>{{ $year->name }}</strong></td>
                                <td>{{ date('M d, Y', strtotime($year->started_at)) }}</td>
                                <td>{{ $year->ended_at ? date('M d, Y', strtotime($year->ended_at)) : 'Ongoing' }}</td>
                                <td>
                                    @if($year->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($year->is_archived)
                                        <span class="badge bg-warning">Archived</span>
                                    @else
                                        <span class="badge bg-secondary">Active</span>
                                    @endif
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    @if(!$year->is_archived && !$year->is_active)
                                        <button class="btn btn-sm btn-outline-warning" onclick="archiveYear({{ $year->id }})">
                                            <i class="fas fa-archive me-1"></i> Archive
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted">No school years found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="addSchoolYearModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i> Create School Year
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.schoolyear.create') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">School Year</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., 2025-2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" class="form-control" name="started_at" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" class="form-control" name="ended_at">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="isActiveCheck">
                        <label class="form-check-label" for="isActiveCheck">Set as Active School Year</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function archiveYear(id) {
        if (confirm('Are you sure you want to archive this school year?')) {
            alert('School year ' + id + ' archived (Placeholder)');
        }
    }
</script>
@endpush
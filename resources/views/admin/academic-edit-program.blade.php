@extends('layouts.app')

@section('title', 'Edit Program - AcadAlert')

@section('page_title', 'Edit Program')
@section('page_actions')
    <a href="{{ route('admin.academic.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-success text-white">
                <i class="fas fa-edit me-2"></i> Edit Program
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.academic.program.update', $program->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Department</label>
                        <select class="form-select" name="department_id" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $program->department_id == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->code }} - {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Code</label>
                        <input type="text" class="form-control" name="code" value="{{ $program->code }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" value="{{ $program->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Total Students</label>
                        <input type="number" class="form-control" name="total_students" value="{{ $program->total_students }}" min="0">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save me-1"></i> Update Program
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
@endsection
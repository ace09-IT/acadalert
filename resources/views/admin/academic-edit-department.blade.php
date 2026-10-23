@extends('layouts.app')

@section('title', 'Edit Department - AcadAlert')

@section('page_title', 'Edit Department')
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
            <div class="card-header bg-primary text-white">
                <i class="fas fa-edit me-2"></i> Edit Department
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.academic.department.update', $department->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Code</label>
                        <input type="text" class="form-control" name="code" value="{{ $department->code }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" value="{{ $department->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ $department->description }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Update Department
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
@endsection
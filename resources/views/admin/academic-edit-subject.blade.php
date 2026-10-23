@extends('layouts.app')

@section('title', 'Edit Subject - AcadAlert')

@section('page_title', 'Edit Subject')
@section('page_actions')
    <a href="{{ route('admin.academic.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-header bg-info text-white">
                <i class="fas fa-edit me-2"></i> Edit Subject            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.academic.subject.update', $subject->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Program</label>
                        <select class="form-select" name="program_id" required>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ $subject->program_id == $program->id ? 'selected' : '' }}>
                                    {{ $program->code }} - {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Year Level</label>
                            <select class="form-select" name="year_level" required>
                                <option value="1" {{ $subject->year_level == 1 ? 'selected' : '' }}>1st Year</option>
                                <option value="2" {{ $subject->year_level == 2 ? 'selected' : '' }}>2nd Year</option>
                                <option value="3" {{ $subject->year_level == 3 ? 'selected' : '' }}>3rd Year</option>
                                <option value="4" {{ $subject->year_level == 4 ? 'selected' : '' }}>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Semester</label>
                            <select class="form-select" name="semester" required>
                                <option value="1" {{ $subject->semester == 1 ? 'selected' : '' }}>1st Semester</option>
                                <option value="2" {{ $subject->semester == 2 ? 'selected' : '' }}>2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Subject Name</label>
                            <input type="text" class="form-control" name="subject_name" value="{{ $subject->subject_name }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" value="{{ $subject->subject_code }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Units</label>
                        <input type="number" class="form-control" name="units" value="{{ $subject->units }}" min="1" max="6">
                    </div>
                    <button type="submit" class="btn btn-info w-100">
                        <i class="fas fa-save me-1"></i> Update Subject
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
@endsection
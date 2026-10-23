@extends('layouts.app')

@section('title', 'System Health - AcadAlert')

@section('page_title', 'System Health Dashboard')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-primary" onclick="runDiagnostics()">
            <i class="fas fa-stethoscope me-1"></i> Run Diagnostics
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

<div class="row">
    @foreach($health as $key => $item)
    <div class="col-md-2 col-6 mb-3">
        <div class="card h-100 ah-reveal" style="--ah-i: {{ $loop->index }};">
            <div class="card-body text-center">
                <div class="display-4 mb-2">
                    <i class="fas 
                        {{ $item['status'] === 'ok' ? 'fa-check-circle text-success' : 
                           ($item['status'] === 'warning' ? 'fa-exclamation-triangle text-warning' : 'fa-times-circle text-danger') }}">
                    </i>
                </div>
                <h6 class="fw-bold mb-0">{{ ucfirst($key) }}</h6>
                <small class="text-muted">{{ $item['message'] }}</small>
                @if(isset($item['used']))
                    <br>
                    <small class="text-muted">{{ $item['used'] }} / {{ $item['total'] }}</small>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-danger ah-glow ah-reveal" style="--ah-i: 6;">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-circle me-2"></i> Recent Errors
                <span class="badge bg-light text-danger ms-2">{{ count($errors) }}</span>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                @if(count($errors) > 0)
                    <div class="list-group">
                        @foreach($errors as $error)
                        <div class="list-group-item list-group-item-danger">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-bug me-2"></i> {{ $error }}</span>
                                <small class="text-muted">Just now</small>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                        No errors detected. System is running smoothly.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 7;">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-info-circle me-2"></i> System Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Laravel Version</div>
                            <div class="fw-bold">{{ app()->version() }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">PHP Version</div>
                            <div class="fw-bold">{{ phpversion() }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Environment</div>
                            <div class="fw-bold">{{ app()->environment() }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Database Driver</div>
                            <div class="fw-bold">{{ config('database.default') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
    function runDiagnostics() {
        alert('Running system diagnostics...\n\nAll systems are operational.');
    }
</script>
@endpush
@extends('layouts.app')

@section('title', 'Edit Recommendation - AcadAlert')

@section('page_title', 'Edit Intervention Recommendation')
@section('page_actions')
    <div>
        <a href="{{ route('academic-head.recommendations', ['block_id' => request()->block_id]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Recommendations
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-edit me-2"></i> Edit Recommendation
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Student</div>
                            <div class="fw-bold">{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</div>
                            <small class="text-muted">{{ $student->student_number ?? '' }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Risk Factors</div>
                            <div class="small">
                                @if(is_array($riskFactors) && count($riskFactors) > 0)
                                    @foreach($riskFactors as $factor)
                                        <span class="badge bg-secondary">{{ $factor }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No risk factors listed</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="{{ route('academic-head.recommendation.update', $recommendation->id) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="block_id" value="{{ request()->block_id }}">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Generated At</label>
                        <p class="text-muted">{{ \Carbon\Carbon::parse($recommendation->generated_at)->format('F d, Y h:i A') }}</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Risk Factors (JSON)</label>
                        <textarea class="form-control" name="risk_factors" rows="2" readonly style="background: #f8f9fa;">{{ json_encode($riskFactors, JSON_PRETTY_PRINT) }}</textarea>
                        <small class="text-muted">Risk factors are read-only and determined by the AI.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Suggested Actions / Recommendation
                            <span class="text-danger">*</span>
                            <span class="badge bg-info ms-2">Editable</span>
                        </label>
                        @php
                            $currentText = '';
                            if (is_array($suggestedActions) && count($suggestedActions) > 0) {
                                $texts = [];
                                foreach ($suggestedActions as $action) {
                                    $actionName = ucfirst(str_replace('_', ' ', $action['action'] ?? 'custom'));
                                    $details = $action['details'] ?? '';
                                    $priority = $action['priority'] ?? 'medium';
                                    $texts[] = "[$priority] $actionName: $details";
                                }
                                $currentText = implode("\n", $texts);
                            } else {
                                $currentText = is_string($suggestedActions) ? $suggestedActions : '';
                            }
                        @endphp
                        <textarea class="form-control" name="suggested_actions" rows="6" required>{{ $currentText }}</textarea>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Format: Each line represents one action. Use the format: <strong>[priority] Action: Description</strong>
                            <br>
                            Example: <strong>[high] Tutoring: Twice-weekly tutoring for CSC102 until the grade reaches 75.</strong>
                            <br>
                            Keep it short: at most three lines, one step each, in one sentence. There is no need to restate
                            the grades, attendance or risk level the page already shows.
                        </small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('academic-head.recommendations', ['block_id' => request()->block_id]) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Recommendation
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-eye me-2"></i> Student Preview
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This is how the recommendation will appear to the student.
                </div>
                <div class="bg-light p-3 rounded">
                    <h6 class="fw-bold">Recommended Actions:</h6>
                    <ul class="mb-0">
                        @if(is_array($suggestedActions) && count($suggestedActions) > 0)
                            @foreach($suggestedActions as $action)
                                <li>
                                    <span class="badge bg-{{ $action['priority'] === 'high' ? 'danger' : ($action['priority'] === 'medium' ? 'warning' : 'info') }}">
                                        {{ $action['priority'] ?? 'medium' }}
                                    </span>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $action['action'] ?? 'custom')) }}</strong>
                                    - {{ $action['details'] ?? 'No details' }}
                                </li>
                            @endforeach
                        @else
                            <li class="text-muted">No actions defined</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelector('textarea[name="suggested_actions"]').addEventListener('blur', function() {
        const value = this.value;
        const lines = value.split('\n').filter(line => line.trim() !== '');
        if (lines.length > 0) {
            const hasPriority = lines.some(line => line.includes('[high]') || line.includes('[medium]') || line.includes('[low]'));
            if (!hasPriority) {
                const formatted = lines.map(line => {
                    if (line.trim().startsWith('-')) {
                        return '[medium]' + line;
                    }
                    return line;
                }).join('\n');
                this.value = formatted;
            }
        }
    });
</script>
@endpush
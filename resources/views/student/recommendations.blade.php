@extends('layouts.app')

@section('title', 'My Recommendations - AcadAlert')

@section('page_title', 'My Intervention Recommendations')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
@endsection

@section('content')
@php
    // Counted once, up here, so the opening strip and the three tiles below can
    // never disagree about how many recommendations are still pending.
    $completedCount = collect($recommendations)->where('is_completed', true)->count();
    $pendingCount   = count($recommendations) - $completedCount;
@endphp

<div class="ah-page sp-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

    <div class="sp-welcome ah-reveal" style="--ah-i: 0;">
        <span class="sp-welcome-icon"><i class="fas fa-lightbulb"></i></span>
        <div class="flex-grow-1">
            <h2 class="sp-welcome-title">{{ count($recommendations) }} recommendation(s)</h2>
            <p class="sp-welcome-sub">
                {{ $pendingCount }} pending completion
                &middot; {{ $completedCount }} completed
            </p>
        </div>
    </div>

    <div class="row ah-reveal" style="--ah-i: 1;">
        <div class="col-12 mb-4">
            <div class="card ah-glow">
                <div class="card-header">
                    <i class="fas fa-lightbulb text-primary"></i> Your Intervention Recommendations
                    <span class="badge bg-light text-primary ms-2">{{ count($recommendations) }}</span>
                </div>
                <div class="card-body">
                    @if(count($recommendations) > 0)
                    <div class="row mb-4 sp-metrics text-center">
                        <div class="col-md-4">
                            <div class="card bg-light sp-tile">
                                <div class="card-body text-center">
                                    <h3>{{ count($recommendations) }}</h3>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning sp-tile">
                                <div class="card-body text-center">
                                    <h3>{{ $pendingCount }}</h3>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success sp-tile">
                                <div class="card-body text-center">
                                    <h3>{{ $completedCount }}</h3>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group">
                        @foreach($recommendations as $recommendation)
                            @php
                                $actions = json_decode($recommendation->suggested_actions, true);
                                $riskFactors = json_decode($recommendation->risk_factors, true);
                            @endphp
                            <div class="list-group-item {{ $recommendation->is_completed ? 'list-group-item-success' : '' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                            <span class="badge bg-{{ $recommendation->is_completed ? 'success' : 'warning' }} me-2">
                                                {{ $recommendation->is_completed ? 'Completed' : 'Pending' }}
                                            </span>
                                            @if(!empty($recommendation->grading_period))
                                                <span class="badge bg-info text-dark me-2">{{ $recommendation->grading_period }}</span>
                                            @endif
                                            <small class="text-muted">
                                                Generated: {{ \Carbon\Carbon::parse($recommendation->generated_at)->format('M d, Y') }}
                                            </small>
                                        </div>

                                        @if($riskFactors)
                                            <div class="mb-2">
                                                <strong>Risk Factors:</strong>
                                                @foreach($riskFactors as $factor)
                                                    <span class="badge bg-secondary">{{ $factor }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if($actions)
                                            <div>
                                                <strong>Recommended Actions:</strong>
                                                <ul class="mb-0 mt-1">
                                                    @foreach($actions as $action)
                                                        <li>
                                                            <span class="badge bg-{{ $action['priority'] === 'high' ? 'danger' : ($action['priority'] === 'medium' ? 'warning' : 'info') }}">
                                                                {{ $action['priority'] }}
                                                            </span>
                                                            <strong>{{ ucfirst(str_replace('_', ' ', $action['action'])) }}</strong>
                                                            @if(isset($action['details']))
                                                                <span class="text-muted">- {{ $action['details'] }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ms-3">
                                        @if(!$recommendation->is_completed)
                                            <button class="btn btn-sm btn-success" onclick="markCompleted({{ $recommendation->id }}, this)">
                                                <i class="fas fa-check me-1"></i> Mark as Done
                                            </button>
                                        @else
                                            <span class="badge bg-success">Completed</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-lightbulb fa-3x d-block mb-3"></i>
                            <p class="mb-1">No intervention recommendations available.</p>
                            <small class="text-muted">Your academic head or counselor will generate recommendations based on your academic performance.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

<div id="recommendationToast" style="position: fixed; top: 80px; right: 20px; z-index: 9999; display: none;">
    <div class="toast align-items-center show" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close me-2 m-auto" onclick="document.getElementById('recommendationToast').style.display='none'"></button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function markCompleted(recommendationId, button) {
        const btn = button;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        fetch('/student/recommendation/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ recommendation_id: recommendationId }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const listItem = btn.closest('.list-group-item');
                listItem.classList.remove('list-group-item-warning');
                listItem.classList.add('list-group-item-success');
                btn.outerHTML = '<span class="badge bg-success">Completed</span>';
                
                const badge = listItem.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge bg-success me-2';
                    badge.textContent = 'Completed';
                }
                
                showToast('Recommendation marked as completed!');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast('' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Done';
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Done';
        });
    }
    
    function showToast(message) {
        const toast = document.getElementById('recommendationToast');
        document.getElementById('toastMessage').textContent = message;
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, 4000);
    }
</script>
@endpush
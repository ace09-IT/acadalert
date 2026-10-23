@extends('layouts.app')

@section('title', 'Risk Configuration - AcadAlert')

@section('page_title', 'Risk Configuration')
@section('page_actions')
    <div>
        <button class='btn btn-sm btn-info' onclick='testAIConnection()'>
            <img src='{{ asset('images/logo/acadalert_notxt.png') }}'
                 alt='' class='me-1' style='height:1em;width:auto;vertical-align:-0.15em;'>
            Test AI Connection
        </button>
        <a href='{{ route('admin.dashboard') }}' class='btn btn-sm btn-outline-secondary'>
            <i class='fas fa-arrow-left me-1'></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class='ah-page admin-page' style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class='row'>
    <div class='col-lg-7 mb-4'>
        <div class='card ah-glow ah-reveal' style='--ah-i: 0;'>
            <div class='card-header bg-primary text-white'>
                <i class='fas fa-sliders-h me-2'></i> Risk Threshold Parameters
            </div>
            <div class='card-body'>
                <form method='POST' action='{{ route('admin.risk.save') }}'>
                    @csrf
                    <div class='mb-3'>
                        <label class='form-label fw-bold'>
                            Low Risk: 0 to <span id='lowVal'>{{ $thresholds->low_threshold }}</span>
                        </label>
                        <input type='range' class='form-range' name='low_threshold' id='lowThreshold'
                               min='0' max='98' value='{{ $thresholds->low_threshold }}'
                               oninput='syncThresholds()'>
                        <small class='text-muted'>Scores at or below this value are Low risk.</small>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label fw-bold'>
                            Moderate Risk: <span id='modLowVal'>{{ $bands['Moderate'] }}</span>
                        </label>
                        <input type='range' class='form-range' name='moderate_threshold' id='moderateThreshold'
                               min='1' max='99' value='{{ $thresholds->moderate_threshold }}'
                               oninput='syncThresholds()'>
                        <small class='text-muted'>Scores above Low and up to this value are Moderate risk.</small>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label fw-bold'>
                            High Risk: <span id='highVal'>{{ $thresholds->high_threshold }}</span> to 100
                        </label>
                        <input type='number' class='form-control' name='high_threshold' id='highThreshold'
                               min='2' max='100' value='{{ $thresholds->high_threshold }}' readonly>
                        <small class='text-muted'>
                            Contiguity is enforced: the High band always begins exactly one point above the
                            Moderate ceiling, so every score from 0 to 100 is classified.
                        </small>
                    </div>
                    <hr>
                    <button type='submit' class='btn btn-primary w-100'>
                        <i class='fas fa-save me-1'></i> Save Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>    <div class='col-lg-5 mb-4'>
        <div class='card mb-4 ah-glow ah-reveal' style='--ah-i: 1;'>
            <div class='card-header bg-info text-white'>
                <i class='fas fa-info-circle me-2'></i> Current Settings
            </div>
            <div class='card-body'>
                <div class='row'>
                    <div class='col-6'>
                        <div class='bg-light p-2 rounded mb-2'>
                            <div class='text-muted small'>Low Risk Range</div>
                            <div class='fw-bold text-success'>{{ $effectiveBands['Low'] }}</div>
                        </div>
                    </div>
                    <div class='col-6'>
                        <div class='bg-light p-2 rounded mb-2'>
                            <div class='text-muted small'>Moderate Risk Range</div>
                            <div class='fw-bold text-warning'>{{ $effectiveBands['Moderate'] }}</div>
                        </div>
                    </div>
                    <div class='col-6'>
                        <div class='bg-light p-2 rounded mb-2'>
                            <div class='text-muted small'>High Risk Range</div>
                            <div class='fw-bold text-danger'>{{ $effectiveBands['High'] }}</div>
                        </div>
                    </div>
                    <div class='col-6'>
                        <div class='bg-light p-2 rounded mb-2'>
                            <div class='text-muted small'>Level Assignment</div>
                            <div class='fw-bold'>Deterministic (system)</div>
                        </div>
                    </div>
                </div>
                @if($configuredAt)
                    <div class='text-muted small mb-2'>
                        Last saved: {{ $configuredAt }}@if($configuredBy) by {{ $configuredBy }}@endif
                    </div>
                @endif
                @if($thresholds->high_threshold !== $thresholds->moderate_threshold + 1)
                    <div class='alert alert-warning'>
                        <i class='fas fa-exclamation-triangle me-1'></i>
                        <strong>Non-contiguous configuration:</strong> the High band starts at
                        {{ $thresholds->high_threshold }} while the Moderate ceiling is
                        {{ $thresholds->moderate_threshold }}, so scores
                        {{ $thresholds->moderate_threshold + 1 }} to {{ $thresholds->high_threshold - 1 }}
                        fall in an unclaimed gap and are classified Moderate (conservative rule).
                        Saving normalises the bands to {{ $thresholds->low_threshold }} /
                        {{ $thresholds->moderate_threshold }} / {{ $thresholds->moderate_threshold + 1 }}.
                    </div>
                @endif

                <div class='alert alert-info mt-3 mb-0'>
                    <strong>AI Connection Status:</strong>
                    <span id='aiStatus' class='badge bg-secondary'>Checking...</span>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
    const low = document.getElementById('lowThreshold');
    const moderate = document.getElementById('moderateThreshold');
    const high = document.getElementById('highThreshold');
    const lowVal = document.getElementById('lowVal');
    const modLowVal = document.getElementById('modLowVal');
    const highVal = document.getElementById('highVal');

    function syncThresholds() {
        const lowValue = parseInt(low.value, 10);
        moderate.min = lowValue + 1;

        if (parseInt(moderate.value, 10) <= lowValue) {
            moderate.value = lowValue + 1;
        }

        high.value = parseInt(moderate.value, 10) + 1;

        lowVal.textContent = lowValue;
        modLowVal.textContent = (lowValue + 1) + ' - ' + moderate.value;
        highVal.textContent = high.value;
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncThresholds();
        testAIConnection();
    });

    function testAIConnection() {
        const statusEl = document.getElementById('aiStatus');
        statusEl.className = 'badge bg-secondary';
        statusEl.textContent = 'Checking...';

        fetch('{{ route('admin.risk.test-ai') }}')
            .then(response => response.json())
            .then(data => {
                statusEl.textContent = data.success ? 'Connected' : data.message;
                statusEl.className = data.success ? 'badge bg-success' : 'badge bg-danger';
            })
            .catch(() => {
                statusEl.textContent = 'Connection failed';
                statusEl.className = 'badge bg-danger';
            });
    }
</script>
@endpush
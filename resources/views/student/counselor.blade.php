@extends('layouts.app')

@section('title', 'Contact Counselor - AcadAlert')

@section('page_title', 'Contact Your Counselor')
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
<div class="ah-page sp-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

    <div class="sp-welcome ah-reveal" style="--ah-i: 0;">
        <span class="sp-welcome-icon"><i class="fas fa-user-circle"></i></span>
        <div class="flex-grow-1">
            <h2 class="sp-welcome-title">{{ $counselorInfo->name ?? 'Your Guidance Counselor' }}</h2>
            <p class="sp-welcome-sub">
                @if($counselorInfo)
                    Your guidance counselor &middot; {{ $counselorInfo->office_hours ?? 'Mon-Fri, 9:00 AM - 4:00 PM' }}
                @else
                    No counselor has been assigned to your department yet
                @endif
            </p>
        </div>
    </div>

    <div class="row ah-reveal" style="--ah-i: 1;">
    @if($counselorInfo)
    <div class="col-lg-6 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-user-circle text-primary"></i> Your Counselor
            </div>
            <div class="card-body text-center py-4">
                <div class="display-1 mb-3">
                    <i class="fas fa-user-circle text-primary"></i>
                </div>
                <h4 class="fw-bold">{{ $counselorInfo->name }}</h4>
                <p class="text-muted">Guidance Counselor</p>
                <hr>
                <div class="row text-start">
                    <div class="col-12 mb-2">
                        <i class="fas fa-map-pin text-primary me-2" style="width: 20px;"></i>
                        <strong>Office:</strong> 
                        {{ $counselorInfo->office_location ?? 'Guidance Office, 2nd Floor, Main Building' }}
                    </div>
                    <div class="col-12 mb-2">
                        <i class="fas fa-phone text-primary me-2" style="width: 20px;"></i>
                        <strong>Phone:</strong> 
                        {{ $counselorInfo->phone_number ?? '(075) 123-4567 loc. 123' }}
                    </div>
                    <div class="col-12 mb-2">
                        <i class="fas fa-envelope text-primary me-2" style="width: 20px;"></i>
                        <strong>Email:</strong> 
                        <a href="mailto:{{ $counselorInfo->email }}">{{ $counselorInfo->email }}</a>
                    </div>
                    <div class="col-12 mb-2">
                        <i class="fas fa-clock text-primary me-2" style="width: 20px;"></i>
                        <strong>Office Hours:</strong> 
                        {{ $counselorInfo->office_hours ?? 'Mon-Fri, 9:00 AM - 4:00 PM' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card ah-glow">
            <div class="card-header">
                <i class="fas fa-calendar-plus text-primary"></i> Schedule Appointment
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="fas fa-handshake fa-3x text-success"></i>
                </div>
                <p class="text-center">Need to talk to your counselor? Schedule an appointment below.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Current Risk Level:</strong> 
                    @if($currentRisk)
                        <span class="badge {{ $currentRisk->risk_level === 'High' ? 'badge-risk-high' : ($currentRisk->risk_level === 'Moderate' ? 'badge-risk-moderate' : 'badge-risk-low') }}">
                            {{ $currentRisk->risk_level ?? 'Low' }}
                        </span>
                    @else
                        <span class="badge badge-risk-low">Low</span>
                    @endif
                </div>
                
                <button class="btn btn-primary w-100 py-2" onclick="scheduleAppointment()">
                    <i class="fas fa-calendar-plus me-2"></i> Schedule Appointment
                </button>
            </div>
        </div>
        
        <div class="card mt-4 ah-glow">
            <div class="card-header">
                <i class="fas fa-lightbulb text-primary"></i> Quick Resources
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-phone me-2 text-primary"></i> Emergency Contact: (075) 123-4567
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-pdf me-2 text-danger"></i> Download Counseling Form
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-external-link-alt me-2 text-success"></i> Mental Health Resources
                    </a>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="col-12">
        <div class="card ah-glow">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-circle fa-5x text-muted d-block mb-3"></i>
                <h4>No Counselor Assigned</h4>
                <p class="text-muted">A counselor has not been assigned to your department yet.</p>
                <p class="text-muted small">Please contact the Guidance Office directly for assistance.</p>
                <div class="mt-3">
                    <p><i class="fas fa-map-pin me-2"></i> Guidance Office, 2nd Floor, Main Building</p>
                    <p><i class="fas fa-phone me-2"></i> (075) 123-4567 loc. 123</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Fri, 9:00 AM - 4:00 PM</p>
                </div>
            </div>
        </div>
    </div>
    @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    function scheduleAppointment() {
        alert('Appointment scheduling will be available soon!\n\nPlease visit the Guidance Office in person or call (075) 123-4567 loc. 123 to schedule an appointment.');
    }
</script>
@endpush
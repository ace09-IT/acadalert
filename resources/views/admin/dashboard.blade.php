@extends('layouts.app')

@section('title', 'Admin Dashboard - AcadAlert')

@section('page_title', 'System Dashboard')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary ah-reveal" style="--ah-i: 0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-number">{{ $stats['total_students'] ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger ah-reveal" style="--ah-i: 1;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk Students</div>
                    <div class="stat-number">{{ $stats['high_risk'] ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card purple ah-reveal" style="--ah-i: 2;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Critical Cases</div>
                    <div class="stat-number">{{ $stats['critical_cases'] ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success ah-reveal" style="--ah-i: 3;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Collection Rate</div>
                    <div class="stat-number">
                        @php
                            $total = $stats['total_payments'] ?? 0;
                            $collected = $stats['collected_payments'] ?? 0;
                            $rate = $total > 0 ? round(($collected / $total) * 100, 1) : 0;
                        @endphp
                        {{ $rate }}%
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 4;">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary"></i>
                Risk by Department
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="riskByDeptChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 5;">
            <div class="card-header">
                <i class="fas fa-chart-pie text-primary"></i>
                Risk Distribution
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="riskDistributionChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge badge-risk-low me-2">Low: {{ $riskDistribution['Low'] ?? 0 }}</span>
                    <span class="badge badge-risk-moderate me-2">Moderate: {{ $riskDistribution['Moderate'] ?? 0 }}</span>
                    <span class="badge badge-risk-high">High: {{ $riskDistribution['High'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 6;">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i>
                Institution Risk Trend
                <span class="admin-trend-tag">High-Risk Share</span>
            </div>
            <div class="card-body">
                <div class="chart-container admin-trend-stage">
                    <canvas id="riskTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 7;">
            <div class="card-header">
                <i class="fas fa-history text-primary"></i>
                Recent Activities
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivities as $activity)
                            <tr>
                                <td>{{ $activity->user_name }}</td>
                                <td><span class="badge bg-secondary">{{ $activity->action }}</span></td>
                                <td>{{ $activity->created_at instanceof \Carbon\Carbon ? $activity->created_at->format('M d, Y H:i:s') : \Carbon\Carbon::parse($activity->created_at)->format('M d, Y H:i:s') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No recent activities.</td>
                            </tr>
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
        <div class="card ah-glow ah-reveal" style="--ah-i: 8;">
            <div class="card-header">
                <i class="fas fa-server text-primary"></i>
                System Health
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($systemHealth as $key => $item)
                    <div class="col-md-2 col-6 text-center py-2">
                        <div class="{{ $item['status'] === 'ok' ? 'text-success' : ($item['status'] === 'warning' ? 'text-warning' : 'text-danger') }}">
                            <i class="fas {{ $item['status'] === 'ok' ? 'fa-check-circle' : ($item['status'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') }} fa-2x"></i>
                        </div>
                        <div class="small text-muted mt-1">{{ ucfirst($key) }}</div>
                        <div class="fw-bold small">{{ $item['message'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/admin-charts.js') }}"></script>

<script>
    console.log('[Admin Dashboard] Chart scripts loaded.');
    console.log('[Admin Dashboard] Charts will be initialized by admin-charts.js');
    
    // If charts don't load, you can manually trigger loading with:
    // window.loadAdminCharts();
</script>
@endpush
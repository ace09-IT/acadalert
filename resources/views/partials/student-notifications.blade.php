@php
    $bellAlerts = $studentBellAlerts ?? collect();
    $bellUnread = (int) ($studentBellUnread ?? 0);
@endphp
<li class="nav-item dropdown student-bell">
    <a class="nav-link student-bell-toggle" href="#" id="studentBellToggle" role="button"
       data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
       title="Alerts &amp; flags"
       aria-label="Alerts and flags{{ $bellUnread > 0 ? ', ' . $bellUnread . ' unread' : '' }}">
        <i class="fas fa-bell" aria-hidden="true"></i>
        @if($bellUnread > 0)
            <span class="student-bell-count">{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
        @endif
    </a>

    <div class="dropdown-menu dropdown-menu-end student-bell-menu" aria-labelledby="studentBellToggle">
        <div class="student-bell-head">
            <span class="student-bell-head-title">
                <i class="fas fa-bell" aria-hidden="true"></i> Alerts &amp; Flags
            </span>
            @if($bellUnread > 0)
                <span class="badge bg-danger">{{ $bellUnread }} unread</span>
            @else
                <span class="badge bg-success">All read</span>
            @endif
        </div>

        <div class="student-bell-body">
            @if($bellAlerts->count() > 0)
                @foreach($bellAlerts as $alert)
                    @php
                        $alertType = match ($alert->severity) {
                            'critical' => 'danger',
                            'high' => 'warning',
                            'medium' => 'info',
                            'low' => 'secondary',
                            'success' => 'success',
                            default => 'info',
                        };
                        $isCounselorAlert = in_array($alert->flag_type, [
                            'counselor_update', 'counselor_action', 'status_update',
                            'priority_update', 'case_resolved', 'case_reopened',
                        ]);
                    @endphp
                    <div class="alert alert-permanent alert-{{ $alertType }} d-flex justify-content-between align-items-center mb-2"
                         id="alert-{{ $alert->id }}">
                        <div>
                            @if(!$alert->is_acknowledged)
                                <span class="badge bg-danger me-2">NEW</span>
                            @endif
                            @if($isCounselorAlert)
                                <i class="fas fa-headset me-2 text-primary"></i>
                                <span class="fw-bold">[Counselor]</span>
                            @endif
                            {{ $alert->message ?? $alert->flag_type }}
                            @if($alert->consecutive_periods_count > 1)
                                <span class="badge bg-danger ms-2">x{{ $alert->consecutive_periods_count }} consecutive</span>
                            @endif
                            <br>
                            <small class="text-muted">{{ $alert->grading_period }} {{ $alert->school_year }}</small>
                            @if($alert->created_at)
                                <small class="text-muted ms-2">
                                    | {{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}
                                </small>
                            @endif
                        </div>
                        <div class="alert-actions ms-2">
                            @if(!$alert->is_acknowledged)
                                <button class="btn btn-sm btn-outline-primary acknowledge-btn"
                                        data-alert-id="{{ $alert->id }}"
                                        onclick="acknowledgeAlert({{ $alert->id }}, this)">
                                    <i class="fas fa-check me-1"></i> Acknowledge
                                </button>
                            @else
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i> Acknowledged
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-3x d-block mb-3 text-success opacity-50"></i>
                    <p class="mb-0">No alerts at this time.</p>
                    <small>You will be alerted here when a flag is raised on your record.</small>
                </div>
            @endif
        </div>
    </div>
</li>
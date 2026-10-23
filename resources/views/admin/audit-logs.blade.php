@extends('layouts.app')

@section('title', 'Audit Logs - AcadAlert')

@section('page_title', 'Audit Logs')
@section('page_actions')
    <div>
        <button type="button" class="btn btn-sm btn-success" id="exportLogsBtn" onclick="exportAuditLogs()">
            <i class="fas fa-file-export me-1"></i> Export Logs
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus03.webp') }}')">

<div class="row">
    <div class="col-12 mb-3">
        <div class="card ah-glow ah-reveal" style="--ah-i: 0;">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Date From</label>
                        <input type="date" class="form-control form-control-sm" name="date_from" 
                               value="{{ $dateFrom ?? now()->subDays(30)->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Date To</label>
                        <input type="date" class="form-control form-control-sm" name="date_to" 
                               value="{{ $dateTo ?? now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">User</label>
                        <select class="form-select form-select-sm" name="user">
                            <option value="all">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Action</label>
                        <select class="form-select form-select-sm" name="action">
                            <option value="all">All Actions</option>
                            @foreach($actions as $action)
                                <option value="{{ $action }}">{{ $action }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter me-1"></i> Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 1;">
            <div class="card-header">
                <i class="fas fa-history text-primary me-2"></i>
                Activity Logs
                <span class="badge bg-secondary ms-2">{{ $logs->total() }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                                <td>{{ $log->user_name }}</td>
                                <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                                <td>{{ $log->ip_address }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No logs found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
function exportAuditLogs() {
    const exportBtn = document.getElementById('exportLogsBtn');
    const originalText = exportBtn ? exportBtn.innerHTML : '';
    const exportUrl = {!! json_encode(route('admin.audit.logs.export', request()->query()), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Exporting...';
    }

    function restore() {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.innerHTML = originalText;
        }
    }

    fetch(exportUrl + (exportUrl.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now(), {
        method: 'GET',
        headers: {
            'Accept': 'text/csv, application/json;q=0.9, */*;q=0.8',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
    })
    .then(function (response) {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }

        // A redirect or an error page would otherwise be saved as a .csv that opens as
        // a login screen. Fail loudly instead of writing a junk file.
        const contentType = response.headers.get('content-type') || '';
        if (contentType.indexOf('text/html') !== -1) {
            throw new Error('received HTML instead of CSV');
        }

        return response.blob();
    })
    .then(function (blob) {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = 'audit_logs_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        restore();
        showToast('Audit logs exported successfully.', 'success');
    })
    .catch(function (error) {
        console.error('[AuditExport] Export failed:', error);
        restore();
        showToast('Failed to export logs: ' + error.message, 'error');
    });
}
</script>
@endpush
@extends('layouts.app')

@section('title', 'Payment Monitoring - AcadAlert')

@section('page_title', 'Payment Monitoring')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-success" id="exportReportBtn" onclick="exportPayments()">
            <i class="fas fa-file-export me-1"></i> Export Report
        </button>
        <button class="btn btn-sm btn-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="ah-page admin-page" style="--ah-photo: url('{{ asset('images/backgrounds/maincampus02.webp') }}')">

<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card primary ah-reveal" style="--ah-i: 0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Due</div>
                    <div class="stat-number">₱{{ number_format($stats['total_amount'] ?? 0, 2) }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card success ah-reveal" style="--ah-i: 1;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Collected</div>
                    <div class="stat-number">₱{{ number_format($stats['collected_amount'] ?? 0, 2) }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card danger ah-reveal" style="--ah-i: 2;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-number">₱{{ number_format($stats['overdue_amount'] ?? 0, 2) }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card info ah-reveal" style="--ah-i: 3;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Collection Rate</div>
                    <div class="stat-number">{{ $stats['collection_rate'] ?? 0 }}%</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 col-6 mb-3">
        <div class="card bg-light ah-reveal" style="--ah-i: 4;">
            <div class="card-body text-center">
                <h5 class="text-muted small mb-1">Total Students</h5>
                <h3>{{ $stats['total_count'] ?? 0 }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card bg-success text-white ah-reveal" style="--ah-i: 5;">
            <div class="card-body text-center">
                <h5 class="text-white-50 small mb-1">Paid</h5>
                <h3>{{ $stats['paid_count'] ?? 0 }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card bg-warning text-dark ah-reveal" style="--ah-i: 6;">
            <div class="card-body text-center">
                <h5 class="text-dark-50 small mb-1">Partial</h5>
                <h3>{{ $stats['partial_count'] ?? 0 }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card bg-danger text-white ah-reveal" style="--ah-i: 7;">
            <div class="card-body text-center">
                <h5 class="text-white-50 small mb-1">Overdue</h5>
                <h3>{{ $stats['overdue_count'] ?? 0 }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-3">
        <div class="card ah-glow ah-reveal" style="--ah-i: 8;">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="all" {{ $statusFilter == 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="Paid" {{ $statusFilter == 'Paid' ? 'selected' : '' }}>Paid</option>
                            <option value="Unpaid" {{ $statusFilter == 'Unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="Overdue" {{ $statusFilter == 'Overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="Partial" {{ $statusFilter == 'Partial' ? 'selected' : '' }}>Partial</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Department</label>
                        <select class="form-select form-select-sm" name="department">
                            <option value="all" {{ $departmentFilter == 'all' ? 'selected' : '' }}>All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->code }}" {{ $departmentFilter == $dept->code ? 'selected' : '' }}>
                                    {{ $dept->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Search</label>
                        <input type="text" class="form-control form-control-sm" name="search" 
                               placeholder="Student name, number, or email..." 
                               value="{{ $searchFilter }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
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
    <div class="col-12 mb-3">
        <div class="card ah-glow ah-reveal" style="--ah-i: 9;">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary me-2"></i>
                Payment Trend (Last 6 Months)
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="paymentTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 10;">
            <div class="card-header">
                <i class="fas fa-credit-card text-primary me-2"></i>
                Student Payments
                <span class="badge bg-secondary ms-2">{{ $payments->total() }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Program</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                            <tr>
                                <td>
                                    <strong>{{ $payment->first_name }} {{ $payment->last_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $payment->student_email }}</small>
                                </td>
                                <td><code>{{ $payment->student_number }}</code></td>
                                <td>
                                    <span class="badge bg-secondary">{{ $payment->program_code }}</span>
                                    <br>
                                    <small class="text-muted">{{ $payment->department_code }}</small>
                                </td>
                                <td>₱{{ number_format($payment->amount, 2) }}</td>
                                <td>₱{{ number_format($payment->paid_amount, 2) }}</td>
                                <td>
                                    <span class="{{ $payment->balance > 0 ? 'text-danger fw-bold' : 'text-success' }}">
                                        ₱{{ number_format($payment->balance, 2) }}
                                    </span>
                                </td>
                                <td>
                                    @if(\Carbon\Carbon::parse($payment->due_date)->isPast() && $payment->balance > 0)
                                        <span class="text-danger fw-bold">
                                            {{ date('M d, Y', strtotime($payment->due_date)) }}
                                            <span class="badge bg-danger ms-1">OVERDUE</span>
                                        </span>
                                    @else
                                        {{ date('M d, Y', strtotime($payment->due_date)) }}
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'Paid' => 'success',
                                            'Unpaid' => 'warning',
                                            'Overdue' => 'danger',
                                            'Partial' => 'info'
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$payment->status] ?? 'secondary' }}">
                                        {{ $payment->status }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewPayment({{ $payment->id }})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="recordPayment({{ $payment->id }})">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-credit-card fa-2x d-block mb-2 opacity-50"></i>
                                    <p>No payments found matching the filters.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $payments->links() }}
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-danger ah-glow ah-reveal" style="--ah-i: 11;">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Overdue Students
                <span class="badge bg-light text-danger ms-2">{{ $overdueSummary->count() }}</span>
            </div>
            <div class="card-body">
                @if($overdueSummary->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-danger">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student Number</th>
                                    <th>Department</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overdueSummary as $overdue)
                                <tr>
                                    <td>{{ $overdue->first_name }} {{ $overdue->last_name }}</td>
                                    <td>{{ $overdue->student_number }}</td>
                                    <td>{{ $overdue->department_code }}</td>
                                    <td class="text-danger fw-bold">₱{{ number_format($overdue->balance, 2) }}</td>
                                    <td>{{ date('M d, Y', strtotime($overdue->due_date)) }}</td>
                                    <td>
                                        <span class="badge bg-danger">{{ $overdue->days_overdue ?? 0 }} days</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        No overdue students found.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card ah-glow ah-reveal" style="--ah-i: 12;">
            <div class="card-header">
                <i class="fas fa-building text-primary me-2"></i>
                Department Payment Summary
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Students</th>
                                <th>Total Amount</th>
                                <th>Collected</th>
                                <th>Outstanding</th>
                                <th>Paid</th>
                                <th>Partial</th>
                                <th>Unpaid</th>
                                <th>Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departmentSummary as $dept)
                            <tr>
                                <td><strong>{{ $dept->department }}</strong></td>
                                <td>{{ $dept->total_students }}</td>
                                <td>₱{{ number_format($dept->total_amount, 2) }}</td>
                                <td>₱{{ number_format($dept->collected_amount, 2) }}</td>
                                <td>₱{{ number_format($dept->outstanding_amount, 2) }}</td>
                                <td>{{ $dept->paid_count }}</td>
                                <td>{{ $dept->partial_count }}</td>
                                <td>{{ $dept->unpaid_count }}</td>
                                <td class="text-danger">{{ $dept->overdue_count }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="viewPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card me-2"></i> Payment Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewPaymentContent">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i> Record Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="recordPaymentForm">
                <div class="modal-body">
                    <div class="alert alert-info" id="paymentInfo">
                        <i class="fas fa-info-circle me-2"></i>
                        Student: <span id="paymentStudentName">Loading...</span>
                        <br>
                        Balance: <span id="paymentBalance">Loading...</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount</label>
                        <input type="number" class="form-control" name="amount" id="paymentAmount" 
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" 
                               id="paymentDate" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reference Number</label>
                        <input type="text" class="form-control" name="reference" 
                               id="paymentReference" placeholder="OR-XXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" name="notes" id="paymentNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>

document.addEventListener('DOMContentLoaded', function() {
    const paymentTrend = @json($paymentTrend);
    const canvas = document.getElementById('paymentTrendChart');
    
    if (!canvas) {
        console.warn('Payment trend chart canvas not found');
        return;
    }
    
    if (!paymentTrend || !paymentTrend.months || paymentTrend.months.length === 0) {
        const parent = canvas.parentElement;
        parent.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-chart-line fa-2x d-block mb-2 opacity-50"></i>
                <p class="mb-0">No payment data available for the selected period.</p>
            </div>
        `;
        return;
    }

    const ctx = canvas.getContext('2d');
    
    const formatCurrency = (value) => {
        return '₱' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    const hasCollectedData = paymentTrend.collected.some(v => v > 0);
    const hasOverdueData = paymentTrend.overdue.some(v => v > 0);

    // If no data, show fallback
    if (!hasCollectedData && !hasOverdueData) {
        const parent = canvas.parentElement;
        parent.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-credit-card fa-2x d-block mb-2 opacity-50"></i>
                <p class="mb-0">No payment records found. Start recording payments to see trends.</p>
            </div>
        `;
        return;
    }

    const maxValues = [...paymentTrend.collected, ...paymentTrend.overdue];
    const maxValue = Math.max(...maxValues, 0);
    const yAxisMax = Math.ceil(maxValue * 1.2 / 1000000) * 1000000 || 1000000;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: paymentTrend.months,
            datasets: [
                {
                    label: 'Collected',
                    data: paymentTrend.collected,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: '#28a745',
                    borderWidth: 2,
                    borderRadius: 4,
                    order: 1,
                },
                {
                    label: 'Overdue',
                    data: paymentTrend.overdue,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 2,
                    borderRadius: 4,
                    order: 2,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '500',
                            family: "'Inter', sans-serif",
                        },
                        boxWidth: 12,
                        boxHeight: 12,
                    },
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1a1a2e',
                    bodyColor: '#333',
                    borderColor: 'rgba(0,0,0,0.08)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            return label + ': ' + formatCurrency(value);
                        },
                        afterBody: function(tooltipItems) {
                            let total = 0;
                            tooltipItems.forEach(item => {
                                total += item.parsed.y || 0;
                            });
                            return 'Total: ' + formatCurrency(total);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500',
                            family: "'Inter', sans-serif",
                        },
                        color: '#6c757d',
                    },
                },
                y: {
                    min: 0,
                    max: yAxisMax,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false,
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return '₱' + (value / 1000000).toFixed(1) + 'M';
                            } else if (value >= 1000) {
                                return '₱' + (value / 1000).toFixed(0) + 'K';
                            }
                            return '₱' + value.toFixed(0);
                        },
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif",
                        },
                        color: '#6c757d',
                        maxTicksLimit: 8,
                    },
                    title: {
                        display: true,
                        text: 'Amount (₱)',
                        font: {
                            size: 12,
                            weight: '500',
                            family: "'Inter', sans-serif",
                        },
                        color: '#6c757d',
                    },
                }
            },
            grouped: true,
            animation: {
                duration: 800,
                easing: 'easeInOutQuart',
            },
        },
        plugins: [{
            id: 'customTooltip',
            beforeDraw: function(chart) {
            }
        }]
    });
});
    
    function viewPayment(paymentId) {
    const modal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));
    modal.show();
    
    const content = document.getElementById('viewPaymentContent');
    content.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading payment details...</p>
        </div>
    `;
    
    fetch(`/admin/payments/${paymentId}/view`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payment = data.payment;
            // Ensure numeric values are treated as numbers
            const amount = parseFloat(payment.amount) || 0;
            const paidAmount = parseFloat(payment.paid_amount) || 0;
            const balance = parseFloat(payment.balance) || 0;
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Student</label>
                            <p class="fw-bold">${payment.first_name} ${payment.last_name}</p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Student Number</label>
                            <p>${payment.student_number}</p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Program</label>
                            <p>${payment.program_code}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Total Amount</label>
                            <p class="fw-bold">₱${amount.toFixed(2)}</p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Paid Amount</label>
                            <p>₱${paidAmount.toFixed(2)}</p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Balance</label>
                            <p class="text-danger fw-bold">₱${balance.toFixed(2)}</p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Status</label>
                            <p><span class="badge bg-${payment.status === 'Paid' ? 'success' : (payment.status === 'Overdue' ? 'danger' : 'warning')}">${payment.status}</span></p>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted small fw-bold">Due Date</label>
                            <p>${new Date(payment.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                        </div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load payment details'}</div>`;
        }
    })
    .catch(error => {
        content.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
    });
}
    
 function recordPayment(paymentId) {
    if (!paymentId || paymentId === 'undefined' || isNaN(paymentId) || parseInt(paymentId) <= 0) {
        alert('Error: Invalid payment ID. Please refresh the page and try again.');
        return;
    }
    
    const id = parseInt(paymentId, 10);
    const modal = new bootstrap.Modal(document.getElementById('recordPaymentModal'));
    modal.show();
    
    const form = document.getElementById('recordPaymentForm');
    form.dataset.paymentId = id;
    
    document.getElementById('paymentStudentName').textContent = 'Loading...';
    document.getElementById('paymentBalance').textContent = 'Loading...';
    
    fetch(`/admin/payments/${id}/info`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const balance = parseFloat(data.balance) || 0;
            document.getElementById('paymentStudentName').textContent = data.student_name || 'Unknown Student';
            document.getElementById('paymentBalance').textContent = '₱' + balance.toFixed(2);
            document.getElementById('paymentAmount').max = balance;
            document.getElementById('paymentAmount').placeholder = 'Enter amount (max ₱' + balance.toFixed(2) + ')';
            document.getElementById('paymentAmount').value = '';
        } else {
            let errorMsg = 'Failed to load payment info: ' + (data.message || 'Unknown error');
            if (data.debug) {
                errorMsg += '\n\nDebug Info:\n' + JSON.stringify(data.debug, null, 2);
            }
            alert(errorMsg);
            modal.hide();
        }
    })
    .catch(error => {
        alert('Error loading payment details: ' + error.message + '\n\nPlease check the console for more details.');
        console.error('Payment info fetch error:', error);
        modal.hide();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recordPaymentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const paymentId = this.dataset.paymentId;
            if (!paymentId) {
                alert('Error: Payment ID not found. Please close and try again.');
                return;
            }
            
            const amount = document.getElementById('paymentAmount').value;
            const paymentDate = document.getElementById('paymentDate').value;
            const reference = document.getElementById('paymentReference').value;
            const notes = document.getElementById('paymentNotes').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid amount.');
                return;
            }
            if (!paymentDate) {
                alert('Please select a payment date.');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
            
            fetch(`/admin/payments/${paymentId}/record`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    amount: amount,
                    payment_date: paymentDate,
                    reference: reference || null,
                    notes: notes || null,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Payment recorded successfully!\n\n' + 
                          'New Balance: ₱' + parseFloat(data.new_balance).toFixed(2) + '\n' +
                          'New Status: ' + data.new_status);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'));
                    if (modal) modal.hide();
                    window.location.reload();
                } else {
                    let errorMsg = '❌ Failed to record payment: ' + (data.message || 'Unknown error');
                    if (data.debug) {
                        errorMsg += '\n\nDebug Info:\n' + JSON.stringify(data.debug, null, 2);
                    }
                    alert(errorMsg);
                    console.error('Record payment error:', data);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message + '\n\nPlease check the console for more details.');
                console.error('Record payment fetch error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Record Payment';
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recordPaymentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const paymentId = this.dataset.paymentId;
            
            if (!paymentId) {
                alert('Error: Payment ID not found. Please close and try again.');
                return;
            }
            
            const amount = document.getElementById('paymentAmount').value;
            const paymentDate = document.getElementById('paymentDate').value;
            const reference = document.getElementById('paymentReference').value;
            const notes = document.getElementById('paymentNotes').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid amount.');
                return;
            }
            
            if (!paymentDate) {
                alert('Please select a payment date.');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
            
            fetch(`/admin/payments/${paymentId}/record`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    amount: amount,
                    payment_date: paymentDate,
                    reference: reference || null,
                    notes: notes || null,
                }),
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('✅ Payment recorded successfully!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'));
                    if (modal) modal.hide();
                    window.location.reload();
                } else {
                    alert('❌ Failed to record payment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error recording payment:', error);
                alert('❌ Error: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Record Payment';
            });
        });
    }
});


function exportPayments() {
    console.log('[Export] exportPayments() called at', new Date().toISOString());
    console.log('[Export] Current URL:', window.location.href);
    
    let debugLog = {
        function: 'exportPayments',
        timestamp: new Date().toISOString(),
        steps: [],
        status: 'started'
    };
    
    try {
        const statusFilter = document.querySelector('select[name="status"]')?.value || 'all';
        const departmentFilter = document.querySelector('select[name="department"]')?.value || 'all';
        const searchFilter = document.querySelector('input[name="search"]')?.value || '';
        
        debugLog.steps.push({
            step: 'filters_collected',
            statusFilter: statusFilter,
            departmentFilter: departmentFilter,
            searchFilter: searchFilter,
            timestamp: new Date().toISOString()
        });
        console.log('[Export] Filters collected:', { statusFilter, departmentFilter, searchFilter });
        
        if (!statusFilter || !departmentFilter) {
            debugLog.steps.push({
                step: 'filter_error',
                message: 'Required filter elements not found',
                status: 'warning'
            });
            console.warn('[Export] Filter elements not found. Using defaults.');
        }
        
        const exportUrl = new URL('/admin/payments/export', window.location.origin);
        exportUrl.searchParams.append('status', statusFilter);
        exportUrl.searchParams.append('department', departmentFilter);
        exportUrl.searchParams.append('search', searchFilter);
        exportUrl.searchParams.append('format', 'csv');
        exportUrl.searchParams.append('_t', Date.now());
        
        debugLog.steps.push({
            step: 'url_constructed',
            url: exportUrl.toString(),
            timestamp: new Date().toISOString()
        });
        console.log('[Export] Export URL:', exportUrl.toString());
        
        const exportBtn = document.getElementById('exportReportBtn');
        if (exportBtn) {
            const originalText = exportBtn.innerHTML;
            exportBtn.dataset.originalText = originalText;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
            
            debugLog.steps.push({
                step: 'button_loading',
                originalText: originalText,
                timestamp: new Date().toISOString()
            });
            console.log('[Export] Button loading state set');
        }
        
        debugLog.steps.push({
            step: 'fetch_started',
            timestamp: new Date().toISOString()
        });
        console.log('[Export] Starting fetch request...');
        
        fetch(exportUrl.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json, text/plain, */*',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        })
        .then(response => {
            debugLog.steps.push({
                step: 'fetch_response',
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries()),
                timestamp: new Date().toISOString()
            });
            console.log('[Export] Response received:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('text/html')) {
                debugLog.steps.push({
                    step: 'html_response',
                    message: 'Received HTML instead of CSV',
                    contentType: contentType,
                    status: 'error'
                });
                throw new Error('Received HTML response. The export endpoint may not be configured correctly.');
            }
            
            return response.blob();
        })
        .then(blob => {
            debugLog.steps.push({
                step: 'blob_received',
                size: blob.size,
                type: blob.type,
                timestamp: new Date().toISOString()
            });
            console.log('[Export] Blob received:', { size: blob.size, type: blob.type });
            
            const link = document.createElement('a');
            const url = window.URL.createObjectURL(blob);
            link.href = url;
            link.download = `payment_report_${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            debugLog.steps.push({
                step: 'download_triggered',
                filename: link.download,
                timestamp: new Date().toISOString()
            });
            console.log('[Export] Download triggered:', link.download);
            
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.innerHTML = exportBtn.dataset.originalText || '<i class="fas fa-file-export me-1"></i> Export Report';
            }
            
            debugLog.status = 'completed';
            debugLog.steps.push({
                step: 'export_complete',
                status: 'success',
                timestamp: new Date().toISOString()
            });
            console.log('[Export] Export completed successfully');
            console.log('[Export] Full debug log:', debugLog);
            
            showExportToast('✅ Payment report exported successfully!', 'success');
        })
        .catch(error => {
            debugLog.steps.push({
                step: 'fetch_error',
                error: {
                    message: error.message,
                    stack: error.stack,
                    name: error.name
                },
                timestamp: new Date().toISOString()
            });
            console.error('[Export] Error during export:', error);
            
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.innerHTML = exportBtn.dataset.originalText || '<i class="fas fa-file-export me-1"></i> Export Report';
            }
            
            debugLog.status = 'failed';
            console.log('[Export] Full debug log (error):', debugLog);
            
            let errorMsg = '❌ Failed to export report: ' + error.message;
            errorMsg += '\n\n🔍 Debug Info:\n' + JSON.stringify(debugLog, null, 2);
            alert(errorMsg);
            
            showExportToast('❌ Failed to export report: ' + error.message, 'error');
        });
        
    } catch (error) {
        debugLog.steps.push({
            step: 'uncaught_error',
            error: {
                message: error.message,
                stack: error.stack,
                name: error.name
            },
            timestamp: new Date().toISOString()
        });
        console.error('[Export] Uncaught error:', error);
        debugLog.status = 'failed_critical';
        console.log('[Export] Full debug log (critical):', debugLog);
        
        alert('❌ Critical error in export function:\n\n' + 
              'Error: ' + error.message + '\n\n' +
              'Please check the console for detailed debugging information.');
    }
}


function showExportToast(message, type = 'success') {
    const colors = {
        success: 'bg-success text-white',
        error: 'bg-danger text-white',
        warning: 'bg-warning text-dark',
        info: 'bg-info text-white'
    };
    
    document.querySelectorAll('.export-toast').forEach(el => el.remove());
    
    const toast = document.createElement('div');
    toast.className = `export-toast toast align-items-center ${colors[type] || colors.info} border-0 show`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.style.position = 'fixed';
    toast.style.top = '80px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.style.maxWidth = '450px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.borderRadius = '8px';
    toast.style.padding = '12px 16px';
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white ms-auto" onclick="this.closest('.toast').remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
</script>
@endpush
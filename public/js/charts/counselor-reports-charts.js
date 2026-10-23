
(function () {
    'use strict';

    var RISK_COLORS = {
        high: '#dc3545',
        moderate: '#ffc107',
        low: '#28a745'
    };

    var ACCENT = '#4e73df';

    function payload(canvasId) {
        var canvas = document.getElementById(canvasId);

        if (!canvas) {
            return null;
        }

        var raw = canvas.getAttribute('data-chart');

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            console.error('[Counselor Reports] Could not parse data-chart for "' + canvasId + '":', error);
            return null;
        }
    }

    function fallback(canvasId, message) {
        var canvas = document.getElementById(canvasId);
        var parent = canvas ? canvas.parentElement : null;

        if (!parent) {
            return;
        }

        parent.innerHTML = '' +
            '<div class="text-center text-muted py-4">' +
            '<i class="fas fa-chart-simple fa-2x d-block mb-2 opacity-50"></i>' +
            '<p class="mb-0">' + message + '</p>' +
            '</div>';
    }

    function allZero(rows) {
        return !rows || !rows.length || rows.every(function (row) {
            return !Number(row.value);
        });
    }

    function totalOf(rows) {
        return rows.reduce(function (sum, row) {
            return sum + (Number(row.value) || 0);
        }, 0);
    }

    function departmentDistribution() {
        var rows = payload('reportDistributionChart');

        if (!rows) {
            return;
        }

        var hasCounts = rows.some(function (row) {
            return (Number(row.high) || 0) + (Number(row.moderate) || 0) + (Number(row.low) || 0) > 0;
        });

        if (!hasCounts) {
            fallback('reportDistributionChart', 'These reports carry no risk counts.');
            return;
        }

        createChart('reportDistributionChart', {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return row.label; }),
                datasets: [
                    {
                        label: 'High',
                        data: rows.map(function (row) { return Number(row.high) || 0; }),
                        backgroundColor: RISK_COLORS.high,
                        stack: 'risk'
                    },
                    {
                        label: 'Moderate',
                        data: rows.map(function (row) { return Number(row.moderate) || 0; }),
                        backgroundColor: RISK_COLORS.moderate,
                        stack: 'risk'
                    },
                    {
                        label: 'Low',
                        data: rows.map(function (row) { return Number(row.low) || 0; }),
                        backgroundColor: RISK_COLORS.low,
                        stack: 'risk'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' student(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        }, 'No report data available.');
    }

    function myPriorityChart() {
        var rows = payload('myPriorityChart');

        if (!rows) {
            return;
        }

        if (allZero(rows) || totalOf(rows) === 0) {
            fallback('myPriorityChart', 'No open cases to distribute.');
            return;
        }

        createChart('myPriorityChart', {
            type: 'doughnut',
            data: {
                labels: rows.map(function (row) { return row.label; }),
                datasets: [{
                    data: rows.map(function (row) { return Number(row.value) || 0; }),
                    backgroundColor: rows.map(function (row) {
                        if (row.label === 'Critical') { return '#6f42c1'; }
                        if (row.label === 'High') { return RISK_COLORS.high; }
                        if (row.label === 'Medium') { return '#17a2b8'; }
                        return '#6c757d';
                    }),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var total = context.dataset.data.reduce(function (sum, value) {
                                    return sum + value;
                                }, 0);
                                var share = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : '0.0';
                                return context.label + ': ' + context.parsed + ' case(s) (' + share + '%)';
                            }
                        }
                    }
                }
            }
        }, 'No open cases available.');
    }

    function myStatusChart() {
        var rows = payload('myStatusChart');

        if (!rows) {
            return;
        }

        if (allZero(rows) || totalOf(rows) === 0) {
            fallback('myStatusChart', 'No cases recorded yet.');
            return;
        }

        createChart('myStatusChart', {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return row.label; }),
                datasets: [{
                    label: 'Cases',
                    data: rows.map(function (row) { return Number(row.value) || 0; }),
                    backgroundColor: ACCENT
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        }, 'No status data available.');
    }

    function myMonthlyChart() {
        var rows = payload('myMonthlyChart');

        if (!rows) {
            return;
        }

        if (!rows.length) {
            fallback('myMonthlyChart', 'No activity recorded yet.');
            return;
        }

        createChart('myMonthlyChart', {
            type: 'line',
            data: {
                labels: rows.map(function (row) { return row.month; }),
                datasets: [
                    {
                        label: 'Escalated',
                        data: rows.map(function (row) { return Number(row.escalated) || 0; }),
                        borderColor: ACCENT,
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Resolved',
                        data: rows.map(function (row) { return Number(row.resolved) || 0; }),
                        borderColor: RISK_COLORS.low,
                        backgroundColor: 'rgba(40, 167, 69, 0.08)',
                        fill: false,
                        tension: 0.3
                    },
                    {
                        label: 'Sessions',
                        data: rows.map(function (row) { return Number(row.sessions) || 0; }),
                        borderColor: '#f6c23e',
                        backgroundColor: 'rgba(246, 194, 62, 0.08)',
                        fill: false,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        }, 'No monthly activity available.');
    }

    function render() {
        if (typeof Chart === 'undefined') {
            ['reportDistributionChart', 'myPriorityChart', 'myStatusChart', 'myMonthlyChart'].forEach(function (id) {
                fallback(id, 'Chart.js could not be loaded, so this chart is unavailable.');
            });
            return;
        }

        departmentDistribution();
        myPriorityChart();
        myStatusChart();
        myMonthlyChart();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
})();

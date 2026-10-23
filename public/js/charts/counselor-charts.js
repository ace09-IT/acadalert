
console.log('[Counselor Charts] Loading...');

let counselorChartsLoaded = false;

function loadCounselorCharts() {
    if (counselorChartsLoaded) {
        console.log('[Counselor Charts] Already loaded, skipping...');
        return;
    }
    counselorChartsLoaded = true;
    
    console.log('[Counselor Charts] Initializing counselor charts...');
    
    const charts = [
        { id: 'priorityChart', name: 'Priority Distribution', loader: loadPriorityDistribution },
        { id: 'statusChart', name: 'Status Distribution', loader: loadStatusDistribution },
        { id: 'caseloadTrendChart', name: 'Caseload Trend', loader: loadCaseloadTrend }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Counselor Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Counselor Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadPriorityDistribution() {
    const canvas = document.getElementById('priorityChart');
    if (!canvas) {
        console.warn('[Counselor Charts] Canvas priorityChart not found');
        return;
    }

    console.log('[Counselor Charts] Fetching priority distribution data...');
    const url = '/charts/counselor/priority-distribution';

    fetchChartData(url)
        .then(response => {
            console.log('[Counselor Charts] Priority distribution response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No priority distribution data available.');
                return;
            }

            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
            if (total === 0) {
                showFallbackMessage(canvas, 'No cases assigned yet.');
                return;
            }

            const config = {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Keep the doughnut proportional within its responsive stage. */
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 14,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' cases (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%',
                }
            };

            const chart = createChart('priorityChart', config, 'No priority distribution data available.');
            if (chart) {
                console.log('[Counselor Charts] Priority Distribution chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Counselor Charts] Priority Distribution error:', error);
            showChartError('Priority Distribution: ' + error.message);
            showFallbackMessage(canvas, 'Error loading priority data: ' + error.message);
        });
}

function loadStatusDistribution() {
    const canvas = document.getElementById('statusChart');
    if (!canvas) {
        console.warn('[Counselor Charts] Canvas statusChart not found');
        return;
    }

    console.log('[Counselor Charts] Fetching status distribution data...');
    const url = '/charts/counselor/status-distribution';

    fetchChartData(url)
        .then(response => {
            console.log('[Counselor Charts] Status distribution response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No status distribution data available.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No cases with status data available.');
                return;
            }

            const config = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Fit the chart to the shared responsive stage. */
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' cases';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: chartGridColor }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('statusChart', config, 'No status distribution data available.');
            if (chart) {
                console.log('[Counselor Charts] Status Distribution chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Counselor Charts] Status Distribution error:', error);
            showChartError('Status Distribution: ' + error.message);
            showFallbackMessage(canvas, 'Error loading status data: ' + error.message);
        });
}

function loadCaseloadTrend() {
    const canvas = document.getElementById('caseloadTrendChart');
    if (!canvas) {
        console.warn('[Counselor Charts] Canvas caseloadTrendChart not found');
        return;
    }

    console.log('[Counselor Charts] Fetching caseload trend data...');
    const url = '/charts/counselor/caseload-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Counselor Charts] Caseload trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No caseload trend data available.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No caseload data available for the selected period.');
                return;
            }

            /* CHANGED: Use the OCEAN accent for caseload trend series. */
            data.datasets.forEach(dataset => {
                dataset.borderColor = window.COLORS.accent;
                dataset.backgroundColor = 'rgba(41, 173, 178, 0.12)';
                dataset.pointBackgroundColor = window.COLORS.accent;
            });

            const config = {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Fit the chart to the shared responsive stage. */
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' new cases';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: chartGridColor },
                            title: {
                                display: true,
                                text: 'New Cases'
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                maxTicksLimit: 8,
                                maxRotation: 0,
                                minRotation: 0,
                            }
                        }
                    }
                }
            };

            const chart = createChart('caseloadTrendChart', config, 'No caseload trend data available.');
            if (chart) {
                console.log('[Counselor Charts] Caseload Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Counselor Charts] Caseload Trend error:', error);
            showChartError('Caseload Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading caseload trend: ' + error.message);
        });
}


function loadStudentRiskTrend() {
    const canvas = document.getElementById('studentRiskTrendChart');
    if (!canvas) {
        console.warn('[Counselor Charts] Canvas studentRiskTrendChart not found');
        return;
    }

    console.log('[Counselor Charts] Loading student risk trend...');
    
    const studentId = document.querySelector('meta[name="student-id"]')?.content || 
                      window.studentId || 
                      document.getElementById('studentRiskTrendChart')?.dataset?.studentId ||
                      0;

    if (!studentId || studentId === '0') {
        console.warn('[Counselor Charts] Student ID not found');
        showFallbackMessage(canvas, 'Student ID not available.');
        return;
    }

    const url = `/charts/counselor/student-risk/${studentId}`;

    fetchChartData(url)
        .then(response => {
            console.log('[Counselor Charts] Student risk trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk trend data available for this student.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No risk data available for this student. Complete at least one grading period.');
                return;
            }

            /* CHANGED: Risk-score trends use OCEAN teal; risk-level badges retain their semantic colors. */
            data.datasets.forEach(dataset => {
                dataset.borderColor = window.COLORS.accent;
                dataset.backgroundColor = 'rgba(41, 173, 178, 0.12)';
                dataset.pointBackgroundColor = window.COLORS.accent;
            });

            const config = {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Fit the chart to the shared responsive stage. */
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Risk Score: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            grid: { color: chartGridColor },
                            ticks: {
                                stepSize: 20
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('studentRiskTrendChart', config, 'No risk trend data available for this student.');
            if (chart) {
                console.log('[Counselor Charts] Student Risk Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Counselor Charts] Student Risk Trend error:', error);
            showChartError('Student Risk Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading student risk trend: ' + error.message);
        });
}

function showFallbackMessage(canvas, message) {
    const parent = canvas.parentElement;
    if (parent) {
        parent.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-chart-simple fa-2x d-block mb-2 opacity-50"></i>
                <p class="mb-0">${message}</p>
            </div>
        `;
    }
}

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(() => {
        if (document.querySelector('#priorityChart, #statusChart, #caseloadTrendChart')) {
            loadCounselorCharts();
        }
        if (document.querySelector('#studentRiskTrendChart')) {
            loadStudentRiskTrend();
        }
    }, 100);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            if (document.querySelector('#priorityChart, #statusChart, #caseloadTrendChart')) {
                loadCounselorCharts();
            }
            if (document.querySelector('#studentRiskTrendChart')) {
                loadStudentRiskTrend();
            }
        }, 100);
    });
}
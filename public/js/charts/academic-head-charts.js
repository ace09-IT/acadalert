
console.log('[Academic Head Charts] Loading...');

let academicHeadChartsLoaded = false;

function loadAcademicHeadCharts() {
    if (academicHeadChartsLoaded) {
        console.log('[Academic Head Charts] Already loaded, skipping...');
        return;
    }
    academicHeadChartsLoaded = true;
    
    console.log('[Academic Head Charts] Initializing teacher charts...');
    
    const charts = [
        { id: 'riskByProgramChart', name: 'Risk by Program', loader: loadRiskByProgram },
        { id: 'riskByBlockChart', name: 'Risk by Block', loader: loadRiskByBlock },
        { id: 'departmentTrendChart', name: 'Department Trend', loader: loadDepartmentTrend },
        { id: 'escalationTrendChart', name: 'Escalation Trend', loader: loadEscalationTrend }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Academic Head Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Academic Head Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadRiskByProgram() {
    const canvas = document.getElementById('riskByProgramChart');
    if (!canvas) {
        console.warn('[Academic Head Charts] Canvas riskByProgramChart not found');
        return;
    }

    console.log('[Academic Head Charts] Fetching risk by program data...');
    const url = '/charts/academic-head/department-risk';

    fetchChartData(url)
        .then(response => {
            console.log('[Academic Head Charts] Risk by program response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || data.length === 0) {
                showFallbackMessage(canvas, 'No risk data available for programs.');
                return;
            }

            const labels = data.map(d => d.program || 'Unknown');
            const highRisk = data.map(d => parseInt(d.high_risk) || 0);
            const moderateRisk = data.map(d => parseInt(d.moderate_risk) || 0);
            const lowRisk = data.map(d => parseInt(d.low_risk) || 0);

            console.log(`[Academic Head Charts] Risk by Program data: ${labels.length} programs`);

            const config = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'High Risk',
                            data: highRisk,
                            backgroundColor: window.RISK_COLORS.high,
                            borderRadius: 4,
                        },
                        {
                            label: 'Moderate Risk',
                            data: moderateRisk,
                            backgroundColor: window.RISK_COLORS.moderate,
                            borderRadius: 4,
                        },
                        {
                            label: 'Low Risk',
                            data: lowRisk,
                            backgroundColor: window.RISK_COLORS.low,
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    /* CHANGED: Fit the responsive parent and give risk segments a visible minimum. */
                    maintainAspectRatio: false,
                    datasets: { bar: { minBarLength: 2 } },
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.x + ' students';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        },
                        y: {
                            stacked: true,
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('riskByProgramChart', config, 'No risk data available for programs.');
            if (chart) {
                console.log('[Academic Head Charts] Risk by Program chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Academic Head Charts] Risk by Program error:', error);
            showChartError('Risk by Program: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk data: ' + error.message);
        });
}

function loadDepartmentTrend() {
    const canvas = document.getElementById('departmentTrendChart');
    if (!canvas) {
        console.warn('[Academic Head Charts] Canvas departmentTrendChart not found');
        return;
    }

    const embedded = readEmbeddedSeries(canvas);

    if (embedded) {
        console.log('[Academic Head Charts] Using embedded department trend data');
        renderDepartmentTrend(canvas, embedded.labels, embedded.data);
        return;
    }

    console.log('[Academic Head Charts] Fetching department trend data...');
    const url = '/charts/academic-head/department-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Academic Head Charts] Department trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No department trend data available.');
                return;
            }

            renderDepartmentTrend(canvas, data.labels || [], data.datasets[0].data || []);
        })
        .catch(error => {
            console.error('[Academic Head Charts] Department Trend error:', error);
            showChartError('Department Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading trend data: ' + error.message);
        });
}

function readEmbeddedSeries(canvas, labelKey, valueKey) {
    const raw = canvas.getAttribute('data-chart');

    if (!raw) {
        return null;
    }

    let parsed;

    try {
        parsed = JSON.parse(raw);
    } catch (error) {
        console.warn('[Academic Head Charts] Ignoring unparseable data-chart attribute:', error.message);
        return null;
    }

    if (!Array.isArray(parsed) || parsed.length === 0) {
        return null;
    }

    return {
        labels: parsed.map(entry => entry[labelKey || 'period']),
        data: parsed.map(entry => Number(entry[valueKey || 'high_risk_percentage']) || 0)
    };
}

function renderDepartmentTrend(canvas, labels, values) {
    const hasData = values.some(val => val > 0);

    if (!hasData) {
        showFallbackMessage(canvas, 'No department trend data available. Complete at least one grading period.');
        return;
    }

    const config = {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'High Risk %',
                data: values,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            /* CHANGED: Use the shared responsive chart stage. */
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'High Risk: ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: chartGridColor },
                    title: { display: true, text: 'High Risk %' }
                },
                x: { grid: { display: false } }
            }
        }
    };

    const chart = createChart('departmentTrendChart', config, 'No department trend data available.');
    if (chart) {
        console.log('[Academic Head Charts] Department Trend chart created successfully');
    }
}


function loadRiskByBlock() {
    const canvas = document.getElementById('riskByBlockChart');
    if (!canvas) {
        return;
    }

    console.log('[Academic Head Charts] Fetching risk by block data...');

    fetchChartData('/charts/academic-head/risk-by-block')
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            const totals = (response.blocks || []).map(b => b.total);
            const hasData = totals.some(total => total > 0);

            if (!data || !data.datasets || !hasData) {
                showFallbackMessage(canvas, 'No block risk data available for this period.');
                return;
            }

            createChart('riskByBlockChart', {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Keep stacked blocks readable within the responsive stage. */
                    maintainAspectRatio: false,
                    datasets: { bar: { minBarLength: 2 } },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14 } },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' student(s)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 8, maxRotation: 0, minRotation: 0 } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            }, 'No block risk data available for this period.');
        })
        .catch(error => {
            console.error('[Academic Head Charts] Risk by Block error:', error);
            showChartError('Risk by Block: ' + error.message);
            showFallbackMessage(canvas, 'Error loading block risk data: ' + error.message);
        });
}

function loadEscalationTrend() {
    const canvas = document.getElementById('escalationTrendChart');
    if (!canvas) {
        return;
    }

    const embedded = readEmbeddedSeries(canvas, 'period', 'escalations');

    if (embedded) {
        console.log('[Academic Head Charts] Using embedded escalation trend data');
        renderEscalationTrend(canvas, embedded.labels, embedded.data);
        return;
    }

    console.log('[Academic Head Charts] Fetching escalation trend data...');

    fetchChartData('/charts/academic-head/escalation-trend')
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No escalation data available.');
                return;
            }

            renderEscalationTrend(canvas, data.labels || [], data.datasets[0].data || []);
        })
        .catch(error => {
            console.error('[Academic Head Charts] Escalation Trend error:', error);
            showChartError('Escalation Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading escalation data: ' + error.message);
        });
}

function renderEscalationTrend(canvas, labels, values) {
    if (!values.some(value => value > 0)) {
        showFallbackMessage(canvas, 'No students have been escalated to the Guidance Counselor yet.');
        return;
    }

    createChart('escalationTrendChart', {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Escalations to Counselor',
                data: values,
                borderColor: '#f6c23e',
                backgroundColor: 'rgba(246, 194, 62, 0.15)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#f6c23e'
            }]
        },
        options: {
            responsive: true,
            /* CHANGED: Use the shared responsive chart stage. */
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.parsed.y + ' escalation(s)';
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Escalations' } },
                x: { grid: { display: false } }
            }
        }
    }, 'No escalation data available.');
}

function loadBlockRiskChart() {
    const canvas = document.getElementById('blockRiskChart');

    if (!canvas) {
        return;
    }

    if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function' && Chart.getChart(canvas)) {
        console.log('[Academic Head Charts] Block risk chart already drawn inline, skipping');
        return;
    }

    const blockIdMeta = document.querySelector('meta[name="block-id"]');

    if (!blockIdMeta) {
        return;
    }

    const blockId = blockIdMeta.getAttribute('content');

    fetchChartData('/charts/academic-head/block-risk/' + blockId)
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;

            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk data available for this block.');
                return;
            }

            createChart('blockRiskChart', {
                type: 'doughnut',
                data: {
                    labels: data.labels || ['Low Risk', 'Moderate Risk', 'High Risk'],
                    datasets: [{
                        data: data.datasets[0].data || [0, 0, 0],
                        backgroundColor: [window.RISK_COLORS.low, window.RISK_COLORS.moderate, window.RISK_COLORS.high],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    /* CHANGED: Keep the doughnut circular and its legend below the plot. */
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14 } } },
                    cutout: '65%'
                }
            }, 'No risk data available for this block.');
        })
        .catch(error => {
            console.error('[Academic Head Charts] Block Risk error:', error);
            showChartError('Block Risk: ' + error.message);
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
        if (document.querySelector('#riskByProgramChart, #departmentTrendChart')) {
            loadAcademicHeadCharts();
        }
    }, 100);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            if (document.querySelector('#riskByProgramChart, #departmentTrendChart')) {
                loadAcademicHeadCharts();
            }
        }, 100);
    });
}
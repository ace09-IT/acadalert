
console.log('[Admin Charts] Loading...');

function adminTrendPalette() {
    const light = document.documentElement.getAttribute('data-bs-theme') === 'light';
    return {
        /* CHANGED: Reuse the OCEAN chart accent in both themes. */
        line: window.COLORS.accent,
        halo: 'rgba(41, 173, 178, 0.16)',
        fillTop: 'rgba(41, 173, 178, 0.30)',
        fillMid: 'rgba(41, 173, 178, 0.10)',
        point: light ? '#ffffff' : '#0D1B2A',
        pointRing: window.COLORS.accent,
        grid: Chart.defaults.borderColor,
        tick: Chart.defaults.color
    };
}

let adminChartsLoaded = false;

function loadAdminCharts() {
    if (adminChartsLoaded) {
        console.log('[Admin Charts] Already loaded, skipping...');
        return;
    }
    adminChartsLoaded = true;
    
    console.log('[Admin Charts] Initializing admin charts...');
    
    const charts = [
        { id: 'riskByDeptChart', name: 'Risk by Department', loader: loadRiskByDepartment },
        { id: 'riskDistributionChart', name: 'Risk Distribution', loader: loadRiskDistribution },
        { id: 'riskTrendChart', name: 'Risk Trend', loader: loadRiskTrend }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Admin Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Admin Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadRiskByDepartment() {
    const canvas = document.getElementById('riskByDeptChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskByDeptChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk by department data...');
    const url = '/charts/admin/risk-by-department';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk by department response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || data.length === 0) {
                showFallbackMessage(canvas, 'No department risk data available.');
                return;
            }

            const labels = data.map(d => d.department || 'Unknown');
            const highRisk = data.map(d => parseInt(d.high_risk) || 0);
            const moderateRisk = data.map(d => parseInt(d.moderate_risk) || 0);
            const lowRisk = data.map(d => parseInt(d.low_risk) || 0);

            console.log(`[Admin Charts] Risk by Department data: ${labels.length} departments`);

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
                    /* CHANGED: Fit the responsive parent without stretching chart content. */
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

            const chart = createChart('riskByDeptChart', config, 'No department risk data available.');
            if (chart) {
                console.log('[Admin Charts] Risk by Department chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk by Department error:', error);
            showChartError('Risk by Department: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk data: ' + error.message);
        });
}

function loadRiskDistribution() {
    const canvas = document.getElementById('riskDistributionChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskDistributionChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk distribution data...');
    const url = '/charts/admin/risk-distribution';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk distribution response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk distribution data available.');
                return;
            }

            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
            console.log(`[Admin Charts] Risk distribution total: ${total} students`);

            if (total === 0) {
                showFallbackMessage(canvas, 'No risk scores recorded yet.');
                return;
            }

            const config = {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Keep doughnut geometry circular inside its chart stage. */
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' students (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%',
                }
            };

            const chart = createChart('riskDistributionChart', config, 'No risk distribution data available.');
            if (chart) {
                console.log('[Admin Charts] Risk Distribution chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk Distribution error:', error);
            showChartError('Risk Distribution: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk distribution: ' + error.message);
        });
}

function loadRiskTrend() {
    const canvas = document.getElementById('riskTrendChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskTrendChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk trend data...');
    const url = '/charts/admin/risk-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk trend data available.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No risk trend data available. Complete at least one grading period.');
                return;
            }

            const series = data.datasets[0];
            const config = {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        Object.assign({}, series, {
                            borderColor: function () { return adminTrendPalette().halo; },
                            borderWidth: 11,
                            borderCapStyle: 'round',
                            borderJoinStyle: 'round',
                            cubicInterpolationMode: 'monotone',
                            fill: false,
                            pointRadius: 0,
                            pointHoverRadius: 0
                        }),
                        Object.assign({}, series, {
                            borderColor: function () { return adminTrendPalette().line; },
                            borderWidth: 3,
                            borderCapStyle: 'round',
                            borderJoinStyle: 'round',
                            cubicInterpolationMode: 'monotone',
                            fill: true,
                            backgroundColor: function (context) {
                                const area = context.chart.chartArea;
                                const palette = adminTrendPalette();
                                if (!area) {
                                    return palette.fillMid;
                                }
                                const gradient = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                                gradient.addColorStop(0, palette.fillTop);
                                gradient.addColorStop(0.62, palette.fillMid);
                                gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
                                return gradient;
                            },
                            pointStyle: 'rectRounded',
                            pointRadius: 5.5,
                            pointHoverRadius: 9,
                            pointBorderWidth: 2,
                            pointBorderColor: function () { return adminTrendPalette().pointRing; },
                            pointBackgroundColor: function () { return adminTrendPalette().point; },
                            pointHoverBorderColor: function () { return adminTrendPalette().pointRing; },
                            pointHoverBackgroundColor: function () { return adminTrendPalette().point; }
                        })
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            // Only the plot answers the pointer; the halo is light, not data.
                            filter: function (item) {
                                return item.datasetIndex === 1;
                            },
                            backgroundColor: 'rgba(10, 16, 36, 0.94)',
                            borderColor: 'rgba(96, 165, 250, 0.45)',
                            borderWidth: 1,
                            cornerRadius: 12,
                            padding: 12,
                            displayColors: false,
                            titleColor: '#eaf1ff',
                            bodyColor: '#dbe8ff',
                            titleFont: { weight: '600' },
                            callbacks: {
                                label: function(context) {
                                    return 'High Risk: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            border: { display: false },
                            grid: {
                                color: function () { return adminTrendPalette().grid; },
                                drawTicks: false
                            },
                            ticks: {
                                color: function () { return adminTrendPalette().tick; },
                                padding: 10,
                                maxTicksLimit: 6,
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            title: {
                                display: true,
                                text: 'High Risk %',
                                color: function () { return adminTrendPalette().tick; }
                            }
                        },
                        x: {
                            border: { display: false },
                            grid: { display: false },
                            ticks: {
                                color: function () { return adminTrendPalette().tick; },
                                padding: 8,
                                maxRotation: 0,
                                autoSkipPadding: 16
                            }
                        }
                    }
                }
            };

            const chart = createChart('riskTrendChart', config, 'No risk trend data available.');
            if (chart) {
                console.log('[Admin Charts] Risk Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk Trend error:', error);
            showChartError('Risk Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk trend: ' + error.message);
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
    // Small delay to ensure everything is rendered
    setTimeout(() => {
        if (document.querySelector('#riskByDeptChart, #riskDistributionChart, #riskTrendChart')) {
            loadAdminCharts();
        }
    }, 100);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            if (document.querySelector('#riskByDeptChart, #riskDistributionChart, #riskTrendChart')) {
                loadAdminCharts();
            }
        }, 100);
    });
}
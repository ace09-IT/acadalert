
console.log('[Student Charts] Loading...');

function loadStudentCharts() {
    console.log('[Student Charts] Initializing student charts...');
    
    const charts = [
        { id: 'studentRiskTrendChart', name: 'Risk Trend', loader: loadRiskTrend },
        { id: 'subjectGradesChart', name: 'Subject Grades', loader: loadSubjectGrades }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Student Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Student Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadRiskTrend() {
    const canvas = document.getElementById('studentRiskTrendChart');
    if (!canvas) {
        console.warn('[Student Charts] Canvas studentRiskTrendChart not found');
        return;
    }

    console.log('[Student Charts] Fetching student risk trend data...');
    const url = '/charts/student/risk-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Student Charts] Risk trend response:', response);
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
                showFallbackMessage(canvas, 'No risk data available. Complete at least one grading period.');
                return;
            }

            if (data.labels && data.labels.length === 1 && data.labels[0] === 'No Data') {
                showFallbackMessage(canvas, 'No risk data available. Complete at least one grading period.');
                return;
            }

            /* CHANGED: Use the OCEAN accent for the student's non-categorical score trend. */
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
                    /* CHANGED: Fit the responsive parent rather than inheriting a fixed ratio. */
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

            const chart = createChart('studentRiskTrendChart', config, 'No risk trend data available.');
            if (chart) {
                console.log('[Student Charts] Risk Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Student Charts] Risk Trend error:', error);
            showChartError('Risk Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk trend: ' + error.message);
        });
}

function loadSubjectGrades() {
    const canvas = document.getElementById('subjectGradesChart');
    if (!canvas) {
        console.warn('[Student Charts] Canvas subjectGradesChart not found');
        return;
    }

    console.log('[Student Charts] Fetching subject grades data...');
    
    const urlParams = new URLSearchParams(window.location.search);
    const period = urlParams.get('period') || 'Midterm';
    const url = `/charts/student/grades?period=${period}`;

    fetchChartData(url)
        .then(response => {
            console.log('[Student Charts] Subject grades response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No grades available for this period.');
                return;
            }

            if (data.labels && data.labels.length === 1 && data.labels[0] === 'No Data') {
                showFallbackMessage(canvas, 'No grades available for this period.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No grades available for this period.');
                return;
            }

            /* CHANGED: Keep grade bars on the shared OCEAN series palette. */
            data.datasets.forEach(dataset => {
                dataset.backgroundColor = window.COLORS.primary;
                dataset.borderColor = window.COLORS.accent;
                dataset.borderWidth = 1;
                dataset.borderRadius = 5;
            });

            const config = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    /* CHANGED: Fit the responsive parent rather than inheriting a fixed ratio. */
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Grade: ' + context.parsed.x + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            grid: { color: chartGridColor },
                            title: {
                                display: true,
                                text: 'Grade %'
                            }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('subjectGradesChart', config, 'No grades available for this period.');
            if (chart) {
                console.log('[Student Charts] Subject Grades chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Student Charts] Subject Grades error:', error);
            showChartError('Subject Grades: ' + error.message);
            showFallbackMessage(canvas, 'Error loading grades: ' + error.message);
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
    if (document.querySelector('#studentRiskTrendChart, #subjectGradesChart')) {
        loadStudentCharts();
    }
}
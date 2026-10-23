
/* CHANGED: Publish shared chart state before theme code or pending fetch callbacks can read it. */
var chartInstances = window.chartInstances || (window.chartInstances = {});
var oceanPalette = getComputedStyle(document.documentElement);
window.COLORS = {
    primary: oceanPalette.getPropertyValue('--ocean-primary').trim(),
    accent: oceanPalette.getPropertyValue('--ocean-accent').trim(),
    soft: oceanPalette.getPropertyValue('--ocean-soft').trim(),
    success: '#198754',
    warning: '#e7b938',
    danger: '#dc3545',
    info: oceanPalette.getPropertyValue('--ocean-accent').trim(),
    secondary: oceanPalette.getPropertyValue('--ocean-soft').trim(),
    purple: oceanPalette.getPropertyValue('--ocean-primary').trim(),
};
window.RISK_COLORS = {
    low: '#198754',
    moderate: '#e7b938',
    high: '#dc3545',
};

Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
/* CHANGED: Standardize sizing and compact circular legends across role charts. */
Chart.defaults.font.size = 12;
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.boxHeight = 8;
Chart.defaults.plugins.legend.labels.padding = 14;
/* CHANGED: Keep tooltip typography and contrast consistent in dark and light themes. */
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(13, 27, 42, 0.96)';
Chart.defaults.plugins.tooltip.titleColor = '#E6F4F1';
Chart.defaults.plugins.tooltip.bodyColor = '#E6F4F1';
Chart.defaults.plugins.tooltip.borderColor = 'rgba(168, 218, 220, 0.28)';
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.scale.ticks.font.size = 11;
Chart.defaults.scale.grid.color = function () {
    return Chart.defaults.borderColor;
};

Chart.defaults.color = document.documentElement.getAttribute('data-bs-theme') === 'light' ? '#666666' : '#adb5bd';
Chart.defaults.borderColor = document.documentElement.getAttribute('data-bs-theme') === 'light' ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';

/* CHANGED: Resolve grid contrast at update time so theme toggles repaint it correctly. */
function chartGridColor() {
    return Chart.defaults.borderColor;
}


async function fetchChartData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...options.headers,
            },
            ...options,
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[Chart] Fetch error:', error);
        showChartError(error.message);
        throw error;
    }
}

function showChartError(message) {
    const errorContainer = document.getElementById('chartErrorContainer');
    if (errorContainer) {
        errorContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Chart Error:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        errorContainer.style.display = 'block';
    } else {
        const toast = document.createElement('div');
        toast.className = 'alert alert-danger alert-dismissible fade show';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '400px';
        toast.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Chart Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 8000);
    }
}

function createChart(canvasId, config, fallbackMessage = 'No data available') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`[Chart] Canvas element "${canvasId}" not found.`);
        return null;
    }

    
    if (chartInstances[canvasId]) {
        console.log(`[Chart] Destroying existing chart on canvas "${canvasId}" (from registry)`);
        try {
            chartInstances[canvasId].destroy();
            chartInstances[canvasId] = null;
            delete chartInstances[canvasId];
        } catch (e) {
            console.warn(`[Chart] Could not destroy existing chart from registry:`, e);
        }
    }

    if (canvas.chart) {
        console.log(`[Chart] Destroying chart attached to canvas "${canvasId}"`);
        try {
            canvas.chart.destroy();
            canvas.chart = null;
        } catch (e) {
            console.warn(`[Chart] Could not destroy attached chart:`, e);
        }
    }

    if (canvas.__chartjs) {
        console.log(`[Chart] Destroying chart via __chartjs property on canvas "${canvasId}"`);
        try {
            canvas.__chartjs.destroy();
            canvas.__chartjs = null;
        } catch (e) {
            console.warn(`[Chart] Could not destroy chart via __chartjs:`, e);
        }
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        console.warn(`[Chart] Could not get context for "${canvasId}".`);
        return null;
    }

    try {
        const hasData = config.data.datasets.some(dataset => 
            dataset.data && dataset.data.length > 0 && dataset.data.some(val => val !== 0 && val !== null)
        );

        if (!hasData) {
            const parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-simple fa-2x d-block mb-2 opacity-50"></i>
                        <p class="mb-0">${fallbackMessage}</p>
                    </div>
                `;
            }
            return null;
        }

        const chart = new Chart(ctx, config);
        
        chartInstances[canvasId] = chart;
        canvas.chart = chart;
        canvas.__chartjs = chart;
        
        console.log(`[Chart] Chart "${canvasId}" created successfully.`);
        return chart;
        
    } catch (error) {
        console.error(`[Chart] Error creating chart "${canvasId}":`, error);
        const parent = canvas.parentElement;
        if (parent) {
            parent.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                    <p class="mb-0">Failed to load chart: ${error.message}</p>
                </div>
            `;
        }
        return null;
    }
}

function createChartWithLoading(canvasId, config, loadingMessage = 'Loading chart data...') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return Promise.resolve(null);
    }

    const parent = canvas.parentElement;
    const loadingEl = document.createElement('div');
    loadingEl.className = 'chart-loading';
    loadingEl.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mb-0">${loadingMessage}</p>
        </div>
    `;
    parent.appendChild(loadingEl);
    canvas.style.display = 'none';

    return new Promise((resolve) => {
        setTimeout(() => {
            loadingEl.remove();
            canvas.style.display = 'block';
            const chart = createChart(canvasId, config);
            resolve(chart);
        }, 300);
    });
}

function destroyAllCharts() {
    console.log('[Chart] Destroying all chart instances...');
    for (const canvasId in chartInstances) {
        if (chartInstances[canvasId]) {
            try {
                chartInstances[canvasId].destroy();
                console.log(`[Chart] Destroyed chart on canvas "${canvasId}"`);
            } catch (e) {
                console.warn(`[Chart] Could not destroy chart on "${canvasId}":`, e);
            }
        }
    }
    for (const key in chartInstances) {
        delete chartInstances[key];
    }
    document.querySelectorAll('canvas').forEach(canvas => {
        if (canvas.chart) {
            try {
                canvas.chart.destroy();
            } catch (e) {}
            canvas.chart = null;
        }
        if (canvas.__chartjs) {
            try {
                canvas.__chartjs.destroy();
            } catch (e) {}
            canvas.__chartjs = null;
        }
    });
    console.log('[Chart] All charts destroyed.');
}

window.destroyAllCharts = destroyAllCharts;
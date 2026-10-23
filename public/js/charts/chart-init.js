
console.log('[Chart Init] Starting chart initialization...');

document.addEventListener('DOMContentLoaded', function() {
    console.log('[Chart Init] DOM loaded, initializing charts...');
    
    const currentRoute = window.location.pathname;
    console.log('[Chart Init] Current route:', currentRoute);
    
    if (currentRoute.includes('/admin/dashboard')) {
        console.log('[Chart Init] Loading Admin Charts...');
        if (typeof loadAdminCharts === 'function') {
            loadAdminCharts();
        } else {
            console.warn('[Chart Init] loadAdminCharts function not found');
        }
    }
    
    if (currentRoute.includes('/academic-head/department')) {
        console.log('[Chart Init] Loading Academic Head Department Charts...');
        if (typeof loadAcademicHeadCharts === 'function') {
            loadAcademicHeadCharts();
        } else {
            console.warn('[Chart Init] loadAcademicHeadCharts function not found');
        }
    }
    
    if (currentRoute.includes('/academic-head/block')) {
        console.log('[Chart Init] Loading Block Risk Chart...');
        if (typeof loadBlockRiskChart === 'function') {
            loadBlockRiskChart();
        } else {
            console.warn('[Chart Init] loadBlockRiskChart function not found');
        }
    }
    
    if (currentRoute.includes('/counselor/dashboard') || currentRoute.includes('/counselor/cases')) {
        console.log('[Chart Init] Loading Counselor Charts...');
        if (typeof loadCounselorCharts === 'function') {
            loadCounselorCharts();
        } else {
            console.warn('[Chart Init] loadCounselorCharts function not found');
        }
    }
    
    if (currentRoute.includes('/counselor/case')) {
        console.log('[Chart Init] Loading Student Risk Trend Chart...');
        if (typeof loadStudentRiskTrend === 'function') {
            loadStudentRiskTrend();
        } else {
            console.warn('[Chart Init] loadStudentRiskTrend function not found');
        }
    }
    
    if (currentRoute.includes('/student/dashboard')) {
        console.log('[Chart Init] Loading Student Charts...');
        if (typeof loadStudentCharts === 'function') {
            loadStudentCharts();
        } else {
            console.warn('[Chart Init] loadStudentCharts function not found');
        }
    }
});


window.addEventListener('error', function(e) {
    if (e.message && e.message.includes('Chart')) {
        console.error('[Chart Error]', e.message);
        showChartError(e.message);
    }
});

console.log('[Chart Init] Chart initialization complete.');
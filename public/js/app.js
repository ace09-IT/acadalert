
document.addEventListener('DOMContentLoaded', function() {
    console.log('AcadAlert v1.0.0 loaded successfully.');
    
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
        setTimeout(function() {
            alert.classList.add('fade');
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
});


function toggleSidebar(forceOpen) {
    const sidebar = document.getElementById('sidebar-wrapper');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggle = document.getElementById('sidebarToggleBtn');

    if (!sidebar) {
        return;
    }

    const shouldOpen = typeof forceOpen === 'boolean'
        ? forceOpen
        : !sidebar.classList.contains('show');

    sidebar.classList.toggle('show', shouldOpen);

    if (backdrop) {
        backdrop.classList.toggle('show', shouldOpen);
    }

    if (toggle) {
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        toggleSidebar(false);
    }
});

// A resize past the breakpoint must not leave a stale backdrop or `show` class
// pinned over the (now always-visible) desktop sidebar.
window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) {
        toggleSidebar(false);
    }
});


document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});


function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}


function showToast(message, type = 'success') {
    const colors = {
        success: 'bg-success text-white',
        error: 'bg-danger text-white',
        warning: 'bg-warning text-dark',
        info: 'bg-info text-white'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center ${colors[type] || colors.info} border-0 show`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    container.appendChild(toast);
    document.body.appendChild(container);
    
    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() {
            container.remove();
        }, 300);
    }, 4000);
}


function showLoading(element) {
    const original = element.innerHTML;
    element.disabled = true;
    element.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading...
    `;
    element.dataset.original = original;
}

function hideLoading(element) {
    element.disabled = false;
    element.innerHTML = element.dataset.original || 'Submit';
}


var AcadAlertTheme = (function () {
    var STORAGE_KEY = 'acadalerts-theme';
    var DARK = 'dark';
    var LIGHT = 'light';

    function normalise(value) {
        return value === LIGHT ? LIGHT : DARK;
    }

    function current() {
        var root = document.documentElement;

        if (root.classList.contains('theme-light')) {
            return LIGHT;
        }

        if (root.classList.contains('theme-dark')) {
            return DARK;
        }

        return normalise(root.getAttribute('data-bs-theme'));
    }

    function read() {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch (error) {
            return null;
        }
    }

    function write(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
        }
    }

    function repaintCharts(theme) {
        try {
            if (!window.Chart || !window.Chart.defaults) {
                return;
            }

            var light = theme === LIGHT;
            window.Chart.defaults.color = light ? '#666666' : '#adb5bd';
            window.Chart.defaults.borderColor = light
                ? 'rgba(0, 0, 0, 0.08)'
                : 'rgba(255, 255, 255, 0.08)';

            // `chartInstances` comes from public/js/charts/chart-config.js, which
            // only the chart pages load; typeof keeps this safe everywhere else.
            if (typeof chartInstances === 'undefined' || !chartInstances) {
                return;
            }

            Object.keys(chartInstances).forEach(function (key) {
                var chart = chartInstances[key];

                if (chart && typeof chart.update === 'function') {
                    chart.update();
                }
            });
        } catch (error) {
            // A chart that cannot repaint must never break the toggle.
        }
    }

    function syncButton(theme) {
        var button = document.getElementById('themeToggleBtn');

        if (!button) {
            return;
        }

        var dark = theme === DARK;
        var label = 'Switch to ' + (dark ? 'light' : 'dark') + ' theme';

        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }

    function apply(theme, persist) {
        var root = document.documentElement;
        var name = normalise(theme);

        root.setAttribute('data-bs-theme', name);
        root.classList.remove('theme-dark', 'theme-light');
        root.classList.add('theme-' + name);

        if (persist) {
            write(name);
        }

        syncButton(name);
        repaintCharts(name);

        return name;
    }

    function toggle() {
        return apply(current() === DARK ? LIGHT : DARK, true);
    }

    function init() {
        apply(normalise(read()), false);

        window.addEventListener('storage', function (event) {
            if (event.key === STORAGE_KEY) {
                apply(normalise(event.newValue), false);
            }
        });
    }

    return {
        current: current,
        apply: apply,
        toggle: toggle,
        init: init
    };
})();

function toggleTheme() {
    return AcadAlertTheme.toggle();
}

document.addEventListener('DOMContentLoaded', function () {
    AcadAlertTheme.init();
});
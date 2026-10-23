'use strict';

const fs = require('fs');
const vm = require('vm');

const BLADE = require('path').join(__dirname, '..', '..', 'resources', 'views', 'academic-head', 'block.blade.php');

const NO_DEPS = process.env.HARNESS_NO_DEPS === '1';

const raw = fs.readFileSync(BLADE, 'utf8');
const pushStart = raw.indexOf("@push('scripts')");
const scriptOpen = raw.indexOf('<script>', pushStart);
const scriptClose = raw.indexOf('</script>', scriptOpen);

if (pushStart < 0 || scriptOpen < 0 || scriptClose < 0) {
    console.error('FAIL: could not locate the @push(scripts) inline script block.');
    process.exit(2);
}

const source = raw.slice(scriptOpen + 8, scriptClose).replace(/\{\{[^}]*\}\}/g, '0');

const docListeners = {};
const fetches = [];
const alerts = [];
let modalConstructed = 0;
let modalShown = 0;
let chartsCreated = 0;

function matchesSelector(el, selector) {
    if (!el || !selector) return false;

    const first = selector.split(',')[0].trim();

    if (first.startsWith('#')) return el.id === first.slice(1);

    if (first.startsWith('.')) {
        const name = first.slice(1).replace(/:checked$/, '');
        return (el.classes || []).includes(name);
    }

    return false;
}

function element(tag, options = {}) {
    const el = {
        tagName: (tag || 'div').toUpperCase(),
        id: options.id || '',
        classes: options.classes || [],
        dataset: options.dataset || {},
        style: {},
        value: options.value === undefined ? '' : options.value,
        checked: !!options.checked,
        disabled: false,
        innerHTML: '',
        textContent: options.textContent || '',
        children: [],
        parentNode: null,
        classList: {
            add: (...names) => names.forEach((name) => { if (!el.classes.includes(name)) el.classes.push(name); }),
            remove: (...names) => { el.classes = el.classes.filter((name) => !names.includes(name)); },
            contains: (name) => el.classes.includes(name),
            toggle: (name, force) => {
                const shouldHave = force === undefined ? !el.classes.includes(name) : !!force;
                if (shouldHave && !el.classes.includes(name)) el.classes.push(name);
                if (!shouldHave) el.classes = el.classes.filter((n) => n !== name);
                return shouldHave;
            },
        },
        addEventListener(type, handler) { (el.listeners[type] = el.listeners[type] || []).push(handler); },
        removeEventListener() {},
        listeners: {},
        appendChild(child) { el.children.push(child); child.parentNode = el; return child; },
        append(...children) { children.forEach((child) => el.appendChild(child)); },
        remove() {},
        focus() {},
        getContext() { return {}; },
        matches: (selector) => matchesSelector(el, selector),
        closest(selector) {
            let node = el;
            while (node) {
                if (matchesSelector(node, selector)) return node;
                node = node.parentNode;
            }
            return null;
        },
        querySelector: () => null,
        querySelectorAll: () => [],
        getAttribute: (name) => (name === 'content' ? 'csrf-token-value' : null),
        setAttribute() {},
    };

    return el;
}

const rows = [];
const checkboxes = [];

[['175', 'Guzman, Ursula', 'High'], ['176', 'Castillo, Camille', 'Moderate']].forEach(([id, name, risk], index) => {
    const nameCell = element('td', { dataset: { label: 'Student' } });
    nameCell.querySelector = (selector) => (selector.includes('strong') ? element('strong', { textContent: name }) : null);

    const riskCell = element('td', { dataset: { label: 'Current Risk' } });
    riskCell.querySelector = (selector) => (selector.includes('.badge') ? element('span', { textContent: risk }) : null);

    const statusCell = element('td', { dataset: { label: 'Status' } });
    const tr = element('tr');
    tr.querySelector = (selector) => {
        if (selector.includes('Student')) return nameCell;
        if (selector.includes('Current Risk')) return riskCell;
        if (selector.includes('Status')) return statusCell;
        return null;
    };

    const checkbox = element('input', { classes: ['student-checkbox'], value: id, checked: index === 0 });
    checkbox.closest = (selector) => (selector === 'tr' ? tr : (matchesSelector(checkbox, selector) ? checkbox : null));

    checkboxes.push(checkbox);
    rows.push(tr);
});

const escalateButton = element('button', { classes: ['escalate-btn'], dataset: { student: '175', name: 'Guzman, Ursula' } });
const resetButton = element('button', { classes: ['reset-escalate-btn'], dataset: { student: '175', name: 'Guzman, Ursula' } });

const tableBody = element('tbody', { id: 'studentTableBody' });
tableBody.querySelectorAll = () => rows;

const elementsById = {};

function byId(id) {
    if (!elementsById[id]) {
        const el = element('div', { id });
        el.querySelectorAll = () => [];
        elementsById[id] = el;
    }

    return elementsById[id];
}

byId('gradingPeriodSelect').value = 'Midterm';
byId('escalationNotes').value = 'Harness run';
byId('resetReason').value = 'Accidental escalation';
['filterRisk', 'filterGrade', 'filterAttendance'].forEach((id) => { byId(id).value = 'all'; });
byId('refreshScoringBtn').style = { display: 'none' };

const csrfMeta = element('meta');
csrfMeta.getAttribute = () => 'csrf-token-value';

const document = {
    body: element('body'),
    addEventListener(type, handler) {
        (docListeners[type] = docListeners[type] || []).push(handler);
    },
    removeEventListener() {},
    getElementById: (id) => byId(id),
    createElement: (tag) => element(tag),
    querySelector(selector) {
        if (selector.includes('csrf-token')) return csrfMeta;
        if (selector.startsWith('#')) return byId(selector.slice(1));
        return null;
    },
    querySelectorAll(selector) {
        if (selector === '.student-checkbox') return checkboxes;
        if (selector === '.student-checkbox:checked') return checkboxes.filter((cb) => cb.checked);
        if (selector === '.escalate-btn') return [escalateButton];
        if (selector === '.reset-escalate-btn') return [resetButton];
        if (selector.startsWith('#studentTableBody')) {
            return selector.includes('.student-checkbox') ? checkboxes : rows;
        }
        return [];
    },
};



const location = { href: 'http://localhost/academic-head/block/1?period=Midterm' };

const bootstrap = NO_DEPS ? undefined : {
    Modal: class Modal {
        constructor(el) { this.el = el; modalConstructed++; }
        show() { modalShown++; }
        hide() {}
        static getOrCreateInstance(el) { return new Modal(el); }
        static getInstance(el) { return new Modal(el); }
    },
    Tooltip: class Tooltip { constructor() {} },
};

const Chart = NO_DEPS ? undefined : class Chart {
    constructor() { chartsCreated++; }
};

function payloadFor(url) {
    if (url.includes('/escalation/recommendation-preview')) {
        return {
            success: true,
            period: 'Midterm',
            students: {
                175: {
                    student_id: 175,
                    student_name: 'Guzman, Ursula',
                    student_number: 'UDD-2024-BSIT-1A-001',
                    risk_level: 'High',
                    risk_score: 85,
                    risk_factors: ['Low grades in programming', 'Frequent absences'],
                    has_recommendation: true,
                    recommendation_id: 125,
                    preview_text: '[high] Tutoring: One-to-one help',
                    suggested_actions: [{ action: 'tutoring', details: 'One-to-one help', priority: 'high' }],
                    generated_at: 'Mar 03, 2026 10:12 AM',
                },
            },
        };
    }

    if (url.includes('/check-escalation-status')) {
        return { success: true, statuses: { 175: { is_escalated: false }, 176: { is_escalated: false } } };
    }

    if (url.includes('/bulk-escalate')) {
        return { success: true, total: 1, escalated: 1, already_escalated: 0, recommendations_forwarded: 1, results: [{ student_id: 175, status: 'success', case_id: 900 }] };
    }

    if (url.includes('/escalated-students')) {
        return { success: true, students: [175], count: 1 };
    }

    if (url.includes('/scoring-status')) {
        return { success: true, has_scores: false, status: 'idle' };
    }

    return { success: true, message: 'ok' };
}

const window = {
    location,
    addEventListener() {},
    removeEventListener() {},
    alert: (message) => alerts.push(String(message)),
    confirm: (message) => true,
    fetch: (url, options) => {
        fetches.push({ url: String(url), options: options || {} });

        return Promise.resolve({ ok: true, status: 200, json: async () => payloadFor(String(url)) });
    },
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval,
    bootstrap,
    Chart,
    URL,
    JSON,
    console,
};


const sandbox = {
    window,
    document,
    console,
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval,
    fetch: window.fetch,
    alert: window.alert,
    confirm: window.confirm,
    bootstrap,
    Chart,
    URL,
    JSON,
    Math,
    Date,
    Promise,
    String,
    Number,
    Array,
    Object,
    Boolean,
    parseInt,
    parseFloat,
    isNaN,
    encodeURIComponent,
    decodeURIComponent,
    navigator: { userAgent: 'node-harness' },
};

sandbox.globalThis = sandbox;

let loadError = null;

try {
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox, { filename: 'block-inline.js' });
} catch (error) {
    loadError = error;
}

const results = [];

function check(name, ok, detail = '') {
    results.push({ name, ok, detail });
}

check('inline script evaluates (no load-time error)', loadError === null, loadError ? (loadError.name + ': ' + loadError.message) : 'no throw');

if (loadError) {
    results.forEach((row) => console.log((row.ok ? 'PASS  ' : 'FAIL  ') + row.name + (row.detail ? '  [' + row.detail + ']' : '')));
    process.exit(1);
}

try {
    (docListeners['DOMContentLoaded'] || []).forEach((handler) => handler({ type: 'DOMContentLoaded' }));
    check('DOMContentLoaded callback completes', true, 'no throw');
} catch (error) {
    check('DOMContentLoaded callback completes', false, error.name + ': ' + error.message);
}

function dispatch(handlerList, event, thisArg) {
    let threw = null;

    (handlerList || []).forEach((handler) => {
        try {
            // Real dispatch binds `this` to the element the listener sits on; the
            // period auto-submit reads `this.value`, so the harness must too.
            handler.call(thisArg, event);
        } catch (error) {
            threw = error;
        }
    });

    return threw;
}

const click = (target) => dispatch(docListeners['click'], { type: 'click', target, preventDefault() {}, stopPropagation() {} }, document);
const change = (target, value) => {
    if (value !== undefined) target.value = value;

    const event = { type: 'change', target, preventDefault() {}, stopPropagation() {} };

    return dispatch(target.listeners ? target.listeners['change'] : null, event, target)
        || dispatch(docListeners['change'], event, document);
};
const fetchesTo = (fragment) => fetches.filter((entry) => entry.url.includes(fragment));
const previewCalls = () => fetchesTo('/escalation/recommendation-preview').length;


let alertsBefore = alerts.length;
let before = previewCalls();
let threw = click(escalateButton);

if (NO_DEPS) {
    // Without the modal library the dialog cannot open — what MUST still happen is the
    // data fetch and a loud, actionable report instead of a dead click.
    check('Escalate (row button): data path fires + failure is reported',
        previewCalls() === before + 1 && alerts.length > alertsBefore,
        'preview fetch x' + previewCalls() + ', alerts x' + (alerts.length - alertsBefore)
        + (threw ? ', threw: ' + threw.message : ''));
} else {
    check('Escalate (row button) opens the modal', previewCalls() === before + 1 && modalShown > 0,
        'preview fetch x' + previewCalls() + ', modal shown x' + modalShown + (threw ? ', threw: ' + threw.message : ''));
}

alertsBefore = alerts.length;
before = modalShown;
threw = click(resetButton);

if (NO_DEPS) {
    check('Reset (row button): failure is reported, nothing throws',
        !threw && alerts.length > alertsBefore,
        (alerts.length - alertsBefore) + ' alert(s) raised');
} else {
    check('Reset (row button) opens the reset modal', modalShown === before + 1,
        threw ? 'threw: ' + threw.message : 'modal shown x' + modalShown);
}

before = fetchesTo('/run-scoring').length;
threw = click(byId('runRiskScoringBtn'));
check('Run AI Risk Scoring issues its request', fetchesTo('/run-scoring').length === before + 1,
    threw ? 'threw: ' + threw.message : 'run-scoring fetch issued');

before = previewCalls();
threw = click(byId('bulkEscalateBtn'));
check('Escalate Selected opens the modal for the checked rows', previewCalls() === before + 1,
    document.querySelectorAll('.student-checkbox:checked').length + ' row(s) checked' + (threw ? ', threw: ' + threw.message : ''));

before = previewCalls();
threw = click(byId('bulkEscalateAllBtn'));
check('Escalate All Flagged opens the modal', previewCalls() === before + 1, threw ? 'threw: ' + threw.message : 'visible rows included');

before = fetchesTo('check-escalation-status').length;
click(byId('bulkResetBtn'));
check('Reset Selected requests the escalation status', fetchesTo('check-escalation-status').length === before + 1, '');

before = fetchesTo('/escalated-students').length;
click(byId('bulkResetAllBtn'));
check('Reset All Escalation loads the block escalations', fetchesTo('/escalated-students').length === before + 1, '');

threw = click(byId('applyFiltersBtn'));
const filterThrew = click(byId('resetFiltersBtn'));
check('Table filters (apply/reset) run without throwing', !threw && !filterThrew,
    (threw || filterThrew) ? 'threw: ' + (threw || filterThrew).message : 'both handlers ran');

const hrefBefore = location.href;
change(byId('gradingPeriodSelect'), 'Finals');
check('Term filter (Prelim/Midterm/Finals) reloads with the new period',
    location.href !== hrefBefore && location.href.includes('period=Finals'),
    location.href);
location.href = hrefBefore;

before = fetchesTo('/bulk-escalate').length;
click(byId('confirmEscalationBtn'));
const posted = fetchesTo('/bulk-escalate').slice(-1)[0];
let postedBody = null;

try {
    postedBody = posted ? JSON.parse(posted.options.body) : null;
} catch (error) {
    postedBody = null;
}

check('Escalate to Counselor POSTs the case',
    fetchesTo('/bulk-escalate').length === before + 1 && postedBody !== null && Array.isArray(postedBody.student_ids),
    postedBody ? 'student_ids=' + JSON.stringify(postedBody.student_ids) : 'no POST body');

check('the POST carries the per-student recommendation choice',
    postedBody !== null && postedBody.recommendations && postedBody.recommendations['175'] !== undefined,
    postedBody && postedBody.recommendations ? JSON.stringify(postedBody.recommendations['175']) : 'missing');

if (NO_DEPS) {
    const alertsBefore = alerts.length;

    let degradeThrew = click(escalateButton);
    check('no-deps: Escalate does not throw a silent error', !degradeThrew,
        degradeThrew ? degradeThrew.name + ': ' + degradeThrew.message : 'no throw');
    check('no-deps: Escalate reports the missing dependency to the user',
        alerts.length > alertsBefore, (alerts.length - alertsBefore) + ' alert(s) raised');

    const runAlertsBefore = alerts.length;
    let runThrew = click(byId('runRiskScoringBtn'));
    check('no-deps: Run AI Risk Scoring does not throw', !runThrew,
        runThrew ? runThrew.name + ': ' + runThrew.message : 'no throw');
    check('no-deps: Run AI Risk Scoring reports the missing dependency',
        alerts.length > runAlertsBefore, (alerts.length - runAlertsBefore) + ' alert(s) raised');

    const hrefBeforeNoDeps = location.href;
    change(byId('gradingPeriodSelect'), 'Prelim');
    check('no-deps: the term filter still works without any library',
        location.href.includes('period=Prelim'), location.href);
    location.href = hrefBeforeNoDeps;

    check('no-deps: charts are skipped without breaking the page', chartsCreated === 0,
        chartsCreated + ' chart instance(s)');
}

let failures = 0;

results.forEach((row) => {
    if (!row.ok) failures++;
    console.log((row.ok ? 'PASS  ' : 'FAIL  ') + row.name + (row.detail ? '  [' + row.detail + ']' : ''));
});

console.log('');
console.log('fetches: ' + fetches.length + ' | modal instances: ' + modalConstructed + ' | chart instances: ' + chartsCreated + ' | alerts: ' + alerts.length);
console.log(failures === 0 ? 'RESULT: every control handler fired.' : 'RESULT: ' + failures + ' control(s) did NOT fire.');
process.exit(failures === 0 ? 0 : 1);


'use strict';

// Regression guard for the Block Dashboard's AI-scoring failure modal.
//
// The Retry button is created on demand (the modal markup contains none), so
// the bug was that every failure appended another one while the previous was
// still in the modal — a second failure showed TWO Retry buttons. This harness
// fails the scoring request repeatedly and asserts the modal never holds more
// than one.
//
// It uses a controllable timer instead of real waiting, so it runs instantly
// and deterministically.

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const BLADE = path.join(__dirname, '..', '..', 'resources', 'views', 'academic-head', 'block.blade.php');

const raw = fs.readFileSync(BLADE, 'utf8');
const pushStart = raw.indexOf("@push('scripts')");
const scriptOpen = raw.indexOf('<script>', pushStart);
const scriptClose = raw.indexOf('</script>', scriptOpen);

if (pushStart < 0 || scriptOpen < 0 || scriptClose < 0) {
    console.error('FAIL: could not locate the @push(scripts) inline script block.');
    process.exit(2);
}

const source = raw.slice(scriptOpen + 8, scriptClose).replace(/\{\{[^}]*\}\}/g, '0');

const results = [];
const check = (name, ok, detail = '') => results.push({ name, ok, detail });

// ---- controllable timers (deterministic; nothing actually waits) --------
let seq = 0;
const pending = new Map();

function setTimeoutStub(fn, delay) {
    const id = ++seq;
    pending.set(id, { fn, delay: delay || 0 });
    return id;
}

function clearTimeoutStub(id) { pending.delete(id); }
function setIntervalStub() { return ++seq; }
function clearIntervalStub() {}

function fireDueTimers(maxDelay) {
    const due = [...pending.entries()]
        .filter(([, timer]) => timer.delay <= maxDelay)
        .sort((a, b) => a[1].delay - b[1].delay);

    due.forEach(([id, timer]) => {
        pending.delete(id);
        timer.fn();
    });

    return due.length;
}

// ---- minimal DOM -------------------------------------------------------
function matchesSelector(el, selector) {
    if (!el || !selector) return false;

    const first = selector.split(',')[0].trim();

    if (first.startsWith('#')) return el.id === first.slice(1);
    if (first.startsWith('.')) return (el.classes || []).includes(first.slice(1));

    return false;
}

function element(tag, options = {}) {
    const el = {
        tagName: (tag || 'div').toUpperCase(),
        id: options.id || '',
        classes: options.classes || [],
        style: {},
        value: options.value === undefined ? '' : options.value,
        disabled: false,
        innerHTML: '',
        textContent: '',
        children: [],
        parentNode: null,
        listeners: {},
        addEventListener(type, handler) { (el.listeners[type] = el.listeners[type] || []).push(handler); },
        removeEventListener() {},
        appendChild(child) { el.children.push(child); child.parentNode = el; return child; },
        append(...kids) { kids.forEach((kid) => el.appendChild(kid)); },
        remove() {
            if (el.parentNode) {
                el.parentNode.children = el.parentNode.children.filter((child) => child !== el);
                el.parentNode = null;
            }
        },
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
        getAttribute: () => null,
        setAttribute() {},
        classList: {
            add() {}, remove() {}, contains: () => false, toggle: () => false,
        },
    };

    return el;
}

const elementsById = {};
const byId = (id) => (elementsById[id] = elementsById[id] || element('div', { id: id }));

// The container the Retry button is appended to.
const modalBody = byId('__scoringModalBody');

const document = {
    body: element('body'),
    addEventListener() {},
    removeEventListener() {},
    getElementById: (id) => byId(id),
    createElement: (tag) => element(tag),
    querySelector(selector) {
        if (selector.includes('csrf-token')) return { content: 'csrf-token-value' };
        if (selector.includes('.text-center')) return modalBody;
        return null;
    },
    querySelectorAll: () => [],
};

const scoringRequests = [];

// A stock PHP timeout arrives as a 500 with a non-JSON body — exactly the path
// that appends the Retry button.
function failingFetch(url) {
    scoringRequests.push(String(url));

    return Promise.resolve({
        ok: false,
        status: 500,
        json: () => Promise.reject(new SyntaxError('Unexpected token < in JSON')),
    });
}

const bootstrap = {
    Modal: {
        getOrCreateInstance: () => ({ show() {}, hide() {} }),
        getInstance: () => ({ show() {}, hide() {} }),
    },
    Tooltip: class Tooltip {},
};

const window = {
    location: { href: 'http://localhost/academic-head/block/1?period=Midterm', reload() {} },
    addEventListener() {},
    removeEventListener() {},
    alert() {},
    confirm: () => true,
    fetch: () => Promise.resolve(),
    bootstrap,
    Chart: class Chart {},
    setTimeout: setTimeoutStub,
    clearTimeout: clearTimeoutStub,
    setInterval: setIntervalStub,
    clearInterval: clearIntervalStub,
};

const sandbox = {
    window,
    document,
    console,
    setTimeout: setTimeoutStub,
    clearTimeout: clearTimeoutStub,
    setInterval: setIntervalStub,
    clearInterval: clearIntervalStub,
    fetch: failingFetch,
    alert: () => {},
    confirm: () => true,
    bootstrap,
    Chart: window.Chart,
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
    Error,
    TypeError,
    SyntaxError,
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

check('inline script evaluates (no load-time error)', loadError === null,
    loadError ? (loadError.name + ': ' + loadError.message) : 'no throw');

const retryButtons = () => modalBody.children.filter((child) => /Retry/.test(child.innerHTML || ''));
const tick = async (times = 8) => {
    for (let i = 0; i < times; i++) await Promise.resolve();
};

(async () => {
    if (loadError) {
        results.forEach((row) => console.log((row.ok ? 'PASS  ' : 'FAIL  ') + row.name + (row.detail ? '  [' + row.detail + ']' : '')));
        process.exit(1);
    }

    // 1st failure.
    sandbox.runRiskScoring();
    await tick();

    check('failure #1: the scoring request is issued',
        scoringRequests.length === 1, scoringRequests.length + ' request(s)');
    check('failure #1: no Retry button before the reveal delay',
        retryButtons().length === 0, retryButtons().length + ' button(s)');

    fireDueTimers(3000);

    check('failure #1: exactly one Retry button is revealed',
        retryButtons().length === 1, retryButtons().length + ' button(s)');

    // The user clicks Retry → the run restarts and fails again.
    retryButtons()[0].onclick();
    fireDueTimers(500); // 500ms → runRiskScoring()
    await tick();

    check('retry: the run restarts cleanly (stale button cleared)',
        scoringRequests.length === 2 && retryButtons().length === 0,
        scoringRequests.length + ' request(s), ' + retryButtons().length + ' button(s)');

    fireDueTimers(3000);

    check('failure #2: a repeat failure still shows EXACTLY one Retry button',
        retryButtons().length === 1, retryButtons().length + ' button(s)');

    // 3rd cycle, for good measure.
    retryButtons()[0].onclick();
    fireDueTimers(500);
    await tick();
    fireDueTimers(3000);

    check('failure #3: still exactly one Retry button',
        retryButtons().length === 1, retryButtons().length + ' button(s)');

    check('the request only ever targets the scoring endpoint',
        scoringRequests.every((url) => url.includes('/run-scoring')),
        scoringRequests.join(', '));

    check('the modal reports the failure without the raw engine string',
        String(byId('scoringStatus').textContent).includes('Scoring is taking longer than expected.'),
        byId('scoringStatus').textContent);

    let failures = 0;

    results.forEach((row) => {
        if (!row.ok) failures++;
        console.log((row.ok ? 'PASS  ' : 'FAIL  ') + row.name + (row.detail ? '  [' + row.detail + ']' : ''));
    });

    console.log('');
    console.log('scoring requests: ' + scoringRequests.length + ' | Retry buttons at rest: ' + retryButtons().length);
    console.log(failures === 0
        ? 'RESULT: the Retry button never duplicates.'
        : 'RESULT: ' + failures + ' assertion(s) failed.');

    process.exit(failures === 0 ? 0 : 1);
})();
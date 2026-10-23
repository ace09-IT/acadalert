'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const BLADE = path.join(__dirname, '..', '..', 'resources', 'views', 'academic-head', 'risk-scoring.blade.php');

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

function check(name, ok, detail = '') {
    results.push({ name, ok, detail });
}

const docListeners = {};

function element(tag, options = {}) {
    const el = {
        tagName: (tag || 'div').toUpperCase(),
        id: options.id || '',
        style: {},
        disabled: false,
        textContent: options.textContent || '',
        innerHTML: '',
        children: [],
        classes: (options.className || '').split(/\s+/).filter(Boolean),
        attributes: options.attributes || {},
        listeners: {},
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(el.attributes, name) ? el.attributes[name] : null;
        },
        setAttribute(name, value) { el.attributes[name] = value; },
        addEventListener(type, handler) { (el.listeners[type] = el.listeners[type] || []).push(handler); },
        removeEventListener() {},
        appendChild(child) { el.children.push(child); child.parentNode = el; return child; },
        querySelectorAll(selector) {
            if (selector === 'button') return el.children.filter((c) => c.tagName === 'BUTTON');
            return [];
        },
        closest(selector) {
            let node = el;
            while (node) {
                if (selector.startsWith('.') && node.classes.includes(selector.slice(1))) return node;
                if (selector.startsWith('#') && node.id === selector.slice(1)) return node;
                node = node.parentNode;
            }
            return null;
        },
        classList: {
            add: (...names) => names.forEach((n) => { if (!el.classes.includes(n)) el.classes.push(n); }),
            remove: (...names) => { el.classes = el.classes.filter((n) => !names.includes(n)); },
            contains: (n) => el.classes.includes(n),
        },
    };

    return el;
}

const blockRows = [];

[['9'], ['10']].forEach(([id]) => {
    const row = element('tr', { attributes: { 'data-block-id': id, 'data-scored': '0' } });

    const runBtn = element('button', { className: 'btn btn-outline-success run-block-btn' });
    runBtn.attributes['data-block-id'] = id;

    const refreshBtn = element('button', { className: 'btn btn-outline-warning refresh-block-btn' });
    refreshBtn.attributes['data-block-id'] = id;

    row.appendChild(runBtn);
    row.appendChild(refreshBtn);

    blockRows.push(row);
});

const elementsById = {};

function byId(id) {
    if (!elementsById[id]) {
        elementsById[id] = element('div', { id });
    }

    return elementsById[id];
}

const progressCard = byId('runProgressCard');
progressCard.style = { display: 'none' };

const body = element('tbody', { id: 'blockScoringBody' });
body.querySelectorAll = (selector) => (selector.includes('tr[data-block-id]') ? blockRows : []);

const meta = {
    'risk-scoring-period': element('meta', { attributes: { content: 'Midterm' } }),
    'risk-scoring-year': element('meta', { attributes: { content: '2024-2025' } }),
    'csrf-token': element('meta', { attributes: { content: 'csrf' } }),
};

const document = {
    body: element('body'),
    addEventListener(type, handler) { (docListeners[type] = docListeners[type] || []).push(handler); },
    removeEventListener() {},
    getElementById: (id) => byId(id),
    createElement: (tag) => element(tag),
    querySelector(selector) {
        const m = selector.match(/^meta\[name="([^"]+)"\]$/);
        if (m) return meta[m[1]] || null;
        if (selector.startsWith('#')) return byId(selector.slice(1));
        return null;
    },
    querySelectorAll(selector) {
        if (selector === '#blockScoringBody tr[data-block-id]') return blockRows;
        if (selector.startsWith('tr[data-block-id=')) {
            const id = selector.replace(/[^0-9]/g, '');
            return blockRows.filter((r) => r.attributes['data-block-id'] === id);
        }
        return [];
    },
};

const pending = [];
const fetchCallsList = [];

const win = {
    location: { href: 'http://localhost/academic-head/risk-scoring?period=Midterm', reload() {} },
    addEventListener() {},
    removeEventListener() {},
    alert() {},
    confirm: () => true,
    fetch(url, options) {
        const entry = { url: String(url), options: options || {}, resolve: null };

        fetchCallsList.push(entry);

        // Never auto-resolve: the test decides when a request settles, so it can
        // inspect the bar WHILE the request is in flight — the whole point.
        return new Promise((resolve) => {
            entry.resolve = resolve;
            pending.push(entry);
        });
    },
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval,
    console,
    JSON,
    URL,
};

function lastPending(predicate) {
    for (let i = pending.length - 1; i >= 0; i--) {
        if (predicate(pending[i].url)) {
            return i;
        }
    }

    return -1;
}

const isRun = (url) => /\/block\/\d+\/(run|refresh)-scoring$/.test(url);
const isData = (url) => url.indexOf('/academic-head/risk-scoring/data') === 0;

function pendingRun() {
    const i = lastPending(isRun);

    return i === -1 ? null : pending[i];
}

function pendingData() {
    const i = lastPending(isData);

    return i === -1 ? null : pending[i];
}

function countRuns() {
    return fetchCallsList.filter((c) => isRun(c.url)).length;
}

function settleRun(payload) {
    const i = lastPending(isRun);

    if (i === -1) {
        return false;
    }

    pending.splice(i, 1)[0].resolve({ ok: true, status: 200, json: async () => payload });

    return true;
}

function settleData(payload) {
    const i = lastPending(isData);

    if (i === -1) {
        return false;
    }

    const body = payload || {
        success: true,
        summary: { blocks: 2, students: 35 },
        cards_html: '<div class="row" id="scoringCards"><!-- refreshed --></div>',
        rows_html: '<tr data-block-id="9" data-scored="20"><!-- refreshed --></tr>',
    };

    pending.splice(i, 1)[0].resolve({ ok: true, status: 200, json: async () => body });

    return true;
}

const sandbox = {
    window: win,
    document,
    console,
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval,
    fetch: win.fetch,
    alert: win.alert,
    confirm: win.confirm,
    JSON,
    URL,
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
};

sandbox.globalThis = sandbox;

let loadError = null;

try {
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox, { filename: 'risk-scoring-inline.js' });
} catch (error) {
    loadError = error;
}

check('inline script evaluates (no load-time error)', loadError === null,
    loadError ? loadError.name + ': ' + loadError.message : 'no throw');

if (loadError) {
    results.forEach((r) => console.log((r.ok ? 'PASS  ' : 'FAIL  ') + r.name + (r.detail ? '  [' + r.detail + ']' : '')));
    process.exit(1);
}

function click(target) {
    (docListeners['click'] || []).forEach((handler) => {
        handler.call(document, { type: 'click', target, preventDefault() {}, stopPropagation() {} });
    });
}

function barWidth() {
    return String(byId('runProgressBar').style.width || '0%');
}

function barPercent() {
    return parseInt(barWidth().replace('%', ''), 10) || 0;
}

function logText() {
    const log = byId('runProgressLog');

    return String(log.innerHTML || '') + ' ' + log.children.map((c) => c.textContent).join(' ');
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

(async function run() {
    click(blockRows[0].children[0]);

    const firstRun = pendingRun();

    check('per-block Run: request issued to the block run-scoring endpoint',
        !!firstRun && firstRun.url === '/academic-head/block/9/run-scoring',
        firstRun ? firstRun.url : 'no request');

    let posted = null;
    try { posted = JSON.parse(firstRun.options.body); } catch (e) { posted = null; }

    check('per-block Run: payload carries period + school_year',
        posted !== null && posted.period === 'Midterm' && posted.school_year === '2024-2025',
        posted ? JSON.stringify(posted) : 'no body');

    check('per-block Run: progress card revealed, opening value 5%',
        progressCard.style.display === '' && barPercent() === 5,
        'display=' + JSON.stringify(progressCard.style.display) + ' width=' + barWidth());

    // The request is STILL IN FLIGHT — the bar must advance (simulated), exactly
    // like the working block page.
    await wait(1700);

    check('per-block Run: bar ADVANCES while the request is in flight',
        barPercent() > 5,
        'after ~1.7s in flight the bar reads ' + barWidth());

    settleRun({ success: true, processed: 20, batches: 4, ai_calls: 4, cached: false, message: 'ok' });
    await wait(80);

    check('per-block Run: bar reaches 100% after a SUCCESSFUL run',
        barPercent() === 100,
        'bar reads ' + barWidth());

    const refreshReq = pendingData();

    check('per-block Run: an automatic data refresh was requested',
        !!refreshReq,
        refreshReq ? refreshReq.url : 'no refresh request');

    check('per-block Run: the refresh carries the period + school year in view',
        !!refreshReq
            && refreshReq.url.indexOf('period=Midterm') !== -1
            && refreshReq.url.indexOf('school_year=2024-2025') !== -1,
        refreshReq ? refreshReq.url : 'n/a');

    settleData({
        success: true,
        cards_html: '<div class="row" id="scoringCards"><!-- fresh cards --></div>',
        rows_html: '<tr data-block-id="9" data-scored="99"><!-- fresh rows --></tr>',
    });
    await wait(80);

    check('per-block Run: the summary cards were swapped with the fresh markup',
        String(byId('scoringCards').outerHTML || '').indexOf('fresh cards') !== -1,
        'outerHTML=' + String(byId('scoringCards').outerHTML || '').slice(0, 50));

    check('per-block Run: the block rows were swapped with the fresh markup',
        String(byId('blockScoringBody').innerHTML || '').indexOf('fresh rows') !== -1,
        'innerHTML=' + String(byId('blockScoringBody').innerHTML || '').slice(0, 50));

    pending.length = 0;
    click(blockRows[1].children[0]);

    const secondRun = pendingRun();

    check('per-block Run (2nd block): request issued',
        !!secondRun && secondRun.url === '/academic-head/block/10/run-scoring',
        secondRun ? secondRun.url : 'no request');

    settleRun({ success: false, message: 'AI risk scoring unavailable: HTTP 503' });
    await wait(80);

    check('per-block Run: a FAILED run does NOT leave the bar stranded at 5%',
        barPercent() !== 5,
        'bar reads ' + barWidth() + ' after a failed run');

    check('per-block Run: the failure is reported to the user',
        /503/.test(logText()),
        'log captured the error');

    check('per-block Run: a FAILED run still refreshes (refresh-scoring deletes first)',
        !!pendingData(),
        pendingData() ? 'refresh: ' + pendingData().url : 'no refresh request');

    settleData();
    await wait(80);

    pending.length = 0;
    click(byId('runAllBlocksBtn'));

    const sweepFirst = pendingRun();

    check('Run All Blocks: first block requested',
        !!sweepFirst && sweepFirst.url === '/academic-head/block/9/run-scoring',
        sweepFirst ? sweepFirst.url : 'no request');

    check('Run All Blocks: no refresh while the sweep is still running',
        !pendingData(),
        pendingData() ? 'premature refresh: ' + pendingData().url : 'none');

    settleRun({ success: true, processed: 20, batches: 4, ai_calls: 4, cached: false });
    await wait(80);

    const sweepSecond = pendingRun();

    check('Run All Blocks: the SECOND block is requested after the first settles',
        !!sweepSecond && sweepSecond.url === '/academic-head/block/10/run-scoring',
        sweepSecond ? sweepSecond.url : 'never issued the 2nd block');

    check('Run All Blocks: bar reflects OVERALL progress (50% after 1 of 2 blocks)',
        barPercent() === 50,
        'bar reads ' + barWidth() + ' (expected 50%)');

    settleRun({ success: true, processed: 15, batches: 3, ai_calls: 3, cached: false });
    await wait(150);

    check('Run All Blocks: bar completes at 100%', barPercent() === 100, 'bar reads ' + barWidth());

    check('Run All Blocks: the run button is re-enabled at the end',
        byId('runAllBlocksBtn').disabled === false,
        'disabled=' + byId('runAllBlocksBtn').disabled);

    check('Run All Blocks: the sweep refreshes the figures',
        !!pendingData(),
        pendingData() ? pendingData().url : 'no closing refresh');

    settleData();
    await wait(80);

    check('Run All Blocks: the log records the automatic refresh',
        /refreshed automatically/i.test(logText()),
        'log tail captured');

    let failures = 0;

    results.forEach((row) => {
        if (!row.ok) failures++;
        console.log((row.ok ? 'PASS  ' : 'FAIL  ') + row.name + (row.detail ? '  [' + row.detail + ']' : ''));
    });

    console.log('');
    console.log('scoring requests: ' + countRuns() + ' | data refreshes: '
        + fetchCallsList.filter((c) => isData(c.url)).length);
    console.log(failures === 0
        ? 'RESULT: the bar reaches a terminal state and the figures refresh automatically.'
        : 'RESULT: ' + failures + ' assertion(s) failed.');

    process.exit(failures === 0 ? 0 : 1);
})();
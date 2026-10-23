@extends('layouts.app')

@section('title', 'Risk Scoring - AcadAlert')

@section('page_title', 'Risk Scoring')

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-primary text-white p-2">
            <i class="fas fa-building me-1"></i> {{ $departmentCode ?: 'Department' }}
        </span>

        <form method="GET" action="{{ route('academic-head.riskScoring') }}" class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" name="period" onchange="this.form.submit()" aria-label="Grading period">
                @foreach($periods as $p)
                    <option value="{{ $p }}" @selected($period === $p)>{{ $p }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" name="school_year" onchange="this.form.submit()" aria-label="School year">
                <option value="{{ $schoolYear }}" @selected(true)>{{ $schoolYear }}</option>
            </select>
        </form>

        <button type="button" class="btn btn-sm btn-success" id="runAllBlocksBtn"
                {{ count($blocks) === 0 ? 'disabled' : '' }}>
            <i class="fas fa-play me-1"></i> Run All Blocks
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPageBtn">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<meta name="risk-scoring-period" content="{{ $period }}">
<meta name="risk-scoring-year" content="{{ $schoolYear }}">

<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

<div class="ah-reveal" style="--ah-i: 0;">
    @include('academic-head.partials.scoring-cards', ['summary' => $summary])
</div>

<div class="card mb-4" id="runProgressCard" style="display: none;">
    <div class="card-header bg-primary text-white">
        <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" class="scoring-mark">
        <span class="run-title">Scoring Run in Progress</span>
        <span class="run-status-dot" aria-hidden="true"></span>
        <span class="badge bg-light text-primary" id="runProgressStatus">Starting...</span>
    </div>
    <div class="card-body">
        <div class="progress run-progress mb-3">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="runProgressBar"
                 style="width: 0%;" role="progressbar" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>
        <p class="small text-muted mb-0" id="runProgressDetail">
            Scores are produced in batches of five with a four-second pause between batches, so a whole
            department takes a few minutes. Leave this page open.
        </p>
        <div id="runProgressLog" class="mt-3 small" style="max-height: 220px; overflow-y: auto;"></div>
    </div>
</div>

<div class="card mb-4 ah-glow ah-reveal" style="--ah-i: 1;">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            <i class="fas fa-layer-group text-primary"></i> Blocks
            <span class="badge bg-secondary ms-2">{{ count($blocks) }}</span>
        </span>
        <span class="text-muted small">Fresh &middot; Stale &middot; Never scored, for {{ $period }} {{ $schoolYear }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-mobile-cards" id="blockScoringTable">
                <thead>
                    <tr>
                        <th>Block</th>
                        <th class="text-end">Students</th>
                        <th class="text-end">Scored</th>
                        <th style="min-width: 130px;">Coverage</th>
                        <th class="text-end">High</th>
                        <th class="text-end">Moderate</th>
                        <th class="text-end">Low</th>
                        <th>Last Scored</th>
                        <th>Cache</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="blockScoringBody">
                    @include('academic-head.partials.scoring-rows', ['blocks' => $blocks, 'period' => $period, 'schoolYear' => $schoolYear])
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const periodMeta = document.querySelector('meta[name="risk-scoring-period"]');
        const yearMeta = document.querySelector('meta[name="risk-scoring-year"]');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');

        const period = periodMeta ? periodMeta.getAttribute('content') : 'Midterm';
        const schoolYear = yearMeta ? yearMeta.getAttribute('content') : '2024-2025';
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        const progressCard = document.getElementById('runProgressCard');
        const progressBar = document.getElementById('runProgressBar');
        const progressStatus = document.getElementById('runProgressStatus');
        const progressDetail = document.getElementById('runProgressDetail');
        const progressLog = document.getElementById('runProgressLog');

        let progressTimer = null;

        function stopProgressAnimation() {
            if (progressTimer !== null) {
                window.clearInterval(progressTimer);
                progressTimer = null;
            }
        }

        function beginProgress(base, ceiling, status, detail) {
            stopProgressAnimation();

            if (progressBar) {
                progressBar.className = 'progress-bar bg-primary progress-bar-striped progress-bar-animated';
            }

            setProgress(base, status, detail);

            const top = Math.max(base, Math.min(95, ceiling));

            progressTimer = window.setInterval(function () {
                const current = parseFloat(String(progressBar && progressBar.style.width).replace('%', '')) || 0;

                if (current >= top) {
                    return;
                }

                // Slow down near the ceiling so the bar visibly waits for the server
                // instead of appearing to finish on its own.
                const step = current < 60 ? 3 : 1;

                setProgress(Math.min(top, current + step));
            }, 800);
        }

        function settleProgress(success, target, status, detail) {
            stopProgressAnimation();

            if (progressBar) {
                progressBar.className = success
                    ? 'progress-bar bg-success'
                    : 'progress-bar bg-danger';
            }

            setProgress(target, status, detail);

            if (progressBar && !success) {
                progressBar.textContent = 'Failed';
            }
        }

        const csrfHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        };

        function logLine(message, tone) {
            if (!progressLog) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'py-1 border-bottom '
                + (tone === 'error' ? 'text-danger' : (tone === 'success' ? 'text-success' : 'text-muted'));
            row.textContent = message;
            progressLog.appendChild(row);
            progressLog.scrollTop = progressLog.scrollHeight;
        }

        function setProgress(percentage, status, detail) {
            if (progressCard) {
                progressCard.style.display = '';
            }

            if (progressBar) {
                const rounded = Math.max(0, Math.min(100, Math.round(percentage)));
                progressBar.style.width = rounded + '%';
                progressBar.textContent = rounded + '%';
            }

            if (progressStatus && status) {
                progressStatus.textContent = status;
            }

            if (progressDetail && detail) {
                progressDetail.textContent = detail;
            }
        }

        function scoreBlock(blockId, refresh) {
            const url = refresh
                ? '/academic-head/block/' + blockId + '/refresh-scoring'
                : '/academic-head/block/' + blockId + '/run-scoring';

            return window.fetch(url, {
                method: 'POST',
                headers: csrfHeaders,
                body: JSON.stringify({ period: period, school_year: schoolYear }),
            }).then(function (response) {
                return response.json();
            });
        }

        function markRowBusy(blockId, busy) {
            const row = document.querySelector('tr[data-block-id="' + blockId + '"]');

            if (!row) {
                return;
            }

            const buttons = row.querySelectorAll('button');

            for (let i = 0; i < buttons.length; i++) {
                buttons[i].disabled = busy;
            }
        }

        function refreshWorkspace() {
            const url = '/academic-head/risk-scoring/data'
                + '?period=' + encodeURIComponent(period)
                + '&school_year=' + encodeURIComponent(schoolYear);

            return window.fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        throw new Error((data && data.message) || 'the server rejected the request');
                    }

                    const cards = document.getElementById('scoringCards');

                    if (cards && data.cards_html) {
                        cards.outerHTML = data.cards_html;
                    }

                    const tableBody = document.getElementById('blockScoringBody');

                    if (tableBody && data.rows_html) {
                        tableBody.innerHTML = data.rows_html;
                    }

                    logLine('Displayed figures refreshed automatically — coverage, risk counts and cache state are current.', 'muted');

                    return data;
                })
                .catch(function (error) {
                    // The scores are already saved, so this must not read as a failed run.
                    logLine('Automatic refresh failed (' + (error && error.message ? error.message : error)
                        + '). The scores are saved; use Refresh to reload the page.', 'error');

                    return null;
                });
        }

        function runSingleBlock(blockId, refresh, options) {
            const label = 'Block ' + blockId;
            const settings = options || {};
            const base = typeof settings.base === 'number' ? settings.base : 5;
            const overall = typeof settings.overall === 'number' ? settings.overall : 100;

            beginProgress(base, Math.max(base + 10, overall - 10),
                refresh ? 'Refreshing ' + label : 'Scoring ' + label,
                'The AI batch engine is working through this block. Batches run four seconds apart.');

            markRowBusy(blockId, true);

            return scoreBlock(blockId, refresh)
                .then(function (data) {
                    const ok = !!(data && data.success);

                    if (ok) {
                        logLine(label + ': ' + (data.processed || 0) + ' student(s) scored'
                            + (data.cached ? ' (served from cache)' : '')
                            + (data.batches ? ', ' + data.batches + ' batch(es)' : '')
                            + ', ' + (data.ai_calls || 0) + ' AI call(s).'
                            + (data.message ? ' ' + data.message : ''), 'success');

                        settleProgress(true, overall, label + ' complete',
                            (data.processed || 0) + ' student(s) scored.');
                    } else {
                        const message = (data && data.message) || 'unknown failure';

                        logLine(label + ': ' + message, 'error');

                        settleProgress(false, overall, label + ' failed', message);
                    }

                    return data;
                })
                .catch(function (error) {
                    logLine(label + ': request failed - ' + error.message, 'error');
                    settleProgress(false, overall, label + ' failed', 'Request failed: ' + error.message);

                    return null;
                })
                .then(function (data) {
                    markRowBusy(blockId, false);

                    // A sweep refreshes ONCE at the end instead of after every block —
                    // the intermediate states are never seen by anyone.
                    if (settings.autoRefresh === false) {
                        return data;
                    }

                    return refreshWorkspace().then(function () {
                        return data;
                    });
                });
        }

        function runAllBlocks() {
            const button = document.getElementById('runAllBlocksBtn');
            const rows = Array.prototype.slice.call(document.querySelectorAll('#blockScoringBody tr[data-block-id]'));

            if (rows.length === 0) {
                window.alert('There are no blocks to score in this department.');
                return;
            }

            const ids = [];

            for (let i = 0; i < rows.length; i++) {
                const parsed = parseInt(rows[i].getAttribute('data-block-id'), 10);

                if (!isNaN(parsed)) {
                    ids.push(parsed);
                }
            }

            if (button) {
                button.disabled = true;
            }

            if (progressLog) {
                progressLog.innerHTML = '';
            }

            logLine('Starting a ' + ids.length + '-block run for ' + period + ' ' + schoolYear + '.', 'muted');

            let index = 0;
            let scored = 0;
            let failed = 0;

            function next() {
                if (index >= ids.length) {
                    settleProgress(failed === 0, 100, 'Run complete',
                        scored + ' block(s) scored, ' + failed + ' failed. Refreshing the figures…');
                    logLine('Run complete: ' + scored + ' scored, ' + failed + ' failed.',
                        failed > 0 ? 'error' : 'success');

                    if (button) {
                        button.disabled = false;
                    }

                    refreshWorkspace();

                    return;
                }

                const blockId = ids[index];

                runSingleBlock(blockId, false, {
                    base: (index / ids.length) * 100,
                    overall: ((index + 1) / ids.length) * 100,
                    autoRefresh: false,
                }).then(function (data) {
                    if (data && data.success) {
                        scored++;
                    } else {
                        failed++;
                    }

                    index++;
                    next();
                });
            }

            next();
        }

        document.addEventListener('click', function (event) {
            const target = event.target;

            if (!target || typeof target.closest !== 'function') {
                return;
            }

            const runBtn = target.closest('.run-block-btn');

            if (runBtn) {
                event.preventDefault();
                runSingleBlock(runBtn.getAttribute('data-block-id'), false);
                return;
            }

            const refreshBtn = target.closest('.refresh-block-btn');

            if (refreshBtn) {
                event.preventDefault();
                runSingleBlock(refreshBtn.getAttribute('data-block-id'), true);
                return;
            }

            if (target.closest('#runAllBlocksBtn')) {
                event.preventDefault();
                runAllBlocks();
                return;
            }

            if (target.closest('#refreshPageBtn')) {
                event.preventDefault();
                window.location.reload();
            }
        });
    })();
</script>
@endpush
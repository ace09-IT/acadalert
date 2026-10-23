@php
@endphp
<div class="row" id="scoringCards">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Blocks in Department</div>
                    <div class="stat-number">{{ number_format((int) $summary['blocks']) }}</div>
                    <small class="opacity-75">{{ number_format((int) $summary['students']) }} active students</small>
                </div>
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Score Coverage</div>
                    <div class="stat-number">{{ number_format((float) $summary['percentage'], 1) }}%</div>
                    <small class="opacity-75">{{ number_format((int) $summary['scored']) }} scored &middot; {{ number_format((int) $summary['pending']) }} pending</small>
                </div>
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk</div>
                    <div class="stat-number">{{ number_format((int) $summary['high']) }}</div>
                    <small class="opacity-75">{{ number_format((int) $summary['moderate']) }} moderate &middot; {{ number_format((int) $summary['low']) }} low</small>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Cache Status</div>
                    <div class="stat-number">{{ number_format((int) $summary['fresh']) }}</div>
                    <small class="opacity-75">
                        fresh &middot; {{ number_format((int) $summary['stale']) }} stale &middot; {{ number_format((int) $summary['never']) }} never scored
                    </small>
                </div>
                <div class="stat-icon"><i class="fas fa-database"></i></div>
            </div>
        </div>
    </div>
</div>

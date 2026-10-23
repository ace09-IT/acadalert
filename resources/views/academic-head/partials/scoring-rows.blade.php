@forelse($blocks as $block)
    <tr data-block-id="{{ $block->block_id }}" data-scored="{{ $block->scored }}">
        <td data-label="Block">
            <div class="fw-semibold">
                <a href="{{ route('academic-head.block', ['blockId' => $block->block_id, 'period' => $period, 'school_year' => $schoolYear]) }}">
                    {{ $block->label !== '' ? $block->label : ('Block ' . $block->block_id) }}
                </a>
            </div>
            @if((int) $block->unacknowledged_flags > 0)
                <small class="badge bg-warning text-dark" title="Flags not acknowledged by the counselor">
                    {{ number_format((int) $block->unacknowledged_flags) }} flag(s)
                </small>
            @endif
        </td>
        <td class="text-end" data-label="Students">{{ number_format((int) $block->students) }}</td>
        <td class="text-end" data-label="Scored">{{ number_format((int) $block->scored) }}</td>
        <td data-label="Coverage">
            <div class="progress" style="height: 16px;">
                <div class="progress-bar {{ $block->pending > 0 ? 'bg-warning' : 'bg-success' }}"
                     style="width: {{ $block->percentage }}%;"
                     title="{{ number_format((float) $block->percentage, 1) }}%">{{ number_format((float) $block->percentage, 1) }}%</div>
            </div>
        </td>
        <td class="text-end" data-label="High">
            <span class="badge badge-risk-high">{{ number_format((int) $block->high) }}</span>
        </td>
        <td class="text-end" data-label="Moderate">
            <span class="badge badge-risk-moderate">{{ number_format((int) $block->moderate) }}</span>
        </td>
        <td class="text-end" data-label="Low">
            <span class="badge badge-risk-low">{{ number_format((int) $block->low) }}</span>
        </td>
        <td data-label="Last Scored">
            <small class="text-muted">{{ $block->last_scored_at ?? 'never' }}</small>
        </td>
        <td data-label="Cache">
            @if($block->cache_state === 'fresh')
                <span class="badge bg-success" title="Cached {{ $block->cache_age_minutes }} minute(s) ago">
                    <i class="fas fa-bolt me-1"></i> Fresh ({{ number_format((int) $block->cache_age_minutes) }}m)
                </span>
            @elseif($block->cache_state === 'stale')
                <span class="badge bg-warning text-dark" title="Cached {{ $block->cache_age_minutes }} minute(s) ago">
                    <i class="fas fa-hourglass-half me-1"></i> Stale ({{ number_format((int) $block->cache_age_minutes) }}m)
                </span>
            @else
                <span class="badge bg-secondary" title="No cached payload for this period">
                    <i class="fas fa-minus me-1"></i> None
                </span>
            @endif
        </td>
        <td class="text-end" data-label="Action">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success run-block-btn"
                        data-block-id="{{ $block->block_id }}"
                        title="Run AI risk scoring for this block">
                    <i class="fas fa-play"></i>
                </button>
                <button type="button" class="btn btn-outline-warning refresh-block-btn"
                        data-block-id="{{ $block->block_id }}"
                        title="Delete this block's scores and flags, then score again"
                        {{ (int) $block->scored === 0 ? 'disabled' : '' }}>
                    <i class="fas fa-arrows-rotate"></i>
                </button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="10" class="text-center text-muted py-4">
            <i class="fas fa-layer-group fa-2x d-block mb-2"></i>
            No blocks found in this department.
        </td>
    </tr>
@endforelse

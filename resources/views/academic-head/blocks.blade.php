@extends('layouts.app')

@php
    $isDepartmentWide = $program === null;
    $grouped = $groupedBlocks ?? [];
    $totalBlocks = count($blocks);
@endphp

@section('title', $isDepartmentWide ? 'Blocks - AcadAlert' : ($program->code . ' Blocks - AcadAlert'))

@section('page_title', $isDepartmentWide ? 'Blocks' : ('Blocks - ' . $program->code))

@section('page_actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-secondary text-white p-2">
            <i class="fas fa-layer-group me-1"></i> {{ $totalBlocks }} block(s)
        </span>

        <form method="GET" action="{{ $isDepartmentWide ? route('academic-head.blocks.index') : route('academic-head.blocks', ['programId' => $program->id]) }}"
              class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" name="period" onchange="this.form.submit()" aria-label="Grading period">
                @foreach(['Prelim', 'Midterm', 'Finals'] as $p)
                    <option value="{{ $p }}" @selected($currentPeriod === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </form>

        @if(!$isDepartmentWide)
            <a href="{{ route('academic-head.blocks.index', ['period' => $currentPeriod]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-layer-group me-1"></i> All Blocks
            </a>
        @endif

        <a href="{{ route('academic-head.department', ['period' => $currentPeriod]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Department Overview
        </a>
    </div>
@endsection

@section('content')
@php
    $blockCardMeta = function ($block) {
        $students = (int) ($block->total_students ?? 0);
        $high = (int) ($block->high_risk ?? 0);
        $highPercent = (float) ($block->high_risk_percentage ?? 0);
        $tone = $highPercent > 15 ? 'danger' : ($highPercent > 8 ? 'warning' : 'success');

        return (object) [
            'students' => $students,
            'high' => $high,
            'highPercent' => $highPercent,
            'tone' => $tone,
        ];
    };
@endphp

<div class="ah-page" style="--ah-photo: url('{{ asset('images/backgrounds/bg_smll_udd.jpg') }}')">

@if($isDepartmentWide)
    {{-- Department-wide: one card group per program, so the head sees where the risk
         is concentrated without walking program by program. --}}
    @forelse($grouped as $programCode => $programBlocks)
        <div class="card mb-4 ah-glow" style="--ah-i: {{ $loop->index }};">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="fas fa-sitemap text-primary me-2"></i> {{ $programCode }}
                    <span class="badge bg-secondary ms-2">{{ count($programBlocks) }} block(s)</span>
                </span>
                @php $programId = $programBlocks[0]->program_id ?? null; @endphp
                @if($programId)
                    <a href="{{ route('academic-head.blocks', ['programId' => $programId, 'period' => $currentPeriod]) }}"
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-filter me-1"></i> Program detail
                    </a>
                @endif
            </div>
            <div class="card-body">
                <div class="row ah-reveal">
                    @foreach($programBlocks as $block)
                        @php $meta = $blockCardMeta($block); @endphp
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 shadow-sm border-{{ $meta->tone }} ah-glow">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1">{{ $block->name }}</h5>
                                            <small class="text-muted">Year {{ $block->year_number ?? '?' }}</small>
                                        </div>
                                        <span class="badge bg-primary">{{ number_format($meta->students) }} students</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <div class="text-center">
                                            <span class="badge badge-risk-low">{{ number_format((int) ($block->low_risk ?? 0)) }}</span>
                                            <small class="text-muted d-block">Low</small>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge badge-risk-moderate">{{ number_format((int) ($block->moderate_risk ?? 0)) }}</span>
                                            <small class="text-muted d-block">Moderate</small>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge badge-risk-high">{{ number_format($meta->high) }}</span>
                                            <small class="text-muted d-block">High</small>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-{{ $meta->tone }}">
                                        <i class="fas fa-percentage me-1"></i>{{ number_format($meta->highPercent, 1) }}% high risk
                                    </div>
                                    <a href="{{ route('academic-head.block', ['blockId' => $block->id, 'period' => $currentPeriod, 'school_year' => $schoolYear]) }}"
                                       class="btn btn-sm btn-outline-primary w-100 mt-3">
                                        <i class="fas fa-eye me-1"></i> View Block
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="card mb-4 ah-glow">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-layer-group fa-2x d-block mb-2"></i>
                No blocks are placed in this department yet.
            </div>
        </div>
    @endforelse
@else
    <div class="card mb-4 ah-glow">
        <div class="card-header">
            <i class="fas fa-layer-group text-primary me-2"></i>
            Blocks - {{ $program->name ?? 'Program' }}
            <span class="badge bg-secondary ms-2">{{ $currentPeriod }}</span>
        </div>
        <div class="card-body">
            <div class="row ah-reveal" style="--ah-i: 0;">
                @forelse($blocks as $block)
                    @php $meta = $blockCardMeta($block); @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm border-{{ $meta->tone }} ah-glow">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title mb-1">{{ $block->name }}</h5>
                                        <small class="text-muted">{{ $block->year_level_name ?? ('Year ' . ($block->year_number ?? '?')) }}</small>
                                    </div>
                                    <span class="badge bg-primary">{{ number_format($meta->students) }} students</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <div class="text-center">
                                        <span class="badge badge-risk-low">{{ number_format((int) ($block->low_risk ?? 0)) }}</span>
                                        <small class="text-muted d-block">Low</small>
                                    </div>
                                    <div class="text-center">
                                        <span class="badge badge-risk-moderate">{{ number_format((int) ($block->moderate_risk ?? 0)) }}</span>
                                        <small class="text-muted d-block">Moderate</small>
                                    </div>
                                    <div class="text-center">
                                        <span class="badge badge-risk-high">{{ number_format($meta->high) }}</span>
                                        <small class="text-muted d-block">High</small>
                                    </div>
                                </div>
                                <div class="mt-2 small text-{{ $meta->tone }}">
                                    <i class="fas fa-percentage me-1"></i>{{ number_format($meta->highPercent, 1) }}% high risk
                                </div>
                                <a href="{{ route('academic-head.block', ['blockId' => $block->id, 'period' => $currentPeriod, 'school_year' => $schoolYear]) }}"
                                   class="btn btn-sm btn-outline-primary w-100 mt-3">
                                    <i class="fas fa-eye me-1"></i> View Block
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-info-circle me-2"></i> No blocks found for this program.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif
</div>
@endsection

<?php

namespace App\Http\Controllers\AcademicHead;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Services\InterventionService;
use App\Services\RiskScoringCacheService;
use App\Services\RiskScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskScoringController extends Controller
{
    protected $riskService;

    public function __construct(RiskScoringService $riskService)
    {
        $this->riskService = $riskService;
    }
    public function index(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        $structure = app(\App\Repositories\Api\AcademicStructureRepository::class);

        $department = $structure->department($departmentId);

        $period = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = (string) $request->input('school_year', '2024-2025');
        $semester = (string) $request->input('semester', '1st');

        $workspace = $this->buildWorkspace($departmentId, $period, $schoolYear);

        return view('academic-head.risk-scoring', [
            'department' => $department,
            'departmentName' => $department->name ?? 'Department',
            'departmentCode' => $department->code ?? '',
            'blocks' => $workspace['blocks'],
            'summary' => $workspace['summary'],
            'period' => $period,
            'periods' => InterventionService::GRADING_PERIODS,
            'schoolYear' => $schoolYear,
            'semester' => $semester,
        ]);
    }

    public function data(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);

        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found for this user.',
            ], 403);
        }

        $period = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = (string) $request->input('school_year', '2024-2025');

        $workspace = $this->buildWorkspace($departmentId, $period, $schoolYear);

        return response()->json([
            'success' => true,
            'period' => $period,
            'school_year' => $schoolYear,
            'summary' => $workspace['summary'],
            'cards_html' => view('academic-head.partials.scoring-cards', [
                'summary' => $workspace['summary'],
            ])->render(),
            'rows_html' => view('academic-head.partials.scoring-rows', [
                'blocks' => $workspace['blocks'],
                'period' => $period,
                'schoolYear' => $schoolYear,
            ])->render(),
        ]);
    }

    protected function buildWorkspace(int $departmentId, string $period, string $schoolYear): array
    {
        $structure = app(\App\Repositories\Api\AcademicStructureRepository::class);
        $riskScores = app(\App\Repositories\Local\RiskScoreRepository::class);

        $cacheTtlMinutes = (int) config('services.aistudio.cache_ttl_minutes', 60);

        $cacheRows = DB::table('risk_score_cache')
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('block_id');

        $levelsByStudent = $riskScores->levelByStudent($period, $schoolYear);

        $blocks = [];
        $summary = [
            'blocks' => 0,
            'students' => 0,
            'scored' => 0,
            'pending' => 0,
            'percentage' => 0.0,
            'high' => 0,
            'moderate' => 0,
            'low' => 0,
            'fresh' => 0,
            'stale' => 0,
            'never' => 0,
        ];



        foreach ($structure->blocksWithProgramForDepartment($departmentId) as $block) {
            $studentIds = $structure->studentIdsInBlock($block->id, true);

            $counts = ['Low' => 0, 'Moderate' => 0, 'High' => 0];
            foreach ($studentIds as $studentId) {
                $level = $levelsByStudent[(int) $studentId] ?? null;

                if ($level !== null && isset($counts[$level])) {
                    $counts[$level]++;
                }
            }

            $students = count($studentIds);
            $scored = $counts['Low'] + $counts['Moderate'] + $counts['High'];

            $cacheRow = $cacheRows->get($block->id);
            $cacheState = 'never';
            $cacheAgeMinutes = null;

            if ($cacheRow !== null) {
                $cachedAt = \Carbon\Carbon::parse($cacheRow->created_at);
                $cacheAgeMinutes = (int) $cachedAt->diffInMinutes(now());
                $cacheState = $cacheAgeMinutes <= $cacheTtlMinutes ? 'fresh' : 'stale';
            }

            $lastScoredAt = $studentIds === []
                ? null
                : DB::table('risk_scores')
                    ->whereIn('student_id', $studentIds)
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->max('updated_at');

            $unacknowledgedFlags = $studentIds === []
                ? 0
                : DB::table('flags')
                    ->whereIn('student_id', $studentIds)
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->where('is_acknowledged', false)
                    ->count();

            $blocks[] = (object) [
                'block_id' => (int) $block->id,
                'label' => trim(implode(' - ', array_filter([
                    $block->program_code ?? null,
                    isset($block->year_number) ? 'Year ' . $block->year_number : null,
                    $block->name ?? null,
                ]))),
                'program_code' => (string) ($block->program_code ?? ''),
                'name' => (string) ($block->name ?? ''),
                'year_number' => $block->year_number,
                'students' => $students,
                'scored' => $scored,
                'pending' => max(0, $students - $scored),
                'percentage' => $students > 0 ? round($scored / $students * 100, 1) : 0.0,
                'high' => $counts['High'],
                'moderate' => $counts['Moderate'],
                'low' => $counts['Low'],
                'cache_state' => $cacheState,
                'cache_age_minutes' => $cacheAgeMinutes,
                'last_scored_at' => $lastScoredAt,
                'unacknowledged_flags' => $unacknowledgedFlags,
            ];

            $summary['blocks']++;
            $summary['students'] += $students;
            $summary['scored'] += $scored;
            $summary['pending'] += max(0, $students - $scored);
            $summary['high'] += $counts['High'];
            $summary['moderate'] += $counts['Moderate'];
            $summary['low'] += $counts['Low'];
            $summary[$cacheState === 'fresh' ? 'fresh' : ($cacheState === 'stale' ? 'stale' : 'never')]++;
        }

        $summary['percentage'] = $summary['students'] > 0
            ? round($summary['scored'] / $summary['students'] * 100, 1)
            : 0.0;

        usort($blocks, fn ($a, $b) => [$b->high, $a->name] <=> [$a->high, $b->name]);

        return ['blocks' => $blocks, 'summary' => $summary];
    }



    public function runScoring(Request $request, int $blockId)
    {
        // A whole block is scored synchronously in this one request, so lift the
        // PHP execution ceiling here — and only here. A fresh install's default
        // 30s cannot cover the 4s-per-batch cadence plus AI latency.
        $this->extendAiExecutionTime();

        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $gradingPeriod = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = $request->input('school_year', '2024-2025');

        try {
            $result = $this->riskService->processBlock($blockId, $gradingPeriod, $schoolYear);
        } catch (\App\Exceptions\AIStudioApiException $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI risk scoring unavailable: ' . $e->getMessage(),
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Risk scoring failed: ' . $e->getMessage(),
            ], 500);
        }

        $this->generateFlags($result, $gradingPeriod, $schoolYear);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'processed' => $result['processed'] ?? 0,
            'cached' => $result['cached'] ?? false,
            'batches' => $result['batches'] ?? 0,
            'ai_calls' => $result['ai_calls'] ?? 0,
            'students' => $result['students'] ?? [],
        ]);
    }

    public function getScoringStatus(Request $request, int $blockId)
    {
        $gradingPeriod = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = $request->input('school_year', '2024-2025');

        $total = app(StudentRepository::class)->activeCountForBlock($blockId);

        $processed = app(RiskScoreRepository::class)->countFor(
            app(\App\Repositories\Api\AcademicStructureRepository::class)->studentIdsInBlock($blockId, true),
            $gradingPeriod,
            $schoolYear
        );

        // Cache freshness + last write, so the caller can say "cached 3 minutes ago"
        // instead of only "processed 20 of 20".
        $cacheTtlMinutes = (int) config('services.aistudio.cache_ttl_minutes', 60);

        $cacheRow = DB::table('risk_score_cache')
            ->where('block_id', $blockId)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->first();

        $cacheState = 'never';
        $cacheAgeMinutes = null;

        if ($cacheRow !== null) {
            $cacheAgeMinutes = (int) \Carbon\Carbon::parse($cacheRow->created_at)->diffInMinutes(now());
            $cacheState = $cacheAgeMinutes <= $cacheTtlMinutes ? 'fresh' : 'stale';
        }

        $blockStudentIds = app(\App\Repositories\Api\AcademicStructureRepository::class)
            ->studentIdsInBlock($blockId, true);

        $lastScoredAt = $blockStudentIds === []
            ? null
            : DB::table('risk_scores')
                ->whereIn('student_id', $blockStudentIds)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->max('updated_at');


        return response()->json([
            'total' => $total,
            'processed' => $processed,
            'pending' => $total - $processed,
            'percentage' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'success' => true,
            'has_scores' => $processed > 0,
            'status' => $processed === 0 ? 'idle' : ($processed >= $total ? 'complete' : 'partial'),
            'cached' => $cacheRow !== null,
            'cache_state' => $cacheState,
            'cache_age_minutes' => $cacheAgeMinutes,
            'cache_ttl_minutes' => $cacheTtlMinutes,
            'last_scored_at' => $lastScoredAt,
            'grading_period' => $gradingPeriod,
            'school_year' => $schoolYear,
        ]);
    }

    public function refreshScoring(Request $request, int $blockId)
    {
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $gradingPeriod = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = $request->input('school_year', '2024-2025');

        // Invalidate the local DB cache so the next trigger re-scores fresh
        // (a stale payload would otherwise resurrect the deleted scores).
        (new RiskScoringCacheService())->forget($blockId, $gradingPeriod, $schoolYear);

        $blockStudentIds = app(StudentRepository::class)->forBlock($blockId, false)->pluck('id')->toArray();

        $deletedRiskScores = DB::table('risk_scores')
            ->whereIn('student_id', $blockStudentIds)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();

        $deletedFlags = DB::table('flags')
            ->whereIn('student_id', $blockStudentIds)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();

        // Also delete intervention recommendations for this block — constrained to the
        // SAME period, so purging Prelim cannot wipe the Midterm/Finals advice.
        DB::table('intervention_recommendations')
            ->whereIn('student_id', $blockStudentIds)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted $deletedRiskScores risk score records and $deletedFlags flags.",
            'deleted_risk_scores' => $deletedRiskScores,
            'deleted_flags' => $deletedFlags,
        ]);
    }

    protected function generateFlags(array $result, string $period, string $schoolYear): void
    {
        if (!$result['success'] || empty($result['students'])) {
            return;
        }

        foreach ($result['students'] as $student) {
            if ($student['risk_level'] === 'High') {
                $previousRisk = DB::table('risk_scores')
                    ->where('student_id', $student['student_id'])
                    ->where('grading_period', $this->getPreviousPeriod($period))
                    ->where('school_year', $schoolYear)
                    ->first();

                $consecutiveCount = 1;
                if ($previousRisk && $previousRisk->risk_level === 'High') {
                    $consecutiveCount = 2;
                    
                    $exists = DB::table('flags')
                        ->where('student_id', $student['student_id'])
                        ->where('grading_period', $period)
                        ->where('school_year', $schoolYear)
                        ->exists();

                    if (!$exists) {
                        DB::table('flags')->insert([
                            'student_id' => $student['student_id'],
                            'grading_period' => $period,
                            'school_year' => $schoolYear,
                            'semester' => '1st',
                            'flag_type' => 'consecutive_high_risk',
                            'severity' => 'critical',
                            'is_acknowledged' => false,
                            'consecutive_periods_count' => $consecutiveCount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    $exists = DB::table('flags')
                        ->where('student_id', $student['student_id'])
                        ->where('grading_period', $period)
                        ->where('school_year', $schoolYear)
                        ->where('flag_type', 'high_risk')
                        ->exists();

                    if (!$exists) {
                        DB::table('flags')->insert([
                            'student_id' => $student['student_id'],
                            'grading_period' => $period,
                            'school_year' => $schoolYear,
                            'semester' => '1st',
                            'flag_type' => 'high_risk',
                            'severity' => 'high',
                            'is_acknowledged' => false,
                            'consecutive_periods_count' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    protected function getPreviousPeriod(string $current): string
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : '';
    }
}
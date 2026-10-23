<?php

namespace App\Http\Controllers\AcademicHead;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use App\Rules\ApiExists;
use App\Services\InterventionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterventionController extends Controller
{
    protected $interventionService;

    public function __construct(InterventionService $interventionService)
    {
        $this->interventionService = $interventionService;
    }

    public function index(Request $request)
    {
        $blockId = $request->input('block_id');
        $programId = $request->input('program_id');

        $period = InterventionService::normalizePeriod($request->input('period'));
        $schoolYear = InterventionService::SCHOOL_YEAR;

        $departmentId = DepartmentHelper::getDepartmentId($request);
        // blocks -> year_levels -> programs now resolve through the API placement
        // index instead of a three-table join.
        $blocks = app(AcademicStructureRepository::class)
            ->blocksWithProgramForDepartment($departmentId);
        
        $selectedBlock = null;
        $students = [];
        $recommendations = [];
        $riskLevels = [];
        $sortedStudents = [];
        // student_id => live case status, so the table can show whether a
        // recommendation has already been acted upon (escalated) or is still pending.
        $escalationStates = [];
        $pendingCount = 0;
        $escalatedCount = 0;
        
        if ($blockId) {
            if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
                return redirect()->route('academic-head.department')->with('error', 'You do not have access to this block.');
            }
            
            $selectedBlock = app(AcademicStructureRepository::class)->blockWithProgram($blockId);
            
            $students = app(StudentRepository::class)->forBlock($blockId, true);
            
            $studentIds = $students->pluck('id')->toArray();
            if (!empty($studentIds)) {
                $recommendations = DB::table('intervention_recommendations')
                    ->whereIn('student_id', $studentIds)
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('student_id');

                // Risk level for the SAME period — one grouped query instead of a
                // per-row lookup inside the table loop (which was hard-coded to Midterm).
                $riskLevels = DB::table('risk_scores')
                    ->whereIn('student_id', $studentIds)
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->pluck('risk_level', 'student_id')
                    ->toArray();
            }
            
            $sortedStudents = $students->sortByDesc(function ($student) use ($recommendations) {
                $hasRec = isset($recommendations[$student->id]) && $recommendations[$student->id]->count() > 0;
                
                if ($hasRec) {
                    $latestRec = $recommendations[$student->id]->first();
                    return strtotime($latestRec->created_at);
                }
                
                return 0;
            })->values();
            $escalationStates = DB::table('cases')
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id')
                ->map(fn ($case) => (object) [
                    'status' => (string) $case->status,
                    'priority' => (string) ($case->priority ?? ''),
                    'case_id' => (int) $case->id,
                ])
                ->all();

            $escalatedCount = DB::table('cases')
                ->whereIn('student_id', $studentIds)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->count();

            // A recommendation is "acted upon" when the student has a live case.
            $pendingCount = (int) $recommendations
                ->filter(fn ($rows) => $rows->isNotEmpty())
                ->keys()
                ->reject(fn ($studentId) => isset($escalationStates[(int) $studentId]))
                ->count();

        }
        
        return view('academic-head.recommendations', [
            'blocks' => $blocks,
            'selectedBlock' => $selectedBlock,
            'students' => $sortedStudents,
            'recommendations' => $recommendations,
            'riskLevels' => $riskLevels,
            'blockId' => $blockId,
            'period' => $period,
            'periods' => InterventionService::GRADING_PERIODS,
            'schoolYear' => $schoolYear,
            'escalationStates' => $escalationStates,
            'pendingCount' => $pendingCount,
            'escalatedCount' => $escalatedCount,
            'search' => (string) $request->input('q', ''),
            'riskFilter' => (string) $request->input('risk', 'all'),
        ]);
    }

public function generateForBlock(Request $request)
{
    // Block-wide generation calls the AI once per student in this request, so
    // it needs the same raised, request-scoped ceiling as block risk scoring.
    $this->extendAiExecutionTime();

    // Categorical input validation — the period must be a sane category.
    $request->validate([
        'block_id' => ['required', 'integer', new ApiExists('blocks', 'id')],
        'period' => 'nullable|string|max:20',
    ]);

    $blockId = $request->input('block_id');
    // Normalise (accepts "Final" → "Finals") so the pipeline can only ever bind to
    // one of the three canonical academic periods.
    $gradingPeriod = InterventionService::normalizePeriod($request->input('period'));
    
    if (!$blockId) {
        return response()->json([
            'success' => false,
            'message' => 'Please select a block.',
        ], 400);
    }

    if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this block.',
        ], 403);
    }

    try {
        $result = $this->interventionService->generateForBlock($blockId, $gradingPeriod);
        
        return response()->json([
            'success' => true,
            'success_count' => $result['success_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'total' => $result['total'] ?? 0,
            'message' => "Recommendations generated for {$result['success_count']} student(s).",
            'results' => $result['results'] ?? [],
            'progress' => $result['progress'] ?? [],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to generate recommendations', [
            'block_id' => $blockId,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate recommendations: ' . $e->getMessage(),
        ], 500);
    }
}

public function generateForSelected(Request $request)
{
    // Selected-student generation is the same long synchronous AI loop.
    $this->extendAiExecutionTime();

    $request->validate([
        'student_ids' => 'required|array|min:1',
        'student_ids.*' => ['integer', new ApiExists('students', 'id')],
        'block_id' => ['required', 'integer', new ApiExists('blocks', 'id')],
        'period' => 'nullable|string|max:20',
    ]);

    $blockId = $request->block_id;
    $studentIds = $request->student_ids;
    // Normalise (accepts "Final" → "Finals") so the pipeline can only ever bind to
    // one of the three canonical academic periods.
    $gradingPeriod = InterventionService::normalizePeriod($request->input('period'));

    if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this block.',
        ], 403);
    }

    try {
        $result = $this->interventionService->generateForMultiple($studentIds, $gradingPeriod);
        
        return response()->json([
            'success' => true,
            'success_count' => $result['success_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'total' => $result['total'] ?? 0,
            'message' => "Recommendations generated for {$result['success_count']} student(s).",
            'results' => $result['results'] ?? [],
            'progress' => $result['progress'] ?? [],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to generate selected recommendations', [
            'block_id' => $blockId,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate recommendations: ' . $e->getMessage(),
        ], 500);
    }
}

    public function editRecommendation(Request $request, int $recommendationId)
    {
        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return redirect()->back()->with('error', 'Recommendation not found.');
        }
        
        $studentDepartmentId = app(AcademicStructureRepository::class)
            ->departmentIdForStudent($recommendation->student_id);
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if ($studentDepartmentId === null || $studentDepartmentId != $departmentId) {
            return redirect()->back()->with('error', 'You do not have access to this recommendation.');
        }
        
        $student = app(StudentRepository::class)->find($recommendation->student_id);
        $riskFactors = json_decode($recommendation->risk_factors, true);
        $suggestedActions = json_decode($recommendation->suggested_actions, true);
        
        return view('academic-head.recommendation-edit', [
            'recommendation' => $recommendation,
            'student' => $student,
            'riskFactors' => $riskFactors,
            'suggestedActions' => $suggestedActions,
        ]);
    }

    public function updateRecommendation(Request $request, int $recommendationId)
    {
        $request->validate([
            'suggested_actions' => 'required|string|min:3',
        ]);

        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return redirect()->back()->with('error', 'Recommendation not found.');
        }
        
        $studentDepartmentId = app(AcademicStructureRepository::class)
            ->departmentIdForStudent($recommendation->student_id);
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if ($studentDepartmentId === null || $studentDepartmentId != $departmentId) {
            return redirect()->back()->with('error', 'You do not have access to this recommendation.');
        }

        $actions = (string) $request->suggested_actions;
        
        $parsedActions = json_decode($actions, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedActions) || $parsedActions === []) {
            $lineActions = $this->parseActionLines($actions);

            $parsedActions = $lineActions === []
                ? [
                    [
                        'action' => 'custom',
                        'details' => $actions,
                        'priority' => 'medium',
                    ],
                ]
                : $lineActions;
        }

        DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->update([
                'suggested_actions' => json_encode($parsedActions),
                'updated_at' => now(),
            ]);

        Log::info('Recommendation updated', [
            'recommendation_id' => $recommendationId,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('academic-head.recommendations', ['block_id' => $request->block_id])
            ->with('success', 'Recommendation updated successfully.');
    }

public function deleteRecommendation(Request $request, int $recommendationId)
{
    $recommendation = DB::table('intervention_recommendations')
        ->where('id', $recommendationId)
        ->first();
    
    if (!$recommendation) {
        return response()->json([
            'success' => false,
            'message' => 'Recommendation not found.',
        ], 404);
    }
    
    $studentDepartmentId = app(AcademicStructureRepository::class)
        ->departmentIdForStudent($recommendation->student_id);
    
    $departmentId = DepartmentHelper::getDepartmentId($request);
    if ($studentDepartmentId === null || $studentDepartmentId != $departmentId) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this recommendation.',
        ], 403);
    }

    DB::table('student_recommendation_tracking')
        ->where('recommendation_id', $recommendationId)
        ->delete();

    DB::table('intervention_recommendations')
        ->where('id', $recommendationId)
        ->delete();

    Log::info('Recommendation deleted', [
        'recommendation_id' => $recommendationId,
        'user_id' => auth()->id(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Recommendation deleted successfully.',
    ]);
}

    public function viewRecommendation(Request $request, int $recommendationId)
    {
        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation not found.',
            ], 404);
        }
        
        $student = app(StudentRepository::class)->find($recommendation->student_id);

        $studentDepartmentId = app(AcademicStructureRepository::class)
            ->departmentIdForStudent($recommendation->student_id);
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if ($studentDepartmentId === null || $studentDepartmentId != $departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this recommendation.',
            ], 403);
        }
        
        $riskFactors = json_decode($recommendation->risk_factors, true) ?? [];
        $suggestedActions = json_decode($recommendation->suggested_actions, true) ?? [];
        
        return response()->json([
            'success' => true,
            'student' => [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->student_number,
            ],
            'risk_factors' => $riskFactors,
            'suggested_actions' => $suggestedActions,
            'generated_at' => \Carbon\Carbon::parse($recommendation->generated_at)->format('F d, Y h:i A'),
        ]);
    }
    protected function parseActionLines(string $text): array
    {
        $actions = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $priority = 'medium';
            $action = 'custom';
            $details = $line;

            if (preg_match('/^\[\s*(high|medium|low)\s*\]\s*(.*)$/i', $line, $matches) === 1) {
                $priority = strtolower($matches[1]);
                $rest = trim($matches[2]);

                if (str_contains($rest, ':')) {
                    [$actionPart, $detailPart] = explode(':', $rest, 2);
                    $action = $this->slugAction($actionPart);
                    $details = trim($detailPart);
                } else {
                    $details = $rest;
                }

                $actions[] = [
                    'action' => $action,
                    'details' => $details,
                    'priority' => $priority,
                ];

                continue;
            }

            if (str_contains($line, ':')) {
                [$actionPart, $detailPart] = explode(':', $line, 2);
                $candidate = $this->slugAction($actionPart);

                if ($candidate !== '' && trim($detailPart) !== '') {
                    $actions[] = [
                        'action' => $candidate,
                        'details' => trim($detailPart),
                        'priority' => 'medium',
                    ];

                    continue;
                }
            }

            $actions[] = [
                'action' => 'custom',
                'details' => $details,
                'priority' => $priority,
            ];
        }

        return $actions;
    }

    protected function slugAction(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '_', trim($value)) ?? '';

        return trim(strtolower($slug), '_');
    }
}
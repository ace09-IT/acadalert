<?php

namespace App\Http\Controllers\AcademicHead;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use App\Rules\ApiExists;
use App\Services\Api\MirrorWriter;
use App\Services\RecommendationTextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscalationController extends Controller
{
    public function __construct(
        protected RecommendationTextService $recommendationText,
        protected MirrorWriter $mirror
    ) {
    }

    public function bulkEscalate(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => ['integer', new ApiExists('students', 'id')],
            'notes' => 'nullable|string|max:1000',
            'block_id' => ['required', 'integer', new ApiExists('blocks', 'id')],
            'period' => 'required|string|in:Prelim,Midterm,Finals',
            'recommendations' => 'nullable|array',
            'recommendations.*.include' => 'nullable|boolean',
            'recommendations.*.recommendation_id' => 'nullable|integer',
            'recommendations.*.edited_text' => 'nullable|string|max:5000',
        ]);

        $user = Auth::user();
        $studentIds = $request->student_ids;
        $notes = $request->notes;
        $blockId = $request->block_id;
        $gradingPeriod = $request->period;
        $schoolYear = '2024-2025';

        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $academicHead = app(StaffRepository::class)->academicHeadForUser($user->id);

        if (!$academicHead) {
            return response()->json([
                'success' => false,
                'message' => 'Academic Head record not found.',
            ], 404);
        }

        $counselor = app(StaffRepository::class)->firstCounselorInDepartment($academicHead->department_id);

        if (!$counselor) {
            return response()->json([
                'success' => false,
                'message' => 'No counselor assigned to this department.',
            ], 404);
        }

        $inputs = $this->prepareEscalationInputs($studentIds, $gradingPeriod, $schoolYear);

        $drafts = $this->resolveRecommendationDrafts(
            $request->input('recommendations', []),
            $studentIds,
            $gradingPeriod,
            $schoolYear
        );

        $results = [];
        $successCount = 0;
        $alreadyEscalatedCount = 0;
        $forwardedCount = 0;

        $this->mirror->defer();

        try {
            DB::transaction(function () use (
                $inputs,
                $drafts,
                $studentIds,
                $counselor,
                $user,
                $notes,
                $blockId,
                $gradingPeriod,
                $schoolYear,
                &$results,
                &$successCount,
                &$alreadyEscalatedCount,
                &$forwardedCount
            ) {
                foreach ($studentIds as $studentId) {
                    $studentId = (int) $studentId;

                    $existingCase = DB::table('cases')
                        ->where('student_id', $studentId)
                        ->whereNotIn('status', ['Resolved', 'Closed'])
                        ->first();

                    if ($existingCase) {
                        $alreadyEscalatedCount++;
                        $results[] = [
                            'student_id' => $studentId,
                            'status' => 'already_escalated',
                            'case_id' => $existingCase->id,
                            'message' => 'Student already has an open case.',
                        ];
                        continue;
                    }

                    $input = $inputs[$studentId] ?? [];
                    $riskScore = $input['risk_score'] ?? null;
                    $priority = $input['priority'] ?? 'Medium';
                    $draft = $drafts[$studentId] ?? null;

                    $caseId = DB::table('cases')->insertGetId([
                        'student_id' => $studentId,
                        'counselor_id' => $counselor->id,
                        'escalated_by' => $user->id,
                        'escalated_at' => now(),
                        'school_year' => $schoolYear,
                        'semester' => '1st',
                        'priority' => $priority,
                        'status' => 'New',
                        'risk_level_at_escalation' => $riskScore->risk_level ?? 'Low',
                        'risk_score_at_escalation' => $riskScore->risk_score ?? 0,
                        'intervention_recommendation' => $draft['text'] ?? null,
                        'intervention_included' => (bool) ($draft['included'] ?? false),
                        'intervention_edited' => (bool) ($draft['edited'] ?? false),
                        'intervention_source_id' => $draft['recommendation_id'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('escalations')->insert([
                        'student_id' => $studentId,
                        'escalated_by' => $user->id,
                        'case_id' => $caseId,
                        'intervention_recommendation_id' => $draft['recommendation_id'] ?? null,
                        'school_year' => $schoolYear,
                        'semester' => '1st',
                        'grading_period' => $gradingPeriod,
                        'notes' => $notes,
                        'escalated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $flagIds = $input['flag_ids'] ?? [];

                    if ($flagIds !== []) {
                        DB::table('flags')
                            ->whereIn('id', $flagIds)
                            ->update([
                                'escalated_to_counselor_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    if (!empty($draft['included'])) {
                        $forwardedCount++;
                    }

                    $successCount++;
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'success',
                        'case_id' => $caseId,
                        'priority' => $priority,
                        'recommendation_included' => (bool) ($draft['included'] ?? false),
                        'recommendation_edited' => (bool) ($draft['edited'] ?? false),
                        'message' => 'Student escalated successfully.',
                    ];
                }

                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'action' => 'STUDENTS_ESCALATED',
                    'model_type' => 'App\\Models\\StudentCase',
                    'model_id' => null,
                    'new_values' => json_encode([
                        'student_ids' => array_map('intval', $studentIds),
                        'block_id' => $blockId,
                        'grading_period' => $gradingPeriod,
                        'counselor_id' => $counselor->id,
                        'escalated' => $successCount,
                        'already_escalated' => $alreadyEscalatedCount,
                        'recommendations_forwarded' => $forwardedCount,
                    ]),
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // The local state is committed — release the mirrors.
            $this->mirror->flush();
        } catch (\Throwable $e) {
            // The rows the mirrors describe were never committed.
            $this->mirror->discard();

            Log::error('Bulk escalation failed and was rolled back', [
                'user_id' => $user->id,
                'block_id' => $blockId,
                'student_ids' => $studentIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Escalation failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'total' => count($studentIds),
            'escalated' => $successCount,
            'already_escalated' => $alreadyEscalatedCount,
            'recommendations_forwarded' => $forwardedCount,
            'results' => $results,
            'message' => "$successCount students escalated successfully. $alreadyEscalatedCount already had open cases.",
        ]);
    }

    protected function prepareEscalationInputs(array $studentIds, string $gradingPeriod, string $schoolYear): array
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));

        if ($ids === []) {
            return [];
        }

        $riskRows = DB::table('risk_scores')
            ->whereIn('student_id', $ids)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');

        // Only needed for the Moderate branch, but fetched once for the batch.
        $previousRiskRows = DB::table('risk_scores')
            ->whereIn('student_id', $ids)
            ->where('grading_period', $this->getPreviousPeriod($gradingPeriod))
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');

        $flagRows = DB::table('flags')
            ->whereIn('student_id', $ids)
            ->whereIn('flag_type', ['high_risk', 'consecutive_high_risk'])
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->select('id', 'student_id', 'flag_type', 'consecutive_periods_count')
            ->get()
            ->groupBy('student_id');

        $inputs = [];

        foreach ($ids as $studentId) {
            $riskScore = $riskRows[$studentId] ?? null;
            $flags = $flagRows[$studentId] ?? collect();

            $inputs[$studentId] = [
                'risk_score' => $riskScore,
                'priority' => $this->determinePriority($riskScore, $previousRiskRows[$studentId] ?? null, $flags),
                'flag_ids' => $flags->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ];
        }

        return $inputs;
    }

    protected function determinePriority(?object $riskScore, ?object $previousRisk, $flags): string
    {
        if ($riskScore === null) {
            return 'Medium';
        }

        if ($riskScore->risk_level === 'High') {
            $consecutiveCount = $flags
                ->firstWhere('flag_type', 'consecutive_high_risk')
                ->consecutive_periods_count ?? 0;

            return $consecutiveCount >= 2 ? 'Critical' : 'High';
        }

        if ($riskScore->risk_level === 'Moderate') {
            return ($previousRisk !== null && $previousRisk->risk_level === 'Moderate')
                ? 'Medium'
                : 'Low';
        }

        return 'Low';
    }

    protected function resolveRecommendationDrafts(
        $payload,
        array $studentIds,
        string $gradingPeriod,
        string $schoolYear
    ): array {
        if (!is_array($payload) || $payload === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $studentIds)));

        $requestedIds = [];
        foreach ($payload as $entry) {
            $candidate = is_array($entry) ? ($entry['recommendation_id'] ?? null) : null;

            if (is_numeric($candidate)) {
                $requestedIds[] = (int) $candidate;
            }
        }

        $rows = $requestedIds === []
            ? collect()
            : DB::table('intervention_recommendations')
                ->whereIn('id', array_unique($requestedIds))
                ->whereIn('student_id', $ids)
                ->get()
                ->keyBy('id');

        // Fallback: the student's generated recommendation for this period, so
        // "include" works even if the modal sent no id.
        $latestRows = DB::table('intervention_recommendations')
            ->whereIn('student_id', $ids)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->orderBy('generated_at', 'desc')
            ->get()
            ->groupBy('student_id');

        $drafts = [];

        foreach ($ids as $studentId) {
            $entry = $payload[$studentId] ?? $payload[(string) $studentId] ?? null;

            if (!is_array($entry)) {
                continue;
            }

            $include = filter_var($entry['include'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!$include) {
                $drafts[$studentId] = [
                    'included' => false,
                    'edited' => false,
                    'text' => null,
                    'recommendation_id' => null,
                ];
                continue;
            }

            $recommendationId = is_numeric($entry['recommendation_id'] ?? null)
                ? (int) $entry['recommendation_id']
                : null;

            $source = $recommendationId !== null ? ($rows[$recommendationId] ?? null) : null;

            // The id must belong to THIS student; otherwise fall back to their latest.
            if ($source !== null && (int) $source->student_id !== $studentId) {
                $source = null;
                $recommendationId = null;
            }

            if ($source === null) {
                $source = $latestRows[$studentId][0] ?? null;
                $recommendationId = $source->id ?? null;
            }

            $generatedText = $source === null
                ? []
                : ($source->suggested_actions ?? []);

            // ONE rule for "what gets forwarded" — shared with its unit test:
            // an edit wins, otherwise the generated text, otherwise nothing.
            $resolved = $this->recommendationText->resolveForwardedText(
                $generatedText,
                isset($entry['edited_text']) && is_string($entry['edited_text']) ? $entry['edited_text'] : null
            );

            $drafts[$studentId] = [
                'included' => $resolved['included'],
                'edited' => $resolved['edited'],
                'text' => $resolved['text'],
                'recommendation_id' => $resolved['included'] ? $recommendationId : null,
            ];
        }

        return $drafts;
    }

    public function recommendationPreview(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => ['integer', new ApiExists('students', 'id')],
            'period' => 'required|string|in:Prelim,Midterm,Finals',
        ]);

        $studentIds = array_values(array_unique(array_map('intval', $request->student_ids)));
        $gradingPeriod = $request->period;
        $schoolYear = '2024-2025';

        foreach ($studentIds as $studentId) {
            if (!DepartmentHelper::isStudentInDepartment($request, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to one or more of these students.',
                ], 403);
            }
        }

        // Mock API: one call for every name we need.
        $students = app(StudentRepository::class)->keyedById();

        $riskRows = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');

        $recommendationRows = DB::table('intervention_recommendations')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->orderBy('generated_at', 'desc')
            ->get()
            ->groupBy('student_id');

        $previews = [];

        foreach ($studentIds as $studentId) {
            $student = $students[$studentId] ?? null;
            $risk = $riskRows[$studentId] ?? null;
            // grouped rows keep the query's newest-first order.
            $recommendation = $recommendationRows[$studentId][0] ?? null;

            $actions = $recommendation === null
                ? []
                : $this->recommendationText->actions($recommendation->suggested_actions);

            $previews[(string) $studentId] = [
                'student_id' => $studentId,
                'student_name' => $student === null
                    ? 'Student #' . $studentId
                    : trim(($student->last_name ?? '') . ', ' . ($student->first_name ?? '')),
                'student_number' => $student->student_number ?? null,
                'risk_level' => $risk->risk_level ?? 'N/A',
                'risk_score' => $risk === null ? null : (int) $risk->risk_score,
                'risk_factors' => $risk === null
                    ? []
                    : $this->recommendationText->factors($risk->risk_factors),
                'has_recommendation' => $recommendation !== null && $actions !== [],
                'recommendation_id' => $recommendation->id ?? null,
                'preview_text' => $recommendation === null
                    ? ''
                    : $this->recommendationText->toText($recommendation->suggested_actions),
                'suggested_actions' => $actions,
                'generated_at' => $recommendation !== null && $recommendation->generated_at
                    ? \Carbon\Carbon::parse($recommendation->generated_at)->format('M d, Y h:i A')
                    : null,
            ];
        }

        return response()->json([
            'success' => true,
            'period' => $gradingPeriod,
            'school_year' => $schoolYear,
            'students' => $previews,
        ]);
    }

    public function checkEscalationStatus(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => ['integer', new ApiExists('students', 'id')],
        ]);

        $studentIds = $request->student_ids;
        $statuses = [];

        foreach ($studentIds as $studentId) {
            $existingCase = DB::table('cases')
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->first();

            $statuses[$studentId] = [
                'is_escalated' => $existingCase !== null,
                'case_id' => $existingCase->id ?? null,
                'status' => $existingCase->status ?? null,
                'priority' => $existingCase->priority ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'statuses' => $statuses,
        ]);
    }

    public function resetEscalation(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'integer', new ApiExists('students', 'id')],
            'reason' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $studentId = $request->student_id;
        $reason = $request->reason ?? 'No reason provided';

        if (!DepartmentHelper::isStudentInDepartment($request, $studentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this student.',
            ], 403);
        }

        $case = DB::table('cases')
            ->where('student_id', $studentId)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'No open case found for this student.',
            ], 404);
        }

        if (!in_array($case->status, ['New', 'In Progress'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reset a case that is already ' . $case->status . '.',
            ], 400);
        }

        $escalation = DB::table('escalations')
            ->where('student_id', $studentId)
            ->where('case_id', $case->id)
            ->first();

        DB::beginTransaction();

        try {
            if ($escalation) {
                DB::table('escalations')
                    ->where('id', $escalation->id)
                    ->delete();
            }

            DB::table('case_sessions')
                ->where('case_id', $case->id)
                ->delete();

            DB::table('cases')
                ->where('id', $case->id)
                ->delete();

            DB::table('flags')
                ->where('student_id', $studentId)
                ->update([
                    'escalated_to_counselor_at' => null,
                    'updated_at' => now(),
                ]);

            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'action' => 'ESCALATION_RESET',
                'model_type' => 'App\\Models\\Student',
                'model_id' => $studentId,
                'new_values' => json_encode([
                    'reason' => $reason,
                    'case_id' => $case->id,
                    'reset_at' => now(),
                ]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Escalation reset successfully for student.',
                'student_id' => $studentId,
                'reason' => $reason,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset escalation: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bulkResetEscalation(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => ['integer', new ApiExists('students', 'id')],
            'reason' => 'nullable|string|max:500',
        ]);

        $studentIds = $request->student_ids;
        $reason = $request->reason ?? 'Bulk reset - no reason provided';

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($studentIds as $studentId) {
            try {
                $subRequest = app(Request::class);
                $subRequest->merge([
                    'student_id' => $studentId,
                    'reason' => $reason . ' (Bulk reset)',
                ]);
                
                $response = $this->resetEscalation($subRequest);
                $data = $response->getData();

                if ($data->success) {
                    $successCount++;
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'success',
                        'message' => $data->message,
                    ];
                } else {
                    $failedCount++;
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'failed',
                        'message' => $data->message ?? 'Unknown error',
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                $results[] = [
                    'student_id' => $studentId,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total' => count($studentIds),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'message' => "$successCount students reset successfully, $failedCount failed.",
        ]);
    }

    public function canResetEscalation(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'integer', new ApiExists('students', 'id')],
        ]);

        $studentId = $request->student_id;

        $case = DB::table('cases')
            ->where('student_id', $studentId)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'can_reset' => false,
                'message' => 'No open case found.',
            ]);
        }

        $canReset = in_array($case->status, ['New', 'In Progress']);

        return response()->json([
            'success' => true,
            'can_reset' => $canReset,
            'case_id' => $case->id,
            'status' => $case->status,
            'message' => $canReset 
                ? 'Escalation can be reset.' 
                : 'Case is already ' . $case->status . '. Cannot reset.',
        ]);
    }

    public function getEscalatedStudentsInBlock(Request $request, int $blockId)
    {
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        // cases stays local; the students join resolves through the API placement
        // index. The previous INNER JOIN meant only placed students could ever match.
        $blockStudentIds = app(\App\Repositories\Api\AcademicStructureRepository::class)
            ->studentIdsInBlock($blockId, false);

        $students = DB::table('cases')
            ->whereIn('student_id', $blockStudentIds)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->whereIn('status', ['New', 'In Progress'])
            ->pluck('student_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'students' => $students,
            'count' => count($students),
        ]);
    }

    protected function getPreviousPeriod(string $current): string
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : 'Prelim';
    }
}

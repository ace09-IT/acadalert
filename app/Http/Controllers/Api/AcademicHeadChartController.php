<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicHeadChartController extends Controller
{
    public function riskByProgram(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Academic Head records are served by the mock API now.
            $academicHead = $staff->academicHeadForUser($user->id);

            if (!$academicHead) {
                Log::error('Academic Head record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Head record not found.',
                ], 404);
            }

            $departmentId = $academicHead->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Academic Head: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Academic Head.',
                ], 404);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            Log::info('AcademicHeadChartController: Fetching risk by program for department: ' . $departmentId);

            $data = [];

            foreach ($structure->programsForDepartment($departmentId)->sortBy('code')->values() as $program) {
                $studentIds = $structure->studentIdsInProgram($program->id, false);
                $counts = $riskScores->levelCountsFor($studentIds, $period, $schoolYear);

                $data[] = (object) [
                    'program' => $program->code,
                    'total' => count($studentIds),
                    'high_risk' => $counts['High'],
                    'moderate_risk' => $counts['Moderate'],
                    'low_risk' => $counts['Low'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'department_id' => $departmentId,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('AcademicHeadChartController@riskByProgram: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk by program data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function departmentRiskTrend(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Academic Head records are served by the mock API now.
            $academicHead = $staff->academicHeadForUser($user->id);

            if (!$academicHead) {
                Log::error('Academic Head record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Head record not found.',
                ], 404);
            }

            $departmentId = $academicHead->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Academic Head: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Academic Head.',
                ], 404);
            }

            $periods = ['Prelim', 'Midterm', 'Finals'];
            $schoolYear = $request->input('school_year', '2024-2025');
            $trend = [];

            Log::info('AcademicHeadChartController: Fetching department risk trend for department: ' . $departmentId);

            $departmentStudentIds = $structure->studentIdsInDepartment($departmentId, false);

            foreach ($periods as $period) {
                $high = $riskScores->highCountFor($departmentStudentIds, $period, $schoolYear);
                $total = $riskScores->countFor($departmentStudentIds, $period, $schoolYear);

                $percentage = $total > 0 ? round(($high / $total) * 100, 1) : 0;

                $trend[] = [
                    'period' => $period,
                    'high_risk_count' => $high,
                    'total_students' => $total,
                    'high_risk_percentage' => $percentage,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $periods,
                    'datasets' => [[
                        'label' => 'High Risk %',
                        'data' => array_column($trend, 'high_risk_percentage'),
                        'borderColor' => '#dc3545',
                        'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => '#dc3545',
                    ]]
                ],
                'department_id' => $departmentId,
            ]);
        } catch (\Exception $e) {
            Log::error('AcademicHeadChartController@departmentRiskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch department risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function riskByBlock(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores
    ) {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $academicHead = $staff->academicHeadForUser($user->id);

            if (!$academicHead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Head record not found.',
                ], 404);
            }

            $departmentId = $academicHead->department_id;

            if (!$departmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Academic Head.',
                ], 404);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $blocks = [];

            foreach ($structure->blocksWithProgramForDepartment($departmentId) as $block) {
                $studentIds = $structure->studentIdsInBlock($block->id, true);
                $counts = $riskScores->levelCountsFor($studentIds, $period, $schoolYear);

                $blocks[] = [
                    'block_id' => (int) $block->id,
                    'label' => trim(implode(' - ', array_filter([
                        $block->program_code,
                        'Year ' . ($block->year_number ?? '?'),
                        $block->name,
                    ]))),
                    'total' => count($studentIds),
                    'high_risk' => $counts['High'],
                    'moderate_risk' => $counts['Moderate'],
                    'low_risk' => $counts['Low'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_column($blocks, 'label'),
                    'datasets' => [
                        [
                            'label' => 'Low',
                            'data' => array_column($blocks, 'low_risk'),
                            'backgroundColor' => '#28a745',
                        ],
                        [
                            'label' => 'Moderate',
                            'data' => array_column($blocks, 'moderate_risk'),
                            'backgroundColor' => '#ffc107',
                        ],
                        [
                            'label' => 'High',
                            'data' => array_column($blocks, 'high_risk'),
                            'backgroundColor' => '#dc3545',
                        ],
                    ],
                ],
                'blocks' => $blocks,
                'department_id' => $departmentId,
                'period' => $period,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AcademicHeadChartController@riskByBlock: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk by block data: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function escalationTrend(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure
    ) {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $academicHead = $staff->academicHeadForUser($user->id);

            if (!$academicHead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Head record not found.',
                ], 404);
            }

            $departmentId = $academicHead->department_id;

            if (!$departmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Academic Head.',
                ], 404);
            }

            $schoolYear = $request->input('school_year', '2024-2025');
            $semester = $request->input('semester', '1st');

            $studentIds = $structure->studentIdsInDepartment($departmentId, false);

            $rows = $studentIds === []
                ? collect()
                : DB::table('escalations')
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->whereIn('student_id', $studentIds)
                    ->pluck('grading_period')
                    ->countBy();

            $series = [];

            foreach (['Prelim', 'Midterm', 'Finals'] as $period) {
                $series[] = [
                    'period' => $period,
                    'escalations' => (int) ($rows[$period] ?? 0),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_column($series, 'period'),
                    'datasets' => [[
                        'label' => 'Escalations to Counselor',
                        'data' => array_column($series, 'escalations'),
                        'borderColor' => '#f6c23e',
                        'backgroundColor' => 'rgba(246, 194, 62, 0.15)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => '#f6c23e',
                    ]],
                ],
                'series' => $series,
                'department_id' => $departmentId,
                'school_year' => $schoolYear,
                'semester' => $semester,
            ]);
        } catch (\Exception $e) {
            Log::error('AcademicHeadChartController@escalationTrend: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the escalation trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function blockRiskDistribution(
        Request $request,
        int $blockId,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Academic Head records are served by the mock API now.
            $academicHead = $staff->academicHeadForUser($user->id);

            if (!$academicHead) {
                Log::error('Academic Head record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Head record not found.',
                ], 404);
            }

            $departmentId = $academicHead->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Academic Head: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Academic Head.',
                ], 404);
            }

            if (!$structure->isBlockInDepartment($blockId, $departmentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this block.',
                ], 403);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $students = $structure->studentIdsInBlock($blockId, true);

            if (empty($students)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['Low Risk', 'Moderate Risk', 'High Risk'],
                        'datasets' => [[
                            'data' => [0, 0, 0],
                            'backgroundColor' => ['#28a745', '#ffc107', '#dc3545'],
                            'borderWidth' => 2,
                            'borderColor' => '#fff',
                        ]]
                    ],
                    'message' => 'No students in this block.',
                ]);
            }

            // risk_scores stays local; the three level counts come from one
            // memoised period read instead of three COUNT() round-trips.
            $counts = $riskScores->levelCountsFor($students, $period, $schoolYear);

            $low = $counts['Low'];
            $moderate = $counts['Moderate'];
            $high = $counts['High'];

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => ['Low Risk', 'Moderate Risk', 'High Risk'],
                    'datasets' => [[
                        'data' => [$low, $moderate, $high],
                        'backgroundColor' => ['#28a745', '#ffc107', '#dc3545'],
                        'borderWidth' => 2,
                        'borderColor' => '#fff',
                    ]]
                ],
                'block_id' => $blockId,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('AcademicHeadChartController@blockRiskDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch block risk distribution: ' . $e->getMessage(),
            ], 500);
        }
    }
}
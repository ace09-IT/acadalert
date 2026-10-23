<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Local\CaseRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounselorChartController extends Controller
{
    public function priorityDistribution(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        CaseRepository $caseRepo
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Counselor records are served by the mock API now.
            $counselor = $staff->counselorForUser($user->id);

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            $departmentId = $request->attributes->get('department_id');

            $studentIds = $departmentId
                ? $structure->studentIdsInDepartment($departmentId, false)
                : array_keys($structure->studentPlacements());

            $distribution = $caseRepo->countBy(
                $caseRepo->forCounselor((int) $counselor->id, $studentIds, ['Resolved', 'Closed']),
                'priority'
            );

            $priorityData = [
                'Critical' => 0,
                'High' => 0,
                'Medium' => 0,
                'Low' => 0,
            ];

            foreach ($distribution as $priority => $count) {
                $priorityData[$priority] = $count;
            }

            $colors = [
                'Critical' => '#dc3545',
                'High' => '#ffc107',
                'Medium' => '#17a2b8',
                'Low' => '#6c757d',
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_keys($priorityData),
                    'datasets' => [[
                        'data' => array_values($priorityData),
                        'backgroundColor' => array_map(function($label) use ($colors) {
                            return $colors[$label] ?? '#6c757d';
                        }, array_keys($priorityData)),
                        'borderWidth' => 2,
                        'borderColor' => '#fff',
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@priorityDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch priority distribution: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function statusDistribution(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        CaseRepository $caseRepo
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Counselor records are served by the mock API now.
            $counselor = $staff->counselorForUser($user->id);

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            $departmentId = $request->attributes->get('department_id');

            $studentIds = $departmentId
                ? $structure->studentIdsInDepartment($departmentId, false)
                : array_keys($structure->studentPlacements());

            $distribution = $caseRepo->countBy(
                $caseRepo->forCounselor((int) $counselor->id, $studentIds),
                'status'
            );

            $statuses = ['New', 'In Progress', 'Awaiting Parent', 'Awaiting Student', 'Referred', 'Resolved', 'Closed', 'Reopened'];
            $statusData = [];

            foreach ($statuses as $status) {
                $statusData[$status] = $distribution[$status] ?? 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_keys($statusData),
                    'datasets' => [[
                        'label' => 'Cases',
                        'data' => array_values($statusData),
                        'backgroundColor' => '#4e73df',
                        'borderRadius' => 4,
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@statusDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status distribution: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function caseloadTrend(
        Request $request,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        CaseRepository $caseRepo
    ) {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Counselor records are served by the mock API now.
            $counselor = $staff->counselorForUser($user->id);

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            $departmentId = $request->attributes->get('department_id');
            $weeks = (int) $request->input('weeks', 6);

            $studentIds = $departmentId
                ? $structure->studentIdsInDepartment($departmentId, false)
                : array_keys($structure->studentPlacements());

            $cases = $caseRepo->forCounselor(
                (int) $counselor->id,
                $studentIds,
                null,
                null,
                now()->subWeeks($weeks)
            );

            // DATE(cases.escalated_at) + GROUP BY + ORDER BY date, reproduced in PHP.
            $byDate = [];

            foreach ($cases as $case) {
                $date = substr((string) $case->escalated_at, 0, 10);
                $byDate[$date] = ($byDate[$date] ?? 0) + 1;
            }

            ksort($byDate);

            $dates = array_keys($byDate);
            $counts = array_values($byDate);

            $allDates = [];
            $allCounts = [];
            $current = now()->subWeeks($weeks);
            $end = now();

            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $allDates[] = $dateStr;
                $index = array_search($dateStr, $dates);
                $allCounts[] = $index !== false ? $counts[$index] : 0;
                $current->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $allDates,
                    'datasets' => [[
                        'label' => 'New Cases',
                        'data' => $allCounts,
                        'borderColor' => '#4e73df',
                        'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => '#4e73df',
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@caseloadTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch caseload trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function studentRiskTrend(
        Request $request,
        int $studentId,
        StaffRepository $staff,
        AcademicStructureRepository $structure,
        CaseRepository $caseRepo,
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

            $schoolYear = $request->input('school_year', '2024-2025');

            $counselor = $staff->counselorForUser($user->id);

            if (!$counselor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            $departmentId = $request->attributes->get('department_id');

            // The previous INNER JOINs also required a valid placement, so an
            // unplaced student must never be authorised either.
            $inScope = $structure->isStudentPlaced($studentId)
                && (!$departmentId || $structure->isStudentInDepartment($studentId, $departmentId));

            $authorized = $inScope
                && $caseRepo->existsForCounselorAndStudent((int) $counselor->id, $studentId);

            if (!$authorized) {
                Log::warning('CounselorChartController@studentRiskTrend blocked cross-department access', [
                    'counselor_id' => $counselor->id,
                    'student_id' => $studentId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Student not found in your caseload.',
                ], 403);
            }

            // risk_scores stays local; the FIELD() ordering is applied in PHP.
            $riskHistory = $riskScores->historyForStudent($studentId, $schoolYear);

            $periods = $riskHistory->pluck('grading_period')->toArray();
            $scores = $riskHistory->pluck('risk_score')->toArray();
            $levels = $riskHistory->pluck('risk_level')->toArray();

            $pointColors = array_map(function($level) {
                return $level === 'High' ? '#dc3545' : ($level === 'Moderate' ? '#ffc107' : '#28a745');
            }, $levels);

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $periods,
                    'datasets' => [[
                        'label' => 'Risk Score',
                        'data' => $scores,
                        'borderColor' => '#4e73df',
                        'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => $pointColors,
                        'pointRadius' => 6,
                        'pointHoverRadius' => 8,
                    ]]
                ],
                'student_id' => $studentId,
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@studentRiskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }
}
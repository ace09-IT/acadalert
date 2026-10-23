<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentChartController extends Controller
{
    public function riskTrend(Request $request, StudentRepository $students, RiskScoreRepository $riskScores)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Student records are served by the mock API now.
            $student = $students->findByEmail($user->email);

            if (!$student) {
                Log::error('Student record not found for user: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            $schoolYear = $request->input('school_year', '2024-2025');

            // risk_scores stays local (post-computation aggregate); the FIELD()
            // ordering is applied in PHP by the repository.
            $riskHistory = $riskScores->historyForStudent($student->id, $schoolYear);

            if ($riskHistory->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['No Data'],
                        'datasets' => [[
                            'label' => 'Risk Score',
                            'data' => [0],
                            'borderColor' => '#6c757d',
                            'backgroundColor' => 'rgba(108, 117, 125, 0.1)',
                            'fill' => true,
                            'tension' => 0.3,
                            'pointBackgroundColor' => '#6c757d',
                            'pointRadius' => 6,
                            'pointHoverRadius' => 8,
                        ]]
                    ],
                    'message' => 'No risk data available. Please complete at least one grading period.',
                ]);
            }

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
                'student_id' => $student->id,
            ]);
        } catch (\Exception $e) {
            Log::error('StudentChartController@riskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function grades(Request $request, StudentRepository $students, GradeRepository $gradeRepo)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Student records are served by the mock API now.
            $student = $students->findByEmail($user->email);

            if (!$student) {
                Log::error('Student record not found for user: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $grades = $gradeRepo->subjectGradesForStudent($student->id, $period, $schoolYear);

            if ($grades->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['No Data'],
                        'datasets' => [[
                            'label' => 'Grade %',
                            'data' => [0],
                            'backgroundColor' => ['#6c757d'],
                            'borderRadius' => 4,
                        ]]
                    ],
                    'message' => 'No grades available for this period.',
                ]);
            }

            $subjectNames = $grades->pluck('subject_name')->toArray();
            $gradeValues = $grades->pluck('numerical_grade')->toArray();

            $subjectNames = array_map(function($name) {
                return strlen($name) > 20 ? substr($name, 0, 20) . '...' : $name;
            }, $subjectNames);

            $colors = array_map(function($grade) {
                return $grade >= 80 ? '#28a745' : ($grade >= 75 ? '#ffc107' : '#dc3545');
            }, $gradeValues);

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $subjectNames,
                    'datasets' => [[
                        'label' => 'Grade %',
                        'data' => $gradeValues,
                        'backgroundColor' => $colors,
                        'borderRadius' => 4,
                    ]]
                ],
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('StudentChartController@grades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grades: ' . $e->getMessage(),
            ], 500);
        }
    }
}
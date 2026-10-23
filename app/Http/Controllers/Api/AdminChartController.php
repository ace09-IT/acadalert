<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminChartController extends Controller
{
    public function riskByDepartment(
        Request $request,
        AcademicStructureRepository $structure,
        RiskScoreRepository $riskScores
    ) {
        try {
            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $data = [];

            foreach ($structure->departments()->sortBy('code')->values() as $department) {
                $studentIds = $structure->studentIdsInDepartment($department->id, false);
                $counts = $riskScores->levelCountsFor($studentIds, $period, $schoolYear);

                $data[] = (object) [
                    'department' => $department->code,
                    'total' => count($studentIds),
                    'high_risk' => $counts['High'],
                    'moderate_risk' => $counts['Moderate'],
                    'low_risk' => $counts['Low'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $period,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskByDepartment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk by department data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function riskDistribution(Request $request)
    {
        try {
            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $low = DB::table('risk_scores')
                ->where('risk_level', 'Low')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $moderate = DB::table('risk_scores')
                ->where('risk_level', 'Moderate')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $high = DB::table('risk_scores')
                ->where('risk_level', 'High')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

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
                'period' => $period,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk distribution data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function riskTrend(Request $request)
    {
        try {
            $periods = ['Prelim', 'Midterm', 'Finals'];
            $schoolYear = $request->input('school_year', '2024-2025');

            $trend = [];

            foreach ($periods as $period) {
                $high = DB::table('risk_scores')
                    ->where('risk_level', 'High')
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->count();

                $total = DB::table('risk_scores')
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->count();

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
                'raw_data' => $trend,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk trend data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
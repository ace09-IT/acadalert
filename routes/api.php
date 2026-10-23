<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminChartController;
use App\Http\Controllers\Api\AcademicHeadChartController;
use App\Http\Controllers\Api\CounselorChartController;
use App\Http\Controllers\Api\StudentChartController;


Route::middleware('auth')->group(function () {
    
    Route::prefix('charts/admin')->group(function () {
        Route::get('/risk-by-department', [AdminChartController::class, 'riskByDepartment']);
        Route::get('/risk-distribution', [AdminChartController::class, 'riskDistribution']);
        Route::get('/risk-trend', [AdminChartController::class, 'riskTrend']);
    });
    
    Route::prefix('charts/academic-head')->middleware(['role:academic_head'])->group(function () {
        Route::get('/department-risk', [AcademicHeadChartController::class, 'riskByProgram']);
        Route::get('/department-trend', [AcademicHeadChartController::class, 'departmentRiskTrend']);
        Route::get('/block-risk/{blockId}', [AcademicHeadChartController::class, 'blockRiskDistribution']);
    });
    
    Route::prefix('charts/counselor')->middleware(['role:guidance_counselor', 'department.isolation'])->group(function () {
        Route::get('/priority-distribution', [CounselorChartController::class, 'priorityDistribution']);
        Route::get('/status-distribution', [CounselorChartController::class, 'statusDistribution']);
        Route::get('/caseload-trend', [CounselorChartController::class, 'caseloadTrend']);
        Route::get('/student-risk/{studentId}', [CounselorChartController::class, 'studentRiskTrend']);
    });
    
    Route::prefix('charts/student')->middleware(['role:student'])->group(function () {
        Route::get('/risk-trend', [StudentChartController::class, 'riskTrend']);
        Route::get('/grades', [StudentChartController::class, 'grades']);
    });
});

// Fallback route for chart API errors
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Chart API endpoint not found.',
    ], 404);
});
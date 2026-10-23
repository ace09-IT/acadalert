<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AcademicHead\DashboardController as AcademicHeadDashboardController;
use App\Http\Controllers\Counselor\DashboardController as CounselorDashboardController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\AcademicHead\RiskScoringController;
use App\Http\Controllers\AcademicHead\EscalationController;
use App\Http\Controllers\AcademicHead\InterventionController;
use App\Http\Controllers\AcademicHead\ReportController as AcademicHeadReportController;
use App\Http\Controllers\Admin\EndOfTermReportController as AdminReportController;
use App\Http\Controllers\Counselor\ReportController as CounselorReportController;
use App\Http\Controllers\Student\RecommendationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Api\AdminChartController;
use App\Http\Controllers\Api\AcademicHeadChartController;
use App\Http\Controllers\Api\CounselorChartController;
use App\Http\Controllers\Api\StudentChartController;

// ========================================
// Public Routes
// ========================================

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// ========================================
// Authentication Routes
// ========================================

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ========================================
// Helper Routes for Academic Head
// ========================================

Route::middleware(['auth', 'role:academic_head'])->group(function () {
    // Get student count for a block
    Route::get('/academic-head/block/{blockId}/student-count', function ($blockId) {
        // students is served by the mock API now.
        $count = app(\App\Repositories\Api\StudentRepository::class)->activeCountForBlock((int) $blockId);
        return response()->json(['count' => $count]);
    })->name('academic-head.block.studentCount');
    
    // Get students for a block (used in intervention modal for student selection)
    Route::get('/academic-head/block/{blockId}/students', function ($blockId) {
        // students is served by the mock API now. The previous query had no ORDER BY,
        // so both it and this version return the rows in id order.
        $students = app(\App\Repositories\Api\StudentRepository::class)->all()
            ->where('block_id', (int) $blockId)
            ->where('status', 'Active')
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'student_number' => $row->student_number,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'students' => $students,
        ]);
    })->name('academic-head.block.students');
});

// ========================================
// Protected Routes
// ========================================

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'academic_head') {
            return redirect()->route('academic-head.department');
        } elseif ($user->role === 'guidance_counselor') {
            return redirect()->route('counselor.dashboard');
        } elseif ($user->role === 'student') {
            return redirect()->route('student.dashboard');
        }
        
        return redirect()->route('login');
    })->name('dashboard');
    
    // ============================================
    // CHART.JS API ENDPOINTS
    // ============================================
    
    // ======== ADMIN CHARTS ========
    Route::prefix('charts/admin')->group(function () {
        Route::get('/risk-by-department', [AdminChartController::class, 'riskByDepartment']);
        Route::get('/risk-distribution', [AdminChartController::class, 'riskDistribution']);
        Route::get('/risk-trend', [AdminChartController::class, 'riskTrend']);
    });
    
    // ======== ACADEMIC HEAD CHARTS ========
    Route::prefix('charts/academic-head')->middleware(['auth', 'role:academic_head'])->group(function () {
        Route::get('/department-risk', [AcademicHeadChartController::class, 'riskByProgram']);
        Route::get('/department-trend', [AcademicHeadChartController::class, 'departmentRiskTrend']);
        Route::get('/block-risk/{blockId}', [AcademicHeadChartController::class, 'blockRiskDistribution']);
        // Department-wide breakdowns added for the reorganized dashboard.
        Route::get('/risk-by-block', [AcademicHeadChartController::class, 'riskByBlock']);
        Route::get('/escalation-trend', [AcademicHeadChartController::class, 'escalationTrend']);
    });
    
    // ======== COUNSELOR CHARTS ========
    Route::prefix('charts/counselor')->middleware(['auth', 'role:guidance_counselor', 'department.isolation'])->group(function () {
        Route::get('/priority-distribution', [CounselorChartController::class, 'priorityDistribution']);
        Route::get('/status-distribution', [CounselorChartController::class, 'statusDistribution']);
        Route::get('/caseload-trend', [CounselorChartController::class, 'caseloadTrend']);
        Route::get('/student-risk/{studentId}', [CounselorChartController::class, 'studentRiskTrend']);
    });
    
    // ======== STUDENT CHARTS ========
    Route::prefix('charts/student')->middleware(['auth', 'role:student'])->group(function () {
        Route::get('/risk-trend', [StudentChartController::class, 'riskTrend']);
        Route::get('/grades', [StudentChartController::class, 'grades']);
    });
    
    // ======== ADMIN ROUTES ========
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // ============================================
        // USERS MANAGEMENT - FULL CRUD
        // ============================================
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('users.index');
        Route::get('/users/create', [AdminDashboardController::class, 'createUser'])->name('users.create');
        Route::post('/users', [AdminDashboardController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{id}/edit', [AdminDashboardController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{id}', [AdminDashboardController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}/delete', [AdminDashboardController::class, 'deleteUser'])->name('users.delete');
        
        // ============================================
        // AJAX ROUTES FOR DYNAMIC DROPDOWNS
        // ============================================
        // Department -> Programs
        Route::get('/get-programs/{departmentId}', [AdminDashboardController::class, 'getProgramsByDepartment'])->name('get.programs');
        // Program -> Year Levels
        Route::get('/get-year-levels/{programId}', [AdminDashboardController::class, 'getYearLevelsByProgram'])->name('get.year.levels');
        // Year Level -> Blocks
        Route::get('/get-blocks/{yearLevelId}', [AdminDashboardController::class, 'getBlocksByYearLevel'])->name('get.blocks');
        
        // ============================================
        // END OF USERS MANAGEMENT & AJAX ROUTES
        // ============================================
        
        // ============================================
        // ACADEMIC STRUCTURE MANAGEMENT - FULL CRUD
        // ============================================
        Route::prefix('academic')->name('academic.')->group(function () {
            // Main academic page
            Route::get('/', [AdminDashboardController::class, 'academic'])->name('index');
            
            // Department Routes
            Route::post('/department/store', [AdminDashboardController::class, 'storeDepartment'])->name('department.store');
            Route::get('/department/{id}/edit', [AdminDashboardController::class, 'editDepartment'])->name('department.edit');
            Route::put('/department/{id}', [AdminDashboardController::class, 'updateDepartment'])->name('department.update');
            Route::delete('/department/{id}/delete', [AdminDashboardController::class, 'deleteDepartment'])->name('department.delete');
            
            // Program Routes
            Route::post('/program/store', [AdminDashboardController::class, 'storeProgram'])->name('program.store');
            Route::get('/program/{id}/edit', [AdminDashboardController::class, 'editProgram'])->name('program.edit');
            Route::put('/program/{id}', [AdminDashboardController::class, 'updateProgram'])->name('program.update');
            Route::delete('/program/{id}/delete', [AdminDashboardController::class, 'deleteProgram'])->name('program.delete');
            
            // Subject Routes
            Route::post('/subject/store', [AdminDashboardController::class, 'storeSubject'])->name('subject.store');
            Route::get('/subject/{id}/edit', [AdminDashboardController::class, 'editSubject'])->name('subject.edit');
            Route::put('/subject/{id}', [AdminDashboardController::class, 'updateSubject'])->name('subject.update');
            Route::delete('/subject/{id}/delete', [AdminDashboardController::class, 'deleteSubject'])->name('subject.delete');
        });
        
        // Risk Configuration
        Route::get('/risk/config', [AdminDashboardController::class, 'riskConfig'])->name('risk.config');
        Route::post('/risk/config/save', [AdminDashboardController::class, 'saveRiskConfig'])->name('risk.save');
        Route::get('/risk/test-ai', [AdminDashboardController::class, 'testAIConnection'])->name('risk.test-ai');
        
        // ============================================
        // PAYMENT ROUTES
        // ============================================
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'payments'])->name('index');
            Route::get('/export', [AdminDashboardController::class, 'exportPayments'])->name('export');  // NEW
            Route::get('/{id}/view', [AdminDashboardController::class, 'viewPayment'])->name('view');
            Route::get('/{id}/info', [AdminDashboardController::class, 'getPaymentInfo'])->name('info');
            // FIXED: Added where('id', '[0-9]+') constraint to ensure integer parameter
            Route::post('/{id}/record', [AdminDashboardController::class, 'recordPayment'])->name('record')->where('id', '[0-9]+');
        });
        
        // Audit Logs
        Route::get('/audit/logs', [AdminDashboardController::class, 'auditLogs'])->name('audit.logs');
        // The same list as a CSV download. The two paths differ, so neither can
        // shadow the other. The export is fed the SAME request input as the page,
        // which is what makes the file match the table on screen.
        Route::get('/audit/logs/export', [AdminDashboardController::class, 'exportAuditLogs'])->name('audit.logs.export');
        
        // School Year
        Route::get('/schoolyear', [AdminDashboardController::class, 'schoolYear'])->name('schoolyear.index');
        Route::post('/schoolyear/create', [AdminDashboardController::class, 'createSchoolYear'])->name('schoolyear.create');
        Route::post('/schoolyear/archive/{id}', [AdminDashboardController::class, 'archiveSchoolYear'])->name('schoolyear.archive');
        Route::post('/schoolyear/rollover', [AdminDashboardController::class, 'rolloverSchoolYear'])->name('schoolyear.rollover');
        
        // System Health
        Route::get('/system/health', [AdminDashboardController::class, 'systemHealth'])->name('system.health');

        // ============================================
        // END-OF-TERM REPORTS (read-only consumer)
        // Consolidated view of every report shared with the administrator role.
        // ============================================
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
        Route::get('/reports/{reportId}', [AdminReportController::class, 'preview'])->whereNumber('reportId')->name('reports.preview');
        Route::get('/reports/{reportId}/pdf', [AdminReportController::class, 'download'])->whereNumber('reportId')->name('reports.pdf');
    });
    
    // ======== ACADEMIC HEAD ROUTES ========
    Route::prefix('academic-head')->name('academic-head.')->middleware(['role:academic_head', 'department.isolation'])->group(function () {
        Route::get('/department', [AcademicHeadDashboardController::class, 'department'])->name('department');
        // Department-wide block index. Declared BEFORE the {programId} form so the
        // bare path is matched by the literal route (the parameterized one cannot
        // match a path with no trailing segment, but the ordering makes that
        // explicit rather than incidental).
        Route::get('/blocks', [AcademicHeadDashboardController::class, 'blocksIndex'])->name('blocks.index');
        Route::get('/blocks/{programId}', [AcademicHeadDashboardController::class, 'blocks'])->name('blocks');
        Route::get('/block/{blockId}', [AcademicHeadDashboardController::class, 'block'])->name('block');

        // ============================================
        // STUDENT BASIS — grade/attendance cross-check for a risk score
        // Allows the Academic Head to verify why a student received a score.
        // ============================================
        Route::get('/block/{blockId}/student/{studentId}/basis', [AcademicHeadDashboardController::class, 'basis'])
            ->name('block.student.basis');

        // ============================================
        // RISK SCORING WORKSPACE
        // --------------------------------------------
        // Trigger and monitor scoring runs for every block in the department,
        // with cache/last-run state. Reuses the existing run-scoring,
        // refresh-scoring and scoring-status endpoints — nothing about the
        // scoring engine changes.
        // ============================================
        Route::get('/risk-scoring', [RiskScoringController::class, 'index'])->name('riskScoring');
        // Refreshed summary cards + block rows for the workspace, returned as HTML
        // fragments so a finished scoring run can update the page in place instead of
        // making the Academic Head reload by hand. Read-only: it re-computes and
        // re-renders, it never triggers scoring.
        Route::get('/risk-scoring/data', [RiskScoringController::class, 'data'])->name('riskScoring.data');
        
        // ============================================
        // RECOMMENDATION MANAGEMENT ROUTES (Dedicated Page)
        // ============================================
        Route::get('/recommendations', [InterventionController::class, 'index'])->name('recommendations');
        Route::post('/recommendations/generate-block', [InterventionController::class, 'generateForBlock'])->name('recommendations.generate.block');
        Route::post('/recommendations/generate-selected', [InterventionController::class, 'generateForSelected'])->name('recommendations.generate.selected');
        Route::get('/recommendations/{id}/edit', [InterventionController::class, 'editRecommendation'])->name('recommendation.edit');
        Route::put('/recommendations/{id}', [InterventionController::class, 'updateRecommendation'])->name('recommendation.update');
        Route::delete('/recommendations/{id}/delete', [InterventionController::class, 'deleteRecommendation'])->name('recommendation.delete');
        Route::get('/recommendations/{id}/view', [InterventionController::class, 'viewRecommendation'])->name('recommendation.view');

        // ============================================
        // RISK SCORING ROUTES
        // ============================================
        Route::post('/block/{blockId}/run-scoring', [RiskScoringController::class, 'runScoring'])->name('runScoring');
        Route::post('/block/{blockId}/refresh-scoring', [RiskScoringController::class, 'refreshScoring'])->name('refreshScoring');
        Route::get('/block/{blockId}/scoring-status', [RiskScoringController::class, 'getScoringStatus'])->name('scoringStatus');
        
        // ============================================
        // ESCALATION ROUTES
        // ============================================
        Route::post('/bulk-escalate', [EscalationController::class, 'bulkEscalate'])->name('bulkEscalate');
        // Read-only preview of the recommendation that would be forwarded, so the
        // Academic Head can review/edit it inside the escalation modal before sending.
        Route::post('/escalation/recommendation-preview', [EscalationController::class, 'recommendationPreview'])->name('escalation.recommendationPreview');
        Route::post('/check-escalation-status', [EscalationController::class, 'checkEscalationStatus'])->name('checkEscalationStatus');
        Route::post('/reset-escalation', [EscalationController::class, 'resetEscalation'])->name('resetEscalation');
        Route::post('/bulk-reset-escalation', [EscalationController::class, 'bulkResetEscalation'])->name('bulkResetEscalation');
        Route::post('/can-reset-escalation', [EscalationController::class, 'canResetEscalation'])->name('canResetEscalation');
        Route::get('/block/{blockId}/escalated-students', [EscalationController::class, 'getEscalatedStudentsInBlock'])->name('escalatedStudents');

        // ============================================
        // END-OF-TERM REPORT ROUTES (Academic Head ONLY)
        // --------------------------------------------
        // Deterministic, rule-based generation + PDF export. These routes are
        // reachable exclusively through this `role:academic_head` group, which is
        // what makes the Academic Head the sole authorized generator.
        // ============================================
        Route::get('/reports', [AcademicHeadReportController::class, 'index'])->name('reports');
        Route::post('/reports/generate', [AcademicHeadReportController::class, 'generate'])->name('reports.generate');
        Route::get('/reports/{reportId}', [AcademicHeadReportController::class, 'preview'])->whereNumber('reportId')->name('reports.preview');
        Route::get('/reports/{reportId}/pdf', [AcademicHeadReportController::class, 'download'])->whereNumber('reportId')->name('reports.pdf');
        Route::get('/reports/{reportId}/verify', [AcademicHeadReportController::class, 'verify'])->whereNumber('reportId')->name('reports.verify');
        Route::post('/reports/{reportId}/share', [AcademicHeadReportController::class, 'share'])->whereNumber('reportId')->name('reports.share');
        Route::delete('/reports/{reportId}', [AcademicHeadReportController::class, 'destroy'])->whereNumber('reportId')->name('reports.destroy');
    });
    
    // ======== COUNSELOR ROUTES ========
    Route::prefix('counselor')->name('counselor.')->middleware(['role:guidance_counselor', 'department.isolation'])->group(function () {
        Route::get('/dashboard', [CounselorDashboardController::class, 'index'])->name('dashboard');
        // The caseload list. `scope` selects the view: all (default), open, resolved.
        Route::get('/cases', [CounselorDashboardController::class, 'cases'])->name('cases');
        Route::get('/case/{caseId}', [CounselorDashboardController::class, 'show'])->name('case');
        
        // Session Management
        Route::post('/case/{caseId}/session', [CounselorDashboardController::class, 'storeSession'])->name('session.store');
        
        // Case Actions
        Route::post('/case/{caseId}/resolve', [CounselorDashboardController::class, 'resolve'])->name('resolve');
        Route::post('/case/{caseId}/reopen', [CounselorDashboardController::class, 'reopen'])->name('reopen');
        Route::post('/case/{caseId}/update-priority', [CounselorDashboardController::class, 'updatePriority'])->name('priority.update');
        Route::post('/case/{caseId}/update-status', [CounselorDashboardController::class, 'updateStatus'])->name('status.update');

        // ============================================
        // DEPARTMENT END-OF-TERM REPORTS (read-only consumer)
        // Only reports the Academic Head has shared with the counselor role.
        // ============================================
        Route::get('/reports', [CounselorReportController::class, 'index'])->name('reports');
        Route::get('/reports/{reportId}', [CounselorReportController::class, 'preview'])->whereNumber('reportId')->name('reports.preview');
        Route::get('/reports/{reportId}/pdf', [CounselorReportController::class, 'download'])->whereNumber('reportId')->name('reports.pdf');

        // ============================================
        // MY REPORTS — the counselor's OWN caseload activity summary
        // ============================================
        // Deliberately a separate, distinctly named pair of routes. This is not
        // a department end-of-term report: it aggregates the signed-in
        // counselor's own `cases`/`case_sessions` rows and is available to every
        // counselor regardless of sharing. Generation and sharing of department
        // reports stay exclusive to the Academic Head.
        //
        // Declared AFTER the {reportId} routes but under a distinct prefix, so the
        // whereNumber() constraint is never the thing deciding the match.
        Route::get('/my-reports', [CounselorReportController::class, 'mine'])->name('reports.mine');
        Route::get('/my-reports/pdf', [CounselorReportController::class, 'minePdf'])->name('reports.mine.pdf');
    });
    
    // ======== STUDENT ROUTES ========
    Route::prefix('student')->name('student.')->middleware(['role:student'])->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/grades', [StudentDashboardController::class, 'grades'])->name('grades');
        Route::get('/attendance', [StudentDashboardController::class, 'attendance'])->name('attendance');
        Route::get('/counselor', [StudentDashboardController::class, 'counselor'])->name('counselor');
        
        // Alert Acknowledgment
        Route::post('/acknowledge-alert', [StudentDashboardController::class, 'acknowledgeAlert'])->name('acknowledgeAlert');
        
        // ============================================
        // STUDENT RECOMMENDATION ROUTES
        // ============================================
        Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations');
        Route::post('/recommendation/complete', [RecommendationController::class, 'markCompleted'])->name('recommendation.complete');
        Route::get('/recommendations/pending-count', [RecommendationController::class, 'getPendingCount'])->name('recommendations.pending');
    });
    
    // ======== PROFILE & SETTINGS ROUTES ========
    // Both used to be closures returning views that did not exist, so the
    // top-right dropdown's Profile and Settings links answered HTTP 500 for every
    // role. They are now real pages; the route NAMES are unchanged, so every
    // existing `route('profile')` / `route('settings')` link keeps working.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});
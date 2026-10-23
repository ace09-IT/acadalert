<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\AttendanceSummaryRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\StudentNotificationService;

class DashboardController extends Controller
{
    public function index(Request $request, StudentRepository $students)
    {
        $user = Auth::user();
        
        $student = $students->findByEmail($user->email);
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        $studentInfo = $this->getStudentInfo($student->id);
        
        $riskHistory = $this->getRiskHistory($student->id, $schoolYear);
        $riskTrend = $this->buildRiskTrend($riskHistory);
        
        $currentRisk = $this->getCurrentRisk($student->id, $currentPeriod, $schoolYear);
        $riskFactors = $this->decodeJsonList($currentRisk->risk_factors ?? null);
        
        $subjectGrades = $this->getSubjectGrades($student->id, $currentPeriod, $schoolYear);
        $gradeSummary = $this->buildGradeSummary($subjectGrades);
        
        $attendanceBreakdown = $this->getAttendanceBreakdown($student->id, $currentPeriod, $schoolYear);
        $attendanceSummary = $this->getAttendanceSummary($student->id, $currentPeriod, $schoolYear);

        $alerts = $this->getAlerts($student->id);
        $unreadAlerts = $alerts->where('is_acknowledged', false)->count();

        $recommendations = $this->getRecommendations($student->id);

        $counselorInfo = $this->getCounselorInfo($student->id);

        return view('student.dashboard', [
            'student' => $student,
            'studentInfo' => $studentInfo,
            'currentRisk' => $currentRisk,
            'riskFactors' => $riskFactors,
            'riskTrend' => $riskTrend,
            'subjectGrades' => $subjectGrades,
            'gradeSummary' => $gradeSummary,
            'attendanceBreakdown' => $attendanceBreakdown,
            'attendanceSummary' => $attendanceSummary,
            'alerts' => $alerts,
            'unreadAlerts' => $unreadAlerts,
            'recommendations' => $recommendations,
            'counselorInfo' => $counselorInfo,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
        ]);
    }
    
    public function acknowledgeAlert(Request $request, StudentRepository $students)
    {
        $request->validate([
            'flag_id' => 'required|integer|exists:flags,id',
        ]);

        $user = Auth::user();

        // Student records come from the mock API; flags and acknowledgments stay local.
        $student = $students->findByEmail($user->email);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.'
            ], 404);
        }

        $flag = DB::table('flags')
            ->where('id', $request->flag_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$flag) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found.'
            ], 404);
        }

        if ($flag->is_acknowledged) {
            return response()->json([
                'success' => false,
                'message' => 'Alert already acknowledged.'
            ], 400);
        }

        $exists = DB::table('alert_acknowledgments')
            ->where('student_id', $student->id)
            ->where('flag_id', $request->flag_id)
            ->exists();

        if ($exists) {
            DB::table('alert_acknowledgments')
                ->where('student_id', $student->id)
                ->where('flag_id', $request->flag_id)
                ->update([
                    'acknowledged_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('alert_acknowledgments')->insert([
                'student_id' => $student->id,
                'flag_id' => $request->flag_id,
                'acknowledged_at' => now(),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('flags')
            ->where('id', $request->flag_id)
            ->update([
                'is_acknowledged' => true,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully.'
        ]);
    }


    protected function getStudentInfo(int $studentId)
    {
        $structure = app(AcademicStructureRepository::class);
        $student = app(StudentRepository::class)->find($studentId);
        $placement = $student !== null ? $structure->blockPlacement($student->block_id) : null;

        if ($student === null || $placement === null || $placement['program_id'] === null) {
            return null;
        }

        $info = clone $student;
        $info->block_name = $placement['block_name'] ?? null;
        $info->year_level = $placement['year_level_name'] ?? null;
        $info->program_code = $placement['program_code'] ?? null;
        $info->program_name = $placement['program_name'] ?? null;

        return $info;
    }

    protected function getRiskHistory(int $studentId, string $schoolYear)
    {
        // risk_scores stays local; the FIELD() ordering is applied in PHP.
        return app(RiskScoreRepository::class)->allForStudent($studentId, $schoolYear);
    }

    protected function getCurrentRisk(int $studentId, string $period, string $schoolYear)
    {
        return app(RiskScoreRepository::class)->forStudentPeriod($studentId, $period, $schoolYear);
    }

    protected function getSubjectGrades(int $studentId, string $period, string $schoolYear)
    {
        // grades + the subjects join are both served by the mock API; the
        // repository resolves the join in PHP and orders by numerical_grade.
        return app(GradeRepository::class)->subjectGradesForStudent($studentId, $period, $schoolYear);
    }

    protected function getAttendanceBreakdown(int $studentId, string $period, string $schoolYear)
    {
        // attendance_summaries + the subjects join are both served by the mock API;
        // the repository resolves the join in PHP and orders by attendance_rate.
        return app(AttendanceSummaryRepository::class)
            ->subjectSummariesForStudent($studentId, $period, $schoolYear);
    }

    protected function getAlerts(int $studentId)
    {
        return app(StudentNotificationService::class)->forStudent($studentId);
    }

    protected function getRecommendations(int $studentId)
    {
        $recommendations = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->orderBy('generated_at', 'desc')
            ->get();

        if ($recommendations->isEmpty()) {
            return $recommendations;
        }

        // Completion lives in a separate table (one row per recommendation the
        // student has actually marked done), so the statuses are merged in here.
        $completedIds = DB::table('student_recommendation_tracking')
            ->where('student_id', $studentId)
            ->get()
            ->filter(fn ($row) => (int) $row->is_completed === 1)
            ->pluck('recommendation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($recommendations as $recommendation) {
            $recommendation->is_completed = in_array((int) $recommendation->id, $completedIds, true);
            $recommendation->risk_factors_list = $this->decodeJsonList($recommendation->risk_factors ?? null);
            $recommendation->suggested_actions_list = $this->decodeJsonList($recommendation->suggested_actions ?? null);
        }

        return $recommendations;
    }

    protected function buildRiskTrend($riskHistory): array
    {
        $levelValues = ['Low' => 1, 'Moderate' => 2, 'High' => 3];
        $rows = [];
        $previous = null;

        foreach ($riskHistory as $row) {
            $trend = 'stable';
            $delta = null;

            if ($previous !== null) {
                $delta = (int) $row->risk_score - (int) $previous->risk_score;

                $currentValue = $levelValues[(string) $row->risk_level] ?? 0;
                $previousValue = $levelValues[(string) $previous->risk_level] ?? 0;

                if ($currentValue > $previousValue) {
                    $trend = 'worsening';
                } elseif ($currentValue < $previousValue) {
                    $trend = 'improving';
                }
            }

            $rows[] = [
                'period' => (string) $row->grading_period,
                'score' => (int) $row->risk_score,
                'level' => (string) $row->risk_level,
                'delta' => $delta,
                'trend' => $trend,
            ];

            $previous = $row;
        }

        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return [
            'rows' => $rows,
            'overall' => $last['trend'] ?? 'unknown',
            'delta' => $last['delta'] ?? null,
        ];
    }

    protected function buildGradeSummary($rows): array
    {
        $values = $rows->map(fn ($row) => (float) $row->numerical_grade)->values();

        return [
            'total' => $rows->count(),
            'average' => $values->isEmpty() ? null : round((float) $values->avg(), 2),
            'failing' => $values->filter(fn ($grade) => $grade < 75)->count(),
            'highest' => $values->isEmpty() ? null : (float) $values->max(),
            'lowest' => $values->isEmpty() ? null : (float) $values->min(),
        ];
    }

    protected function getAttendanceSummary(int $studentId, string $period, string $schoolYear): ?object
    {
        return app(AttendanceSummaryRepository::class)
            ->aggregateForStudent($studentId, $period, $schoolYear);
    }

    protected function decodeJsonList($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function getCounselorInfo(int $studentId)
    {
        // students + the placement chain are served by the mock API now, so the
        // student's department is an index lookup.
        $departmentId = app(AcademicStructureRepository::class)->departmentIdForStudent($studentId);

        if ($departmentId === null) {
            return null;
        }

        $staff = app(StaffRepository::class);

        $counselor = $staff->counselors()->first(fn ($row) => (
            (int) $row->department_id === $departmentId || $row->department_id === null
        ));

        if ($counselor === null) {
            return null;
        }

        $user = $staff->user($counselor->user_id);

        $decorated = clone $counselor;
        $decorated->name = $user->name ?? null;
        $decorated->email = $user->email ?? null;

        return $decorated;
    }


    public function grades(Request $request, StudentRepository $students)
    {
        $user = Auth::user();

        // Student records come from the mock API.
        $student = $students->findByEmail($user->email);
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        $studentInfo = $this->getStudentInfo($student->id);
        $subjectGrades = $this->getSubjectGrades($student->id, $currentPeriod, $schoolYear);
        $periods = ['Prelim', 'Midterm', 'Finals'];
        
        return view('student.grades', compact('student', 'studentInfo', 'subjectGrades', 'currentPeriod', 'schoolYear', 'periods'));
    }

    public function attendance(Request $request, StudentRepository $students)
    {
        $user = Auth::user();

        // Student records come from the mock API.
        $student = $students->findByEmail($user->email);
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        $studentInfo = $this->getStudentInfo($student->id);
        $attendanceBreakdown = $this->getAttendanceBreakdown($student->id, $currentPeriod, $schoolYear);
        $periods = ['Prelim', 'Midterm', 'Finals'];
        
        $attendanceSummary = app(AttendanceSummaryRepository::class)
            ->aggregateForStudent($student->id, $currentPeriod, $schoolYear);
        
        return view('student.attendance', compact(
            'student', 'studentInfo', 'attendanceBreakdown', 'attendanceSummary',
            'currentPeriod', 'schoolYear', 'periods'
        ));
    }

    public function counselor(Request $request, StudentRepository $students)
    {
        $user = Auth::user();

        // Student records come from the mock API.
        $student = $students->findByEmail($user->email);
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $studentInfo = $this->getStudentInfo($student->id);
        $counselorInfo = $this->getCounselorInfo($student->id);
        $currentRisk = $this->getCurrentRisk($student->id, 'Midterm', '2024-2025');
        
        return view('student.counselor', compact('student', 'studentInfo', 'counselorInfo', 'currentRisk'));
    }
}
<?php

namespace App\Services;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterventionService
{
    public const GRADING_PERIODS = ['Prelim', 'Midterm', 'Finals'];

    public const SCHOOL_YEAR = '2024-2025';

    protected $aiStudio;
    protected $riskScoring;
    protected $progress = [];

    /** Student records — external mock API. */
    protected StudentRepository $students;

    /** Academic placement chain — external mock API. */
    protected AcademicStructureRepository $structure;

    protected RecommendationCondenser $condenser;

    public static function normalizePeriod(?string $period): string
    {
        $candidate = ucfirst(strtolower(trim((string) $period)));

        $aliases = [
            'Prelim' => 'Prelim',
            'Preliminary' => 'Prelim',
            'Midterm' => 'Midterm',
            'Mid' => 'Midterm',
            'Final' => 'Finals',
            'Finals' => 'Finals',
        ];

        return $aliases[$candidate] ?? 'Midterm';
    }

    public function __construct(
        ?RiskScoringService $riskScoring = null,
        ?StudentRepository $students = null,
        ?AcademicStructureRepository $structure = null,
        ?RecommendationCondenser $condenser = null
    ) {
        $this->aiStudio = new AIStudioApiService();

        $this->riskScoring = $riskScoring ?? new RiskScoringService();

        $this->students = $students ?? app(StudentRepository::class);
        $this->structure = $structure ?? app(AcademicStructureRepository::class);

        $this->condenser = $condenser ?? new RecommendationCondenser();
    }

    public function generateForMultiple(array $studentIds, string $gradingPeriod = 'Midterm'): array
    {
        $gradingPeriod = self::normalizePeriod($gradingPeriod);

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $total = count($studentIds);
        $progress = [];

        foreach ($studentIds as $index => $studentId) {
            $current = $index + 1;
            $percentage = round(($current / $total) * 100);
            
            $progress[] = [
                'student_id' => $studentId,
                'status' => 'processing',
                'current' => $current,
                'total' => $total,
                'percentage' => $percentage,
            ];

            $result = $this->generateForStudent($studentId, $gradingPeriod);
            $results[$studentId] = $result;
            
            if ($result['success']) {
                $successCount++;
                $progress[$index]['status'] = 'success';
            } else {
                $failedCount++;
                $progress[$index]['status'] = 'failed';
                $progress[$index]['error'] = $result['error'] ?? 'Unknown error';
            }
        }

        return [
            'total' => $total,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'progress' => $progress,
        ];
    }

    public function generateForBlock(int $blockId, string $gradingPeriod = 'Midterm'): array
    {
        $gradingPeriod = self::normalizePeriod($gradingPeriod);

        // Students come from the mock API now.
        $students = $this->students->forBlock($blockId, true)
            ->pluck('id')
            ->toArray();

        if (empty($students)) {
            return [
                'success' => false,
                'message' => 'No students found in this block.',
            ];
        }

        return $this->generateForMultiple($students, $gradingPeriod);
    }

    public function generateForStudent(int $studentId, string $gradingPeriod = 'Midterm'): array
    {
        try {
            $gradingPeriod = self::normalizePeriod($gradingPeriod);

            $schoolYear = self::SCHOOL_YEAR;

            $student = $this->students->find($studentId);
            if (!$student) {
                return ['success' => false, 'error' => 'Student not found.'];
            }

            $studentInfo = $this->getStudentInfo($studentId);
            
            $riskScore = DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->first();

            if (!$riskScore) {
                return [
                    'success' => false,
                    'error' => 'No risk score found for this student. Please run risk scoring first.'
                ];
            }

            $riskFactors = json_decode($riskScore->risk_factors, true) ?? [];

            $gradeData = $this->riskScoring->getGrades([$studentId], $gradingPeriod, $schoolYear)[$studentId] ?? [
                'subjects' => [],
                'total_subjects' => 0,
                'avg_grade' => 0,
                'failing_subjects' => 0,
            ];

            $attendanceData = $this->riskScoring->getAttendance([$studentId], $gradingPeriod, $schoolYear)[$studentId] ?? [
                'attendance_rate' => 100.0,
                'absences' => 0,
                'lates' => 0,
            ];

            $studentData = [
                'id' => $studentId,
                'name' => $student->first_name . ' ' . $student->last_name,
                'program' => $studentInfo->program_code ?? 'N/A',
                'risk_level' => $riskScore->risk_level,
                'risk_factors' => $riskFactors,
                'subjects' => $gradeData['subjects'],
                'total_subjects' => $gradeData['total_subjects'],
                'failing_subjects' => $gradeData['failing_subjects'],
                'avg_grade' => $gradeData['avg_grade'],
                'attendance_rate' => $attendanceData['attendance_rate'],
                'absences' => $attendanceData['absences'],
                'lates' => $attendanceData['lates'],
            ];

            $rawRecommendations = $this->aiStudio->generateInterventions($studentId, $studentData);

            $recommendations = $this->condenser->condense($rawRecommendations);

            if (count($recommendations) < count($rawRecommendations)) {
                Log::info('Intervention recommendations condensed', [
                    'student_id' => $studentId,
                    'grading_period' => $gradingPeriod,
                    'raw_actions' => count($rawRecommendations),
                    'kept_actions' => count($recommendations),
                    'dropped' => max(0, count($rawRecommendations) - count($recommendations)),
                    'kept' => array_column($recommendations, 'action'),
                ]);
            }

            $this->deleteExistingRecommendations($studentId, $gradingPeriod, $schoolYear);
            $this->saveRecommendations($studentId, $riskFactors, $recommendations, $gradingPeriod, $schoolYear);

            return [
                'success' => true,
                'message' => 'Recommendations generated successfully.',
                'recommendations' => $recommendations,
            ];

        } catch (\Exception $e) {
            Log::error('Intervention generation failed for student ' . $studentId, [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function deleteExistingRecommendations(int $studentId, string $gradingPeriod, string $schoolYear): void
    {
        $recommendationIds = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->pluck('id')
            ->toArray();

        if (empty($recommendationIds)) {
            return;
        }

        DB::table('student_recommendation_tracking')
            ->whereIn('recommendation_id', $recommendationIds)
            ->delete();

        DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();
    }

    protected function saveRecommendations(
        int $studentId,
        array $riskFactors,
        array $recommendations,
        string $gradingPeriod,
        string $schoolYear
    ): void {
        DB::table('intervention_recommendations')->insert([
            'student_id' => $studentId,
            'grading_period' => $gradingPeriod,
            'school_year' => $schoolYear,
            'risk_factors' => json_encode($riskFactors),
            'suggested_actions' => json_encode($recommendations),
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getStudentRecommendations(
        int $studentId,
        ?string $gradingPeriod = null,
        ?string $schoolYear = null
    ): array {
        $recommendations = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->when($gradingPeriod, function ($query) use ($gradingPeriod, $schoolYear) {
                return $query->where('grading_period', self::normalizePeriod($gradingPeriod))
                    ->where('school_year', $schoolYear ?: self::SCHOOL_YEAR);
            })
            ->orderBy('generated_at', 'desc')
            ->get();

        return $recommendations->toArray();
    }

    public function getTrackingStatus(int $studentId): array
    {
        $tracking = DB::table('student_recommendation_tracking')
            ->join('intervention_recommendations', 'student_recommendation_tracking.recommendation_id', '=', 'intervention_recommendations.id')
            ->where('student_recommendation_tracking.student_id', $studentId)
            ->select(
                'student_recommendation_tracking.*',
                'intervention_recommendations.suggested_actions'
            )
            ->get();

        return $tracking->toArray();
    }

    public function markCompleted(int $studentId, int $recommendationId): array
    {
        $exists = DB::table('student_recommendation_tracking')
            ->where('student_id', $studentId)
            ->where('recommendation_id', $recommendationId)
            ->exists();

        if ($exists) {
            DB::table('student_recommendation_tracking')
                ->where('student_id', $studentId)
                ->where('recommendation_id', $recommendationId)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('student_recommendation_tracking')->insert([
                'student_id' => $studentId,
                'recommendation_id' => $recommendationId,
                'is_completed' => true,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Recommendation marked as completed.',
        ];
    }

    protected function getStudentInfo(int $studentId)
    {
        $student = $this->students->find($studentId);
        $placement = $student !== null
            ? $this->structure->blockPlacement($student->block_id)
            : null;

        if ($student === null || $placement === null || $placement['program_id'] === null) {
            return null;
        }

        $info = clone $student;
        $info->program_code = $placement['program_code'] ?? null;

        return $info;
    }
}
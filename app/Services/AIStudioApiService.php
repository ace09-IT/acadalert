<?php

namespace App\Services;

use App\Exceptions\AIStudioApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIStudioApiService
{
    protected $apiKey;
    protected $apiUrl;
    protected $timeout;
    protected $retryLimit;

    protected $batchRetryLimit;

    public function __construct()
    {
        $this->apiKey = config('services.aistudio.api_key');
        $this->apiUrl = config('services.aistudio.api_url');
        $this->timeout = config('services.aistudio.timeout', 30);
        $this->retryLimit = config('services.aistudio.retry_limit', 2);
        $this->batchRetryLimit = (int) config('services.aistudio.batch_retry_limit', 2);
    }

    public function getRiskScore(array $studentData, array $bands = []): array
    {
        $prompt = $this->buildRiskPrompt($studentData, $bands);

        $responseText = $this->sendRequest($prompt);

        return $this->parseRiskResponse($responseText, $studentData);
    }

    public function getInterventions(array $studentData, array $riskFactors): array
    {
        $prompt = $this->buildInterventionPrompt($studentData, $riskFactors);

        $responseText = $this->sendRequest($prompt);

        return $this->parseInterventionResponse($responseText);
    }

    public function generateInterventions(int $studentId, array $studentData): array
    {
        $riskFactors = $studentData['risk_factors'] ?? [];
        $prompt = $this->buildInterventionPrompt($studentData, $riskFactors);

        $responseText = $this->sendRequest($prompt);

        return $this->parseInterventionResponse($responseText);
    }

    public function getRiskScoresForBatch(array $studentsData, array $bands = []): array
    {
        $prompt = $this->buildBatchRiskPrompt($studentsData, $bands);

        $parsed = $this->parseBatchRiskResponsePartial($this->sendRequest($prompt), $studentsData);

        $scores = $parsed['scores'];
        $missing = $parsed['missing'];

        if ($missing !== [] && $this->batchRetryLimit > 0) {
            $missing = $this->retryOmittedStudents($studentsData, $missing, $bands, $scores);
        }

        if ($missing !== []) {
            $detail = count($missing) === count($studentsData)
                ? 'no student in the batch'
                : 'student ' . implode(', ', $missing);

            Log::error('AI Studio batch risk scoring failed: response omitted '.$detail.' after retries', [
                'omitted_student_ids' => $missing,
                'batch_retry_limit' => $this->batchRetryLimit,
                'batch_size' => count($studentsData),
            ]);

            throw new AIStudioApiException(
                'AI Studio API batch response omitted student '.$missing[0].' after '
                .$this->batchRetryLimit.' retr'.($this->batchRetryLimit === 1 ? 'y' : 'ies').'.'
            );
        }

        return $scores;
    }

    protected function retryOmittedStudents(
        array $studentsData,
        array $missing,
        array $bands,
        array &$scores
    ): array {
        // Re-asking costs a request, so keep the documented free-tier cadence
        // (1 request / batch_delay_seconds) before every retry.
        $delaySeconds = (int) config('services.aistudio.batch_delay_seconds', 4);

        $byId = [];
        foreach ($studentsData as $student) {
            $byId[(int) $student['id']] = $student;
        }

        for ($attempt = 1; $attempt <= $this->batchRetryLimit && $missing !== []; $attempt++) {
            $pending = [];
            foreach ($missing as $sid) {
                if (isset($byId[$sid])) {
                    $pending[] = $byId[$sid];
                }
            }

            if ($pending === []) {
                break;
            }

            Log::warning('AI Studio batch response was incomplete — retrying the omitted students', [
                'attempt' => $attempt,
                'batch_retry_limit' => $this->batchRetryLimit,
                'omitted_student_ids' => $missing,
                'retry_size' => count($pending),
                'delay_seconds' => $delaySeconds,
            ]);

            if ($delaySeconds > 0) {
                usleep($delaySeconds * 1_000_000);
            }

            $retry = $this->parseBatchRiskResponsePartial(
                $this->sendRequest($this->buildBatchRiskPrompt($pending, $bands)),
                $pending
            );

            foreach ($retry['scores'] as $sid => $score) {
                $scores[$sid] = $score;
            }

            if ($retry['missing'] === []) {
                Log::info('AI Studio batch retry recovered every omitted student', [
                    'attempt' => $attempt,
                    'recovered_student_ids' => $missing,
                ]);
            }

            $missing = $retry['missing'];
        }

        return array_values($missing);
    }

    protected function buildBatchRiskPrompt(array $studentsData, array $bands = []): string
    {
        $studentBlocks = [];

        foreach ($studentsData as $index => $student) {
            $number = $index + 1;
            $name = $student['name'] ?? 'Unknown';
            $avgGrade = $student['avg_grade'] ?? 0;
            $totalSubjects = $student['total_subjects'] ?? 0;
            $failingCount = $student['failing_subjects'] ?? 0;
            $attendanceRate = $student['attendance_rate'] ?? 0;
            $absences = $student['absences'] ?? 0;
            $lates = $student['lates'] ?? 0;
            $previousRisk = $student['previous_risk'] ?? 'Low';

            $subjectLines = '';
            if (!empty($student['subjects']) && is_array($student['subjects'])) {
                foreach ($student['subjects'] as $subject) {
                    $code = $subject['subject_code'] ?? 'N/A';
                    $subjectName = $subject['subject_name'] ?? 'Subject';
                    $grade = $subject['numerical_grade'] ?? 0;
                    $subjectLines .= "        - {$code} {$subjectName}: {$grade}\n";
                }
            }
            if ($subjectLines === '') {
                $subjectLines = "        - No subject grades available.\n";
            }

            $studentBlocks[] =
                "Student {$number}:\n" .
                "  student_id: {$student['id']}\n" .
                "  name: {$name}\n" .
                "  avg_grade: {$avgGrade}\n" .
                "  total_subjects: {$totalSubjects}\n" .
                "  failing_count: {$failingCount}\n" .
                "  attendance_rate: {$attendanceRate}\n" .
                "  absences: {$absences}\n" .
                "  lates: {$lates}\n" .
                "  previous_risk: {$previousRisk}\n" .
                "  individual_subject_grades (evaluate EACH one):\n{$subjectLines}";
        }

        $batchData = implode("\n", $studentBlocks);
        $count = count($studentsData);

        return "You are an academic risk assessment system for Universidad de Dagupan.\n" .
               "Below is a BATCH of {$count} students. For EVERY student in the batch, evaluate " .
               "each individual subject grade (not just the average), the failing_count (subject " .
               "grades below 75), and the attendance record to determine academic risk independently.\n\n" .
               "BATCH DATA:\n{$batchData}\n\n" .
               "Instructions:\n" .
               "- Evaluate every student in the batch independently (do not compare students).\n" .
               "- Echo each student's student_id EXACTLY as provided, so results can be mapped.\n" .
               "- Return exactly {$count} objects — one per student.\n\n" .
               "Return ONLY a JSON array (no other text), with this structure:\n" .
               "[\n" .
               '  {"student_id": <int>, "risk_score": <int 0-100>, "risk_level": "Low|Moderate|High", ' .
               '"risk_factors": ["factor1","factor2"], "explanation": "brief reason"},' . "\n" .
               "  ...\n" .
               "]\n\n" .
               "Risk Level Guidelines:\n" .
               $this->renderBandGuidelines($bands) .
               "- The FINAL risk level is assigned deterministically by the system from your " .
               "risk_score using these bands; return your own best level as well for audit.";
    }

    protected function parseBatchRiskResponse(string $response, array $studentsData): array
    {
        $parsed = $this->parseBatchRiskResponsePartial($response, $studentsData);

        if ($parsed['missing'] !== []) {
            throw new AIStudioApiException(
                'AI Studio API batch response omitted student '.$parsed['missing'][0].'.'
            );
        }

        return $parsed['scores'];
    }

    protected function parseBatchRiskResponsePartial(string $response, array $studentsData): array
    {
        $json = $this->extractJson($response, true);

        if (!$json) {
            throw new AIStudioApiException('AI Studio API returned an unparseable batch risk scoring response.');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new AIStudioApiException('AI Studio API batch risk scoring response is not a JSON array.');
        }

        $expectedIds = [];
        foreach ($studentsData as $student) {
            $expectedIds[(int) $student['id']] = (int) $student['id'];
        }

        $scores = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || !isset($entry['student_id'])) {
                continue;
            }

            $sid = (int) $entry['student_id'];
            if (!isset($expectedIds[$sid])) {
                continue;
            }

            if (!isset($entry['risk_score']) || !isset($entry['risk_level'])) {
                throw new AIStudioApiException(
                    "AI Studio API batch response is missing required risk fields for student {$sid}."
                );
            }

            $scores[$sid] = [
                'student_id' => $sid,
                'risk_score' => (int) $entry['risk_score'],
                'risk_level' => $entry['risk_level'],
                'risk_factors' => $entry['risk_factors'] ?? [],
                'explanation' => $entry['explanation'] ?? 'AI assessment complete.',
            ];
        }

        $missing = [];
        foreach ($expectedIds as $sid) {
            if (!isset($scores[$sid])) {
                $missing[] = $sid;
            }
        }

        return ['scores' => $scores, 'missing' => $missing];
    }

    protected function renderBandGuidelines(array $bands): string
    {
        $bands = $bands ?: RiskThresholdService::DEFAULTS;

        $lines = [];
        foreach (RiskThresholdService::bandsForPrompt($bands) as $band) {
            $lines[] = "- " . $band["level"] . ": " . $band["min"] . "-" . $band["max"];
        }

        return implode("\n", $lines) . "\n";
    }

    protected function buildRiskPrompt(array $data, array $bands = []): string
    {
        $subjectLines = '';
        if (!empty($data['subjects']) && is_array($data['subjects'])) {
            foreach ($data['subjects'] as $index => $subject) {
                $number = $index + 1;
                $code = $subject['subject_code'] ?? 'N/A';
                $name = $subject['subject_name'] ?? 'Subject';
                $grade = $subject['numerical_grade'] ?? 0;
                $subjectLines .= "- Subject {$number} ({$code}) {$name}: {$grade}\n";
            }
        }
        if ($subjectLines === '') {
            $subjectLines = "- No subject grades available.\n";
        }

        $avgGrade = $data['avg_grade'] ?? 0;
        $totalSubjects = $data['total_subjects'] ?? 0;
        $failingSubjects = $data['failing_subjects'] ?? 0;
        $attendanceRate = $data['attendance_rate'] ?? 0;
        $previousRisk = $data['previous_risk'] ?? 'Low';
        $absences = $data['absences'] ?? 0;
        $lates = $data['lates'] ?? 0;

        return "You are an academic risk assessment system for Universidad de Dagupan. " .
               "Analyze this student data and return a JSON response only (no other text):\n\n" .
               "Student Data:\n" .
               "- Average grade across all subjects: {$avgGrade}%\n" .
               "- Number of subjects enrolled: {$totalSubjects}\n" .
               "- Number of subjects below 75: {$failingSubjects}\n" .
               "- Individual subject grades (evaluate EACH one):\n{$subjectLines}\n" .
               "- Attendance rate: {$attendanceRate}%\n" .
               "- Previous risk level: {$previousRisk}\n" .
               "- Number of absences: {$absences}\n" .
               "- Number of lates: {$lates}\n\n" .
               "Instructions:\n" .
               "- Evaluate each individual subject grade listed above, not just the average.\n" .
               "- Consider how many subjects are failing (below 75), the severity of each\n" .
               "  subject grade, and the attendance record when determining overall academic risk.\n\n" .
               "Return JSON with this structure:\n" .
               "{\n" .
               '  "risk_score": integer (0-100),' . "\n" .
               '  "risk_level": "Low" or "Moderate" or "High",' . "\n" .
               '  "risk_factors": ["factor1", "factor2"],' . "\n" .
               '  "explanation": "brief reason for the risk assessment"' . "\n" .
               "}\n\n" .
               "Risk Level Guidelines:\n" .
               $this->renderBandGuidelines($bands) .
               "- The FINAL risk level is assigned deterministically by the system from your " .
               "risk_score using these bands; return your own best level as well for audit.";
    }

    protected function buildInterventionPrompt(array $studentData, array $riskFactors): string
    {
        $studentName = isset($studentData['name']) ? $studentData['name'] : 'Unknown';
        $studentProgram = isset($studentData['program']) ? $studentData['program'] : 'N/A';
        $studentRiskLevel = isset($studentData['risk_level']) ? $studentData['risk_level'] : 'Unknown';
        $riskFactorsString = !empty($riskFactors) ? implode(", ", $riskFactors) : 'None identified';
        $avgGrade = isset($studentData['avg_grade']) ? $studentData['avg_grade'] : 0;
        $attendanceRate = isset($studentData['attendance_rate']) ? $studentData['attendance_rate'] : 0;
        $totalSubjects = $studentData['total_subjects'] ?? 0;
        $failingSubjects = $studentData['failing_subjects'] ?? 0;

        // Individual per-subject grades — the AI must evaluate each one, not just
        // the average (same raw source as the risk scoring engine).
        $subjectLines = '';
        if (!empty($studentData['subjects']) && is_array($studentData['subjects'])) {
            foreach ($studentData['subjects'] as $index => $subject) {
                $number = $index + 1;
                $code = $subject['subject_code'] ?? 'N/A';
                $name = $subject['subject_name'] ?? 'Subject';
                $grade = $subject['numerical_grade'] ?? 0;
                $subjectLines .= "- Subject {$number} ({$code}) {$name}: {$grade}\n";
            }
        }
        if ($subjectLines === '') {
            $subjectLines = "- No subject grades available.\n";
        }
        
        return "You are an academic intervention system for Universidad de Dagupan. " .
               "Based on these risk factors, suggest appropriate interventions.\n\n" .
               "Student: {$studentName}\n" .
               "Program: {$studentProgram}\n" .
               "Risk Level: {$studentRiskLevel}\n" .
               "Risk Factors: " . $riskFactorsString . "\n" .
               "Average Grade: {$avgGrade}%\n" .
               "Subjects Enrolled: {$totalSubjects}\n" .
               "Subjects Failing (below 75): {$failingSubjects}\n" .
               "Individual Subject Grades (evaluate EACH one):\n{$subjectLines}\n" .
               "Attendance Rate: {$attendanceRate}%\n\n" .
               "Instructions:\n" .
               "- Base the actions on the individual subject grades listed above, not only on the\n" .
               "  average grade.\n" .
               "- Return 1 to 3 actions and never more than three: fewer is better, and every one\n" .
               "  must be something a counselor can carry out this week.\n" .
               "- One sentence per action, at most 90 characters, starting with an imperative verb.\n" .
               "  Name the concrete step plus a target or frequency, for example\n" .
               "  \"Twice-weekly tutoring for CSC102\" or \"Meet the guardian this week\".\n" .
               "- Do NOT explain, justify or repeat the grades, attendance rate or risk level, and do\n" .
               "  not use \"because\", \"due to\", \"since\", \"as\" or \"given\" clauses. The risk factors\n" .
               "  already say why — state only what to do.\n" .
               "- Never recommend the same kind of help twice: tutoring, peer tutoring, academic\n" .
               "  coaching and academic advising are ONE action, and that action is tutoring.\n" .
               "- Recommend an action only when the data justifies it:\n" .
               "    tutoring              - at least one subject below 75\n" .
               "    counseling            - risk level High, or two or more subjects below 75\n" .
               "    study_skills          - average grade below 80\n" .
               "    attendance_monitoring - attendance rate below 85%\n" .
               "    parent_meeting        - risk level High, or three or more subjects below 75\n" .
               "    career_guidance       - risk level Low with no subject below 75\n" .
               "- Priority: at most two actions are high, the rest medium; low is only for optional\n" .
               "  support. List the highest priority first.\n\n" .
               "Return JSON with this structure:\n" .
               "{\n" .
               '  "recommendations": [\n' .
               '    {"action": "tutoring", "details": "description", "priority": "high/medium/low"},\n' .
               '    {"action": "parent_meeting", "details": "description", "priority": "high/medium/low"}\n' .
               "  ]\n" .
               "}\n\n" .
               "Available actions: tutoring, counseling, study_skills, attendance_monitoring, " .
               "parent_meeting, career_guidance";
    }

    protected function sendRequest(string $prompt, int $attempt = 1): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                throw new AIStudioApiException(
                    'AI Studio API request failed with HTTP ' . $response->status() . ': ' . $response->body()
                );
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if ($text === '') {
                throw new AIStudioApiException('AI Studio API returned an empty response payload.');
            }

            return $text;
        } catch (AIStudioApiException $e) {
            Log::error('AI Studio API request failed', [
                'message' => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $this->retryLimit) {
                // Rate-limit (HTTP 429) backoff before retrying so the free tier is
                // never hammered. Batch spacing (4s) already keeps us at <= 15 req/min.
                if (str_contains($e->getMessage(), 'HTTP 429')) {
                    $backoff = (int) config('services.aistudio.rate_limit_backoff_seconds', 5);
                    Log::warning('AI Studio API rate limit (429) — backing off before retry', [
                        'backoff_seconds' => $backoff,
                        'attempt' => $attempt,
                    ]);
                    usleep($backoff * 1_000_000);
                }

                return $this->sendRequest($prompt, $attempt + 1);
            }

            throw $e;
        } catch (\Exception $e) {
            Log::error('AI Studio API connection exception', [
                'message' => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $this->retryLimit) {
                return $this->sendRequest($prompt, $attempt + 1);
            }

            throw new AIStudioApiException(
                'AI Studio API connectivity failure: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    protected function parseRiskResponse(string $response, array $studentData): array
    {
        $json = $this->extractJson($response, false);

        if (!$json) {
            throw new AIStudioApiException('AI Studio API returned an unparseable risk scoring response.');
        }

        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['risk_score']) || !isset($data['risk_level'])) {
            throw new AIStudioApiException(
                'AI Studio API risk scoring response is missing required fields (risk_score / risk_level).'
            );
        }

        return [
            'success' => true,
            'risk_score' => (int) $data['risk_score'],
            'risk_level' => $data['risk_level'],
            'risk_factors' => $data['risk_factors'] ?? [],
            'explanation' => $data['explanation'] ?? 'AI assessment complete.',
        ];
    }

    protected function parseInterventionResponse(string $response): array
    {
        $candidates = [];
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $candidates[] = $matches[0];
        }
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            $candidates[] = $matches[0];
        }

        foreach ($candidates as $json) {
            $data = json_decode($json, true);

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['recommendations']) && is_array($data['recommendations'])) {
                return $data['recommendations'];
            }

            if (array_is_list($data) && !empty($data) && is_array($data[0])) {
                return $data;
            }
        }

        throw new AIStudioApiException(
            'AI Studio API intervention response is missing the recommendations list.'
        );
    }

    protected function extractJson(string $text, bool $preferArray = false): ?string
    {
        $objectPattern = '/\{.*\}/s';
        $arrayPattern = '/\[.*\]/s';

        $candidates = [];
        foreach ($preferArray ? [$arrayPattern, $objectPattern] : [$objectPattern, $arrayPattern] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $candidates[] = $matches[0];
            }
        }

        // Return the first candidate that is valid JSON (preferred shape first);
        // fall back to the first candidate so the caller can raise a precise error.
        foreach ($candidates as $candidate) {
            if (json_decode($candidate, true) !== null) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }
}
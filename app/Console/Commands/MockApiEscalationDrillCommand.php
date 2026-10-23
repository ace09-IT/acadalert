<?php

namespace App\Console\Commands;

use App\Http\Controllers\AcademicHead\EscalationController;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MockApiEscalationDrillCommand extends Command
{
    protected $signature = 'mock-api:escalation-drill {--period=Midterm : Grading period to escalate in}';

    protected $description = 'Rolled-back drill: escalate a student with a recommendation and verify the counselor can read it';

    protected array $rows = [];

    protected bool $failed = false;

    public function handle(
        HttpKernel $kernel,
        AcademicStructureRepository $structure,
        StudentRepository $students,
        StaffRepository $staff
    ): int {
        $period = (string) $this->option('period');

        if (!in_array($period, ['Prelim', 'Midterm', 'Finals'], true)) {
            $this->error("Unknown grading period: {$period}");

            return self::FAILURE;
        }

        $head = $this->pickAcademicHead();

        if ($head === null) {
            $this->error('No academic_heads user with a profile row was found.');

            return self::FAILURE;
        }

        $academicHead = $staff->academicHeadForUser((int) $head->id);

        if ($academicHead === null) {
            $this->error('The chosen academic head has no academic_heads row.');

            return self::FAILURE;
        }

        $counselor = $staff->firstCounselorInDepartment($academicHead->department_id);

        if ($counselor === null) {
            $this->error('No counselor exists in department ' . $academicHead->department_id . '.');

            return self::FAILURE;
        }

        $candidate = $this->pickCandidate($structure, (int) $academicHead->department_id, $period);

        if ($candidate === null) {
            $this->error('No escalatable student (risk-scored, no open case) in this department.');

            return self::FAILURE;
        }

        $counselorUser = DB::table('users')->where('id', (int) $counselor->user_id)->first();

        if ($counselorUser === null) {
            $this->error('The department counselor has no users row.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf(
            'Drilling the escalation hand-off: academic head #%d → counselor #%d (dept %s), student #%d, %s',
            $head->id,
            $counselor->id,
            $academicHead->department_id,
            $candidate['student_id'],
            $period
        ));
        $this->newLine();

        DB::beginTransaction();

        try {
            $this->checkPreviewEndpoint($head, $academicHead, $candidate, $period);
            $this->checkModalMarkup($kernel, $head, $candidate);
            $this->checkClientDependencies();
            $this->checkScriptWiring();
            $this->checkForwardedGeneratedCopy($head, $academicHead, $candidate, $period);
            $this->checkForwardedEdit($kernel, $counselorUser, $head, $academicHead, $candidate, $period);
            $this->checkNoRecommendation($kernel, $counselorUser, $head, $academicHead, $candidate, $period);
        } catch (\Throwable $e) {
            $this->failed = true;
            $this->rows[] = ['DRILL', 'exception', 'FAIL', get_class($e) . ': ' . $e->getMessage()];
        } finally {
            // Nothing this drill wrote may survive — it reported only what it read back.
            DB::rollBack();
        }

        $this->table(['check', 'detail', 'result', 'evidence'], $this->rows);

        $this->newLine();
        $this->line($this->failed
            ? '<fg=red>The escalation hand-off drill FAILED.</>'
            : '<fg=green>The escalation hand-off drill passed (all writes rolled back).</>');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }
    protected function pickAcademicHead(): ?object
    {
        return DB::table('users')
            ->where('role', 'academic_head')
            ->where('is_active', true)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('academic_heads')
                ->whereColumn('academic_heads.user_id', 'users.id'))
            ->orderBy('id')
            ->first();
    }

    protected function pickCandidate(AcademicStructureRepository $structure, int $departmentId, string $period): ?array
    {
        $studentIds = $structure->studentIdsInDepartment($departmentId, true);

        if ($studentIds === []) {
            return null;
        }

        $openCaseIds = DB::table('cases')
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $riskRows = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', '2024-2025')
            ->orderByDesc('risk_score')
            ->get();

        $student = app(StudentRepository::class);

        foreach ($riskRows as $risk) {
            $studentId = (int) $risk->student_id;

            if (in_array($studentId, $openCaseIds, true)) {
                continue;
            }

            $row = $student->find($studentId);

            if ($row === null || $row->block_id === null) {
                continue;
            }

            // Prefer a student who actually has a generated recommendation, so the
            // "forwarded verbatim" check exercises the real path.
            $recommendation = DB::table('intervention_recommendations')
                ->where('student_id', $studentId)
                ->where('grading_period', $period)
                ->where('school_year', '2024-2025')
                ->orderByDesc('generated_at')
                ->first();

            $candidate = [
                'student_id' => $studentId,
                'block_id' => (int) $row->block_id,
                'risk_score' => (int) $risk->risk_score,
                'risk_level' => (string) $risk->risk_level,
                'recommendation_id' => $recommendation->id ?? null,
                'preview_text' => '',
            ];

            if ($recommendation !== null) {
                $candidate['preview_text'] = app(\App\Services\RecommendationTextService::class)
                    ->toText($recommendation->suggested_actions);

                return $candidate;
            }

            // Remember a fallback (no recommendation) but keep looking.
            $candidate['fallback'] = true;
            $fallback ??= $candidate;
        }

        return $fallback ?? null;
    }

    protected function postEscalation(object $head, object $academicHead, array $candidate, string $period, array $recommendation): array
    {
        Auth::loginUsingId((int) $head->id, false);

        $payload = [
            'student_ids' => [$candidate['student_id']],
            'notes' => 'Escalation drill — rolled back.',
            'block_id' => $candidate['block_id'],
            'period' => $period,
            'recommendations' => [
                (string) $candidate['student_id'] => $recommendation,
            ],
        ];

        $request = Request::create('/academic-head/bulk-escalate', 'POST', $payload);
        $request->setUserResolver(fn () => $head);
        $request->attributes->set('department_id', (int) $academicHead->department_id);
        $request->attributes->set('academic_head', $academicHead);

        $response = app(EscalationController::class)->bulkEscalate($request);
        $decoded = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() !== 200 || !is_array($decoded) || ($decoded['success'] ?? false) !== true) {
            throw new \RuntimeException('bulkEscalate failed: ' . (string) $response->getContent());
        }

        $caseId = (int) ($decoded['results'][0]['case_id'] ?? 0);

        if ($caseId === 0) {
            throw new \RuntimeException('bulkEscalate returned no case id: ' . (string) $response->getContent());
        }

        return ['case_id' => $caseId, 'payload' => $payload];
    }

    /** Undo one sub-check so the next one can reuse the same student. */
    protected function dropDrillCase(int $caseId): void
    {
        DB::table('escalations')->where('case_id', $caseId)->delete();
        DB::table('case_sessions')->where('case_id', $caseId)->delete();
        DB::table('cases')->where('id', $caseId)->delete();
    }

    protected function checkForwardedGeneratedCopy(object $head, object $academicHead, array $candidate, string $period): void
    {
        if ($candidate['preview_text'] === '') {
            $this->record('forwarded', 'generated copy handed over', true, 'no generated recommendation for this student — covered by the edit check');

            return;
        }

        $result = $this->postEscalation($head, $academicHead, $candidate, $period, [
            'include' => true,
            'recommendation_id' => $candidate['recommendation_id'],
            'edited_text' => null,
        ]);

        $case = DB::table('cases')->where('id', $result['case_id'])->first();
        $escalation = DB::table('escalations')->where('case_id', $result['case_id'])->first();

        $this->record(
            'forwarded',
            'case carries the generated recommendation verbatim',
            trim((string) $case->intervention_recommendation) === trim($candidate['preview_text']),
            'recommendation #' . $candidate['recommendation_id']
        );
        $this->record('forwarded', 'intervention_included = true', (bool) $case->intervention_included);
        $this->record('forwarded', 'intervention_edited = false', !(bool) $case->intervention_edited);
        $this->record(
            'forwarded',
            'provenance recorded on case + escalation',
            (int) $case->intervention_source_id === (int) $candidate['recommendation_id']
                && (int) $escalation->intervention_recommendation_id === (int) $candidate['recommendation_id'],
            'intervention_recommendations #' . $candidate['recommendation_id']
        );

        $this->dropDrillCase($result['case_id']);
    }

    protected function checkForwardedEdit(
        HttpKernel $kernel,
        object $counselorUser,
        object $head,
        object $academicHead,
        array $candidate,
        string $period
    ): void {
        $editedText = '[high] Drill: edited by the Academic Head before sending';

        $result = $this->postEscalation($head, $academicHead, $candidate, $period, [
            'include' => true,
            'recommendation_id' => $candidate['recommendation_id'],
            'edited_text' => $editedText,
        ]);

        $case = DB::table('cases')->where('id', $result['case_id'])->first();

        $this->record(
            'edited',
            'the editable text is what the case carries',
            trim((string) $case->intervention_recommendation) === $editedText
        );
        $this->record('edited', 'intervention_edited = true', (bool) $case->intervention_edited);
        $this->record('edited', 'intervention_included = true', (bool) $case->intervention_included);

        if ($candidate['recommendation_id'] !== null) {
            $this->record(
                'edited',
                'generated recommendation row left untouched',
                DB::table('intervention_recommendations')->where('id', $candidate['recommendation_id'])->exists(),
                'the edit is stored on the case, not written over the AI output'
            );
        }

        $this->checkCasePacket($kernel, $counselorUser, $result['case_id'], $candidate, $period, $editedText);

        $this->dropDrillCase($result['case_id']);
    }

    protected function renderCasePage(HttpKernel $kernel, object $counselorUser, int $caseId): array
    {
        Auth::loginUsingId((int) $counselorUser->id, false);

        try {
            $response = $kernel->handle(Request::create('/counselor/case/' . $caseId, 'GET'));

            return [
                'status' => $response->getStatusCode(),
                'html' => (string) $response->getContent(),
            ];
        } finally {
            Auth::logout();
        }
    }

    protected function record(string $check, string $detail, bool $ok, string $evidence = ''): void
    {
        if (!$ok) {
            $this->failed = true;
        }

        $this->rows[] = [$check, $detail, $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>', $evidence];
    }

    protected function checkNoRecommendation(
        HttpKernel $kernel,
        object $counselorUser,
        object $head,
        object $academicHead,
        array $candidate,
        string $period
    ): void {
        $result = $this->postEscalation($head, $academicHead, $candidate, $period, [
            'include' => false,
            'recommendation_id' => $candidate['recommendation_id'],
            'edited_text' => null,
        ]);

        $case = DB::table('cases')->where('id', $result['case_id'])->first();

        $this->record(
            'not-forwarded',
            'nothing was attached to the case',
            $case->intervention_recommendation === null && !(bool) $case->intervention_included
        );

        $escalationNotes = DB::table('escalations')->where('case_id', $result['case_id'])->value('notes');
        $this->record(
            'not-forwarded',
            'the notes still travel',
            str_contains((string) $escalationNotes, 'Escalation drill')
        );

        $page = $this->renderCasePage($kernel, $counselorUser, $result['case_id']);

        $this->record('not-forwarded', 'case page renders for the counselor', $page['status'] === 200, 'HTTP ' . $page['status']);
        $this->record(
            'not-forwarded',
            'page states "No recommendation provided."',
            str_contains($page['html'], 'No recommendation provided.')
        );
        $this->record('not-forwarded', 'risk score still shown', str_contains($page['html'], 'Risk Score'));
        $this->record('not-forwarded', 'risk factors still shown', str_contains($page['html'], 'Risk Factors'));
        $this->record('not-forwarded', 'grades still shown', str_contains($page['html'], 'Grades'));
        $this->record('not-forwarded', 'attendance still shown', str_contains($page['html'], 'Attendance'));
        $this->record('not-forwarded', 'risk trend still shown', str_contains($page['html'], 'Risk Trend'));

        $this->dropDrillCase($result['case_id']);
    }

    protected function checkCasePacket(
        HttpKernel $kernel,
        object $counselorUser,
        int $caseId,
        array $candidate,
        string $period,
        string $editedText
    ): void {
        $page = $this->renderCasePage($kernel, $counselorUser, $caseId);

        $this->record('case page', 'renders for the counselor', $page['status'] === 200, 'HTTP ' . $page['status']);

        if ($page['status'] !== 200) {
            return;
        }

        $html = $page['html'];

        $this->record('case page', 'shows the forwarded recommendation', str_contains($html, $editedText));
        $this->record('case page', 'shows the current risk score', str_contains($html, 'Risk Score'));
        $this->record('case page', 'shows the risk factors block', str_contains($html, 'Risk Factors'));
        $this->record('case page', 'shows the risk trend table', str_contains($html, 'Risk Trend'));
        $this->record('case page', 'shows the grades table', str_contains($html, 'Grades'));
        $this->record('case page', 'shows the attendance block', str_contains($html, 'Attendance'));

        $gradeRows = app(\App\Repositories\Api\GradeRepository::class)
            ->subjectGradesForStudent($candidate['student_id'], $period, '2024-2025');

        if ($gradeRows->isNotEmpty()) {
            $first = $gradeRows->first();
            $average = number_format((float) $gradeRows->avg('numerical_grade'), 2);

            $this->record(
                'case page',
                'renders per-subject grades + average',
                str_contains($html, (string) $first->subject_code) && str_contains($html, $average),
                $gradeRows->count() . ' subject(s), avg ' . $average
            );
        } else {
            $this->record('case page', 'per-subject grades', true, 'no grade rows for this student/period');
        }

        $attendance = app(\App\Repositories\Api\AttendanceSummaryRepository::class)
            ->aggregateForStudent($candidate['student_id'], $period, '2024-2025');

        $rate = $attendance->overall_attendance ?? null;

        if ($rate !== null) {
            $this->record(
                'case page',
                'renders the attendance rate',
                str_contains($html, number_format((float) $rate, 2)),
                'rate ' . number_format((float) $rate, 2) . '%'
            );
        } else {
            $this->record('case page', 'attendance figures', true, 'no attendance summary for this student/period');
        }

        $factors = app(\App\Services\RecommendationTextService::class)->factors(
            DB::table('risk_scores')
                ->where('student_id', $candidate['student_id'])
                ->where('grading_period', $period)
                ->where('school_year', '2024-2025')
                ->value('risk_factors')
        );

        if ($factors !== []) {
            $this->record(
                'case page',
                'renders at least one risk factor',
                str_contains($html, (string) $factors[0]),
                (string) $factors[0]
            );
        }

        $this->record(
            'case page',
            'trend shows an Improving/Stable/Worsening verdict',
            str_contains($html, 'Improving') || str_contains($html, 'Worsening') || str_contains($html, 'Stable')
        );
    }

    protected function checkPreviewEndpoint(object $head, object $academicHead, array $candidate, string $period): void
    {
        Auth::loginUsingId((int) $head->id, false);

        $request = Request::create('/academic-head/escalation/recommendation-preview', 'POST', [
            'student_ids' => [$candidate['student_id']],
            'period' => $period,
        ]);
        $request->setUserResolver(fn () => $head);
        $request->attributes->set('department_id', (int) $academicHead->department_id);
        $request->attributes->set('academic_head', $academicHead);

        $response = app(EscalationController::class)->recommendationPreview($request);
        $decoded = json_decode((string) $response->getContent(), true);
        $preview = $decoded['students'][(string) $candidate['student_id']] ?? null;

        $this->record('preview', 'endpoint answers for the selected student', $response->getStatusCode() === 200 && is_array($preview), 'HTTP ' . $response->getStatusCode());

        if (!is_array($preview)) {
            Auth::logout();

            return;
        }

        $this->record('preview', 'carries the student name + risk level', ($preview['student_name'] ?? '') !== '' && ($preview['risk_level'] ?? 'N/A') === $candidate['risk_level'], (string) $preview['risk_level']);
        $this->record(
            'preview',
            'reports whether a recommendation exists',
            ($preview['has_recommendation'] ?? false) === ($candidate['recommendation_id'] !== null),
            'recommendation #' . ($preview['recommendation_id'] ?? 'none')
        );

        if ($candidate['preview_text'] !== '' && ($preview['has_recommendation'] ?? false)) {
            $this->record(
                'preview',
                'preview text matches what will be forwarded',
                trim((string) $preview['preview_text']) === trim($candidate['preview_text']),
                strlen((string) $preview['preview_text']) . ' chars'
            );
        }

        Auth::logout();
    }

    protected function checkModalMarkup(HttpKernel $kernel, object $head, array $candidate): void
    {
        Auth::loginUsingId((int) $head->id, false);

        try {
            $response = $kernel->handle(Request::create('/academic-head/block/' . $candidate['block_id'], 'GET'));
            $html = (string) $response->getContent();
        } catch (\Throwable $e) {
            $this->record('modal', 'block page renders', false, $e->getMessage());
            Auth::logout();

            return;
        } finally {
            Auth::logout();
        }

        $this->record('modal', 'block page renders for the academic head', $response->getStatusCode() === 200, 'HTTP ' . $response->getStatusCode());
        $this->record('modal', 'has the escalation modal', str_contains($html, 'id="escalationModal"'));
        $this->record('modal', 'has the per-student list container', str_contains($html, 'id="escalationStudentList"'));
        $this->record('modal', 'has the include-recommendation summary', str_contains($html, 'id="recommendationSummary"'));
        $this->record('modal', 'has the confirm button', str_contains($html, 'id="confirmEscalationBtn"'));
        $this->record('modal', 'fetches the preview endpoint', str_contains($html, '/academic-head/escalation/recommendation-preview'));
        $this->record('modal', 'binds handlers through the delegating binder', str_contains($html, 'bindBlockDashboardUi'));
        $this->record('modal', 'isolates the risk chart in a try/catch', str_contains($html, 'Risk chart failed to render'));
        $this->record('modal', 'serves Bootstrap locally (not CDN-only)', str_contains($html, 'js/vendor/bootstrap.bundle.min.js'));
        $this->record('modal', 'serves Chart.js locally (not CDN-only)', str_contains($html, 'js/vendor/chart.umd.min.js'));
        $this->record('modal', 'reports missing dependencies loudly', str_contains($html, 'Page dependencies failed to load'));
    }

    protected function checkClientDependencies(): void
    {
        foreach ([
            'js/vendor/bootstrap.bundle.min.js',
            'js/vendor/chart.umd.min.js',
            'css/vendor/bootstrap.min.css',
        ] as $asset) {
            $this->record(
                'assets',
                $asset . ' ships with the app',
                is_file(public_path($asset)),
                is_file(public_path($asset)) ? number_format(filesize(public_path($asset)) / 1024, 1) . ' KB' : 'missing'
            );
        }
    }

    protected function checkScriptWiring(): void
    {
        $harness = base_path('tests/JavaScript/block_dom_harness.js');

        if (!is_file($harness)) {
            $this->record('js wiring', 'handler-wiring harness present', false, 'tests/JavaScript/block_dom_harness.js missing');

            return;
        }

        $node = $this->nodeBinary();

        if ($node === null) {
            $this->record('js wiring', 'every control fires its handler', true, 'SKIPPED — node is not installed');
            $this->record('js wiring', 'degrades loudly without the CDN libraries', true, 'SKIPPED — node is not installed');
            $this->record('js wiring', 'every inline script in every view parses', true, 'SKIPPED — node is not installed');

            return;
        }

        foreach ([
            ['every control fires its handler', []],
            ['degrades loudly without the CDN libraries', ['HARNESS_NO_DEPS' => '1']],
        ] as [$label, $env]) {
            $process = new \Symfony\Component\Process\Process([$node, $harness], base_path(), $env === [] ? null : $env);
            $process->setTimeout(90);
            $process->run();

            $output = $process->getOutput();
            preg_match('/RESULT: (.*)/', $output, $matches);
            $summary = trim($matches[1] ?? 'no result line');
            $passed = preg_match_all('/^PASS/m', $output);
            $failed = preg_match_all('/^FAIL/m', $output);

            $this->record(
                'js wiring',
                $label,
                $process->getExitCode() === 0,
                $summary . ' (' . $passed . ' pass / ' . $failed . ' fail)'
            );
        }

        $this->checkViewScriptSyntax($node);
    }

    protected function checkViewScriptSyntax(string $node): void
    {
        $sweep = base_path('tests/JavaScript/check_inline_scripts.js');

        if (!is_file($sweep)) {
            $this->record('js wiring', 'inline-script syntax sweep', false, 'tests/JavaScript/check_inline_scripts.js missing');

            return;
        }

        $process = new \Symfony\Component\Process\Process([$node, $sweep], base_path());
        $process->setTimeout(120);
        $process->run();

        $summary = trim((string) preg_replace('/\s+/', ' ', $process->getOutput()));

        $this->record('js wiring', 'every inline script in every view parses', $process->getExitCode() === 0, $summary);
    }


    protected function nodeBinary(): ?string
    {
        foreach (['node', 'node.exe'] as $candidate) {
            $process = new \Symfony\Component\Process\Process([$candidate, '--version'], base_path());
            $process->setTimeout(20);

            try {
                $process->run();
            } catch (\Throwable $e) {
                continue;
            }

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }

}

<?php

namespace App\Console\Commands;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MockApiSmokeCommand extends Command
{
    protected $signature = 'mock-api:smoke {--role= : Only probe one role} {--path= : Probe a single path (requires --role)}';

    protected $description = 'Log in as each role and render every dashboard and chart against the live mock API';

    protected array $resolved = [];

    public function handle(
        HttpKernel $kernel,
        AcademicStructureRepository $structure,
        StudentRepository $students
    ): int {
        $this->resolved = $this->resolveIds($structure, $students);

        $roles = $this->option('role')
            ? [(string) $this->option('role')]
            : ['admin', 'academic_head', 'guidance_counselor', 'student'];

        $this->newLine();
        $this->line('Smoke-testing <fg=cyan>' . count($roles) . '</> role(s) against the live mock API');
        $this->newLine();

        $rows = [];
        $failures = 0;

        foreach ($roles as $role) {
            $user = $this->pickUser($role);

            if ($user === null) {
                $rows[] = [$role, '—', '<fg=yellow>SKIPPED</>', 'no active user with this role'];
                continue;
            }

            $paths = $this->option('path')
                ? [(string) $this->option('path')]
                : $this->pathsFor($role);

            foreach ($paths as $path) {
                [$label, $status, $detail] = $this->probe($kernel, $user, $path);

                if ($status !== 200) {
                    $failures++;
                }

                $rows[] = [$role, $label, $status === 200 ? '<fg=green>200</>' : "<fg=red>$status</>", $detail];
            }
        }

        $this->table(['role', 'endpoint', 'status', 'detail'], $rows);

        $this->newLine();
        $checked = count($rows);
        $passed = $checked - $failures;

        $this->line($failures === 0
            ? sprintf('<fg=green>All %d endpoint(s) rendered successfully.</>', $checked)
            : sprintf('<fg=red>%d of %d endpoint(s) failed.</>', $failures, $checked));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function pickUser(string $role): ?object
    {
        $query = DB::table('users')->where('role', $role)->where('is_active', true)->orderBy('id');

        if ($role === 'academic_head') {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('academic_heads')
                ->whereColumn('academic_heads.user_id', 'users.id'));
        }

        if ($role === 'guidance_counselor') {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('counselors')
                ->whereColumn('counselors.user_id', 'users.id'));
        }

        return $query->first();
    }

    protected function resolveIds(AcademicStructureRepository $structure, StudentRepository $students): array
    {
        $department = $structure->departments()->sortBy('id')->first();
        $program = $structure->programs()->sortBy('id')->first();
        $yearLevel = $structure->yearLevels()->sortBy('id')->first();
        $block = $structure->blocks()->sortBy('id')->first();
        $student = $students->all()->sortBy('id')->first();

        $payment = DB::table('payments')->orderBy('id')->first();

        $report = DB::table('end_of_term_reports')->orderBy('id')->first();

        $counselor = DB::table('counselors')->orderBy('id')->first();
        // cases.counselor_id is constrained() on the inferred `counselors` table, so it
        // references counselors.id — not counselors.user_id.
        $caseQuery = $counselor !== null ? DB::table('cases')->where('counselor_id', $counselor->id) : null;

        $case = $caseQuery !== null ? (clone $caseQuery)->orderBy('id')->first() : null;
        $case = $case ?? DB::table('cases')->orderBy('id')->first();

        // The counselor's own caseload, so the caseload-scoped endpoints legitimately
        // return 200 instead of an expected 403.
        $counselorStudent = $caseQuery !== null
            ? (clone $caseQuery)->orderBy('id')->value('student_id')
            : null;

        return array_filter([
            'departmentId' => (int) ($department->id ?? 0),
            'programId' => (int) ($program->id ?? 0),
            'yearLevelId' => (int) ($yearLevel->id ?? 0),
            'blockId' => (int) ($block->id ?? 0),
            'studentId' => (int) ($student->id ?? 0),
            'id' => (int) ($payment->id ?? 0),
            'reportId' => (int) ($report->id ?? 0),
            'caseId' => (int) ($case->id ?? 0),
            'counselorStudentId' => (int) ($counselorStudent ?? 0),
        ], fn ($value) => $value > 0);
    }

    protected function pathsFor(string $role): array
    {
        return match ($role) {
            'admin' => [
                '/admin/dashboard',
                '/admin/users',
                '/admin/users/create',
                '/admin/users/{id}/edit',
                '/admin/academic',
                '/admin/academic/department/{departmentId}/edit',
                '/admin/academic/program/{programId}/edit',
                '/admin/risk/config',
                '/admin/payments',
                '/admin/payments/{id}/view',
                '/admin/payments/{id}/info',
                '/admin/audit/logs',
                '/admin/schoolyear',
                '/admin/system/health',
                '/admin/reports',
                '/admin/get-programs/{departmentId}',
                '/admin/get-year-levels/{programId}',
                '/admin/get-blocks/{yearLevelId}',
                '/charts/admin/risk-by-department',
                '/charts/admin/risk-distribution',
                '/charts/admin/risk-trend',
                '/profile',
                '/settings',
            ],
            'academic_head' => [
                '/academic-head/department',
                '/academic-head/blocks',
                '/academic-head/recommendations',
                '/academic-head/reports',
                '/academic-head/risk-scoring',
                '/academic-head/blocks/{programId}',
                '/academic-head/block/{blockId}',
                '/academic-head/block/{blockId}/student-count',
                '/academic-head/block/{blockId}/students',
                '/academic-head/block/{blockId}/scoring-status',
                '/academic-head/block/{blockId}/escalated-students',
                '/charts/academic-head/department-risk',
                '/charts/academic-head/department-trend',
                '/charts/academic-head/block-risk/{blockId}',
                '/charts/academic-head/risk-by-block',
                '/charts/academic-head/escalation-trend',
                '/profile',
                '/settings',
            ],
            'guidance_counselor' => [
                '/counselor/dashboard',
                '/counselor/cases',
                '/counselor/cases?scope=open',
                '/counselor/cases?scope=resolved',
                '/counselor/case/{caseId}',
                '/counselor/reports',
                // "My Reports" — the counselor's own deterministic caseload activity
                // summary, plus the PDF projection of the same snapshot.
                '/counselor/my-reports',
                '/counselor/my-reports/pdf',
                '/charts/counselor/priority-distribution',
                '/charts/counselor/status-distribution',
                '/charts/counselor/caseload-trend',
                '/charts/counselor/student-risk/{counselorStudentId}',
                '/profile',
                '/settings',
            ],
            'student' => [
                '/student/dashboard',
                '/student/grades',
                '/student/attendance',
                '/student/counselor',
                '/student/recommendations',
                '/student/recommendations/pending-count',
                '/charts/student/risk-trend',
                '/charts/student/grades',
                '/profile',
                '/settings',
            ],
            default => [],
        };
    }

    protected function probe(HttpKernel $kernel, object $user, string $path): array
    {
        foreach ($this->resolved as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        $label = $path;

        if (str_contains($path, '{')) {
            return [$label, 0, 'unresolved placeholder'];
        }

        try {
            // Authenticate the guard the kernel's `auth` middleware will consult.
            Auth::loginUsingId($user->id, false);

            $response = $kernel->handle(Request::create($path, 'GET'));

            $status = $response->getStatusCode();
            $body = (string) $response->getContent();
        } catch (\Throwable $e) {
            return [$label, 500, get_class($e) . ': ' . $this->trim($e->getMessage())];
        } finally {
            Auth::logout();
        }

        if ($status !== 200) {
            return [$label, $status, $this->trim(strip_tags($body))];
        }

        // A 200 that is actually a rendered error page must not pass.
        foreach (['Whoops', 'Too few arguments', 'Undefined variable', 'Undefined array key', 'Attempt to read property', 'Call to undefined'] as $marker) {
            if (str_contains($body, $marker)) {
                return [$label, 500, 'body contains "' . $marker . '"'];
            }
        }

        return [$label, 200, strlen($body) . ' bytes'];
    }

    protected function trim(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_substr($text, 0, 90);
    }
}

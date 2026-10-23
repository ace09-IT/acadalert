<?php

namespace App\Console\Commands;

use App\Helpers\DepartmentHelper;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MockApiTenancyCommand extends Command
{
    protected $signature = 'mock-api:tenancy';

    protected $description = 'Verify the multi-tenant department boundary still holds after the mock API migration';

    public function handle(AcademicStructureRepository $structure, StudentRepository $students): int
    {
        $departments = $structure->departments()->sortBy('code')->values();

        if ($departments->isEmpty()) {
            $this->error('No departments found.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf('Probing <fg=cyan>%d</> department boundary(ies)', $departments->count()));
        $this->newLine();

        $rows = [];
        $failed = false;

        $reference = [];
        $viaApi = [];

        foreach ($departments as $department) {
            $reference[$department->code] = DB::table('students')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $department->id)
                ->where('students.status', 'Active')
                ->distinct()
                ->pluck('students.id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $ids = $structure->studentIdsInDepartment($department->id, true);
            sort($ids);
            $viaApi[$department->code] = array_map('intval', $ids);
        }

        $ok = $this->digest($reference) === $this->digest($viaApi);
        $failed = $failed || !$ok;

        $rows[] = [
            'scope matches the original 4-table JOIN',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $ok
                ? array_sum(array_map('count', $reference)) . ' scoped student(s), ' . count($reference) . ' department(s)'
                : $this->firstDifference($reference, $viaApi),
        ];

        $this->report($rows, $failed, $reference, $viaApi, $departments, $structure, $students);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function report(
        array $rows,
        bool $failed,
        array $reference,
        array $viaApi,
        $departments,
        AcademicStructureRepository $structure,
        StudentRepository $students
    ): void {
        $seen = [];
        $overlaps = [];

        foreach ($viaApi as $code => $ids) {
            foreach ($ids as $id) {
                if (isset($seen[$id]) && $seen[$id] !== $code) {
                    $overlaps[] = sprintf('student %d in %s and %s', $id, $seen[$id], $code);
                }
                $seen[$id] = $code;
            }
        }

        $ok = $overlaps === [];
        $failed = $failed || !$ok;

        $rows[] = [
            'no student appears in two departments',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $ok
                ? count($seen) . ' student(s) mapped to exactly one department'
                : implode('; ', array_slice($overlaps, 0, 3)),
        ];

        $crossChecks = 0;
        $leaks = [];

        foreach ($departments as $owner) {
            $ids = $viaApi[$owner->code];

            if ($ids === []) {
                continue;
            }

            $sample = (int) $ids[0];

            foreach ($departments as $other) {
                if ((int) $other->id === (int) $owner->id) {
                    continue;
                }

                $crossChecks++;

                if ($structure->isStudentInDepartment($sample, $other->id)) {
                    $leaks[] = sprintf('student %d (%s) also resolved inside %s', $sample, $owner->code, $other->code);
                }
            }
        }

        $ok = $leaks === [];
        $failed = $failed || !$ok;

        $rows[] = [
            'cross-department lookups are denied',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $ok ? $crossChecks . ' cross-department check(s) all denied' : implode('; ', array_slice($leaks, 0, 3)),
        ];

        $this->reportNegatives($rows, $failed, $viaApi, $departments, $structure, $students);
    }

    protected function reportNegatives(
        array $rows,
        bool $failed,
        array $viaApi,
        $departments,
        AcademicStructureRepository $structure,
        StudentRepository $students
    ): void {
        $allStudents = $students->all();
        $unknownId = (int) $allStudents->max(fn ($row) => (int) $row->id) + 100000;
        $falsePositives = [];
        $unplacedChecked = false;

        foreach ($departments as $department) {
            if ($structure->isStudentInDepartment($unknownId, $department->id)) {
                $falsePositives[] = sprintf('unknown student %d accepted for %s', $unknownId, $department->code);
            }
        }

        foreach ($allStudents as $student) {
            if ($structure->isStudentPlaced($student->id)) {
                continue;
            }

            $unplacedChecked = true;

            foreach ($departments as $department) {
                if ($structure->isStudentInDepartment($student->id, $department->id)) {
                    $falsePositives[] = sprintf('unplaced student %d accepted for %s', $student->id, $department->code);
                }
            }

            break;
        }

        $ok = $falsePositives === [];
        $failed = $failed || !$ok;

        $rows[] = [
            'unknown / unplaced students never in scope',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $ok
                ? sprintf('fabricated id %d rejected; unplaced student present: %s', $unknownId, $unplacedChecked ? 'yes' : 'no')
                : implode('; ', array_slice($falsePositives, 0, 3)),
        ];

        $helperMismatch = [];

        foreach ($departments as $department) {
            $request = Request::create('/probe', 'GET');
            $request->attributes->set('department_id', (int) $department->id);

            $helperIds = DepartmentHelper::getDepartmentStudents($request);
            $helperIds = array_map(fn ($row) => (int) $row->id, $helperIds);
            sort($helperIds);

            if ($helperIds !== $viaApi[$department->code]) {
                $helperMismatch[] = $department->code;
            }
        }

        $ok = $helperMismatch === [];
        $failed = $failed || !$ok;

        $rows[] = [
            'DepartmentHelper matches the placement index',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $ok ? count($departments) . ' department(s) agree' : 'differs for: ' . implode(', ', $helperMismatch),
        ];

        $this->table(['check', 'result', 'detail'], $rows);

        $this->newLine();
        $this->line($failed
            ? '<fg=red>Multi-tenant boundary is NOT intact.</>'
            : '<fg=green>Department isolation holds — no cross-tenant leakage.</>');
    }

    protected function digest(array $data): string
    {
        $this->canonicalise($data);

        return hash('sha256', json_encode($data));
    }

    protected function canonicalise(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->canonicalise($value);
            }
        }

        unset($value);

        if (!array_is_list($data)) {
            ksort($data);
        }
    }

    protected function firstDifference(array $expected, array $actual): string
    {
        foreach ($expected as $key => $value) {
            $other = $actual[$key] ?? null;

            if ($value !== $other) {
                return sprintf(
                    'first difference at [%s]: SQL=%s API=%s',
                    $key,
                    json_encode($value),
                    json_encode($other)
                );
            }
        }

        return 'structures differ';
    }
}

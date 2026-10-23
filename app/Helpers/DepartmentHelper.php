<?php

namespace App\Helpers;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DepartmentHelper
{
    public static function getDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    public static function getAcademicHead(Request $request): ?object
    {
        return $request->attributes->get('academic_head');
    }

    public static function getCounselor(Request $request): ?object
    {
        return $request->attributes->get('counselor');
    }

    public static function isAcademicHeadWithDepartment(Request $request): bool
    {
        return self::getDepartmentId($request) !== null && self::getAcademicHead($request) !== null;
    }

    public static function isCounselorWithDepartment(Request $request): bool
    {
        return self::getDepartmentId($request) !== null && self::getCounselor($request) !== null;
    }

    public static function getDepartmentPrograms(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        // `programs` is served by the mock API.
        return app(AcademicStructureRepository::class)
            ->programsForDepartment($departmentId)
            ->all();
    }

    public static function getDepartmentBlocks(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        // blocks -> year_levels -> programs now resolves through the API
        // placement index instead of a three-table join.
        return app(AcademicStructureRepository::class)
            ->blocksWithProgramForDepartment($departmentId)
            ->all();
    }

    public static function getBlockStudents(Request $request, int $blockId): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        // Students and the placement chain now come from the mock API.
        $structure = app(AcademicStructureRepository::class);

        if (!$structure->isBlockInDepartment($blockId, $departmentId)) {
            return [];
        }

        $placement = $structure->blockPlacement($blockId);

        return app(StudentRepository::class)
            ->forBlock($blockId, false)
            ->map(function ($student) use ($placement) {
                $decorated = clone $student;
                $decorated->block_name = $placement['block_name'] ?? null;
                $decorated->year_number = $placement['year_number'] ?? null;
                $decorated->program_code = $placement['program_code'] ?? null;

                return $decorated;
            })
            ->all();
    }

    public static function isBlockInDepartment(Request $request, int $blockId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        return app(AcademicStructureRepository::class)
            ->isBlockInDepartment($blockId, $departmentId);
    }

    public static function isProgramInDepartment(Request $request, int $programId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $program = app(AcademicStructureRepository::class)->program($programId);

        return $program !== null && (int) $program->department_id === (int) $departmentId;
    }

    public static function isStudentInDepartment(Request $request, int $studentId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        return app(AcademicStructureRepository::class)
            ->isStudentInDepartment($studentId, $departmentId);
    }

    public static function isCaseInCounselorDepartment(Request $request, int $caseId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $case = DB::table('cases')->where('id', $caseId)->first(['student_id']);

        if ($case === null) {
            return false;
        }

        return app(AcademicStructureRepository::class)
            ->isStudentInDepartment($case->student_id, $departmentId);
    }

    public static function getCounselorDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    public static function getDepartmentStudents(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        $structure = app(AcademicStructureRepository::class);
        $placements = $structure->studentPlacements();
        $studentIds = $structure->studentIdsInDepartment($departmentId, true);

        return app(StudentRepository::class)
            ->activeForIds($studentIds)
            ->map(function ($student) use ($placements) {
                $placement = $placements[(int) $student->id] ?? [];

                $decorated = clone $student;
                $decorated->block_name = $placement['block_name'] ?? null;
                $decorated->year_number = $placement['year_number'] ?? null;
                $decorated->program_code = $placement['program_code'] ?? null;

                return $decorated;
            })
            ->sortBy([
                ['last_name', 'asc'],
                ['first_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function getDepartmentCases(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        $counselor = self::getCounselor($request);
        if (!$counselor) {
            return [];
        }

        $structure = app(AcademicStructureRepository::class);
        $studentIds = $structure->studentIdsInDepartment($departmentId, false);
        $names = app(StudentRepository::class)->nameIndex();

        $priorityRank = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];

        return DB::table('cases')
            ->where('counselor_id', $counselor->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->map(function ($case) use ($names) {
                $student = $names[(int) $case->student_id] ?? null;

                $decorated = clone $case;
                $decorated->first_name = $student->first_name ?? null;
                $decorated->last_name = $student->last_name ?? null;
                $decorated->student_number = $student->student_number ?? null;

                return $decorated;
            })
            ->sort(function ($a, $b) use ($priorityRank) {
                $cmp = ($priorityRank[(string) $a->priority] ?? 9)
                    <=> ($priorityRank[(string) $b->priority] ?? 9);

                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $b->updated_at, (string) $a->updated_at);
            })
            ->values()
            ->all();
    }
}
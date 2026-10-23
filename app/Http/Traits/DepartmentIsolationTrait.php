<?php

namespace App\Http\Traits;

use App\Repositories\Api\AcademicStructureRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait DepartmentIsolationTrait
{
    protected function getDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    protected function getAcademicHead(Request $request): ?object
    {
        return $request->attributes->get('academic_head');
    }

    protected function isStudentInDepartment(Request $request, $studentId): bool
    {
        $departmentId = $this->getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        return app(AcademicStructureRepository::class)
            ->isStudentInDepartment($studentId, $departmentId);
    }

    protected function applyDepartmentFilter(Request $request, $query)
    {
        throw new \RuntimeException(
            'applyDepartmentFilter() is no longer supported: the academic placement chain ' .
            'is served by the mock API. Use ' .
            'AcademicStructureRepository::studentIdsInDepartment() and scope the query ' .
            'with whereIn() instead.'
        );
    }

    protected function getDepartmentIds(Request $request): array
    {
        $departmentId = $this->getDepartmentId($request);
        return $departmentId ? [$departmentId] : [];
    }
}
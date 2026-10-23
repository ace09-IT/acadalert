<?php

namespace App\Http\Controllers;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\ParentRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request, StudentRepository $students)
    {
        $user = Auth::user();
        $structure = app(AcademicStructureRepository::class);
        $staff = app(StaffRepository::class);

        $student = null;
        $placement = null;
        $parents = collect();
        $staffProfile = null;
        $department = null;

        if ($user->isStudent()) {
            // `students` is served by the mock API; the placement chain
            // (block -> year level -> program -> department) is resolved in PHP.
            $student = $students->findByEmail($user->email);
            $placement = $student !== null
                ? $structure->blockPlacement($student->block_id)
                : null;

            if ($student !== null) {
                $parents = app(ParentRepository::class)
                    ->forStudent($student->id)
                    ->sortByDesc(fn ($row) => (int) $row->is_primary_contact)
                    ->values();
            }
        } else {
            $staffProfile = $user->isAcademicHead()
                ? $staff->academicHeadForUser($user->id)
                : ($user->isCounselor() ? $staff->counselorForUser($user->id) : null);

            if ($staffProfile !== null && ($staffProfile->department_id ?? null) !== null) {
                $department = $structure->department($staffProfile->department_id);
            }
        }

        return view('profile', [
            'user' => $user,
            'isStudent' => $user->isStudent(),
            'student' => $student,
            'placement' => $placement,
            'parents' => $parents,
            'staffProfile' => $staffProfile,
            'department' => $department,
        ]);
    }
}

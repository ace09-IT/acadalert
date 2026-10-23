<?php

namespace App\Http\Middleware;

use App\Repositories\Api\StaffRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DepartmentIsolationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // For Academic Heads — the academic_heads collection is served by the mock API.
        if ($user->role === 'academic_head') {
            $academicHead = app(StaffRepository::class)->academicHeadForUser($user->id);

            if (!$academicHead) {
                return redirect()->route('dashboard')->with('error', 'Academic Head record not found.');
            }

            $request->attributes->set('department_id', $academicHead->department_id);
            $request->attributes->set('academic_head', $academicHead);
        }

        // FOR COUNSELORS — the counselors collection is served by the mock API.
        if ($user->role === 'guidance_counselor') {
            $counselor = app(StaffRepository::class)->counselorForUser($user->id);

            if (!$counselor) {
                return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
            }

            $request->attributes->set('department_id', $counselor->department_id);
            $request->attributes->set('counselor', $counselor);
        }

        return $next($request);
    }

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
}
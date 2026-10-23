<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Repositories\Api\StudentRepository;
use App\Services\InterventionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    protected $interventionService;

    public function __construct(InterventionService $interventionService)
    {
        $this->interventionService = $interventionService;
    }

    public function index(StudentRepository $students)
    {
        $user = Auth::user();

        // Student records come from the mock API.
        $student = $students->findByEmail($user->email);

        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }

        $recommendations = $this->interventionService->getStudentRecommendations($student->id);
        $tracking = $this->interventionService->getTrackingStatus($student->id);

        $trackedIds = collect($tracking)->pluck('recommendation_id')->toArray();
        $completedIds = collect($tracking)->where('is_completed', true)->pluck('recommendation_id')->toArray();

        foreach ($recommendations as $rec) {
            $rec->is_completed = in_array($rec->id, $completedIds);
            $rec->is_tracked = in_array($rec->id, $trackedIds);
        }

        return view('student.recommendations', [
            'student' => $student,
            'recommendations' => $recommendations,
            'tracking' => $tracking,
        ]);
    }

    public function markCompleted(Request $request, StudentRepository $students)
    {
        $request->validate([
            'recommendation_id' => 'required|integer|exists:intervention_recommendations,id',
        ]);

        $user = Auth::user();

        // Student records come from the mock API; recommendations stay local.
        $student = $students->findByEmail($user->email);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
            ], 404);
        }

        $rec = DB::table('intervention_recommendations')
            ->where('id', $request->recommendation_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$rec) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation not found.',
            ], 404);
        }

        $result = $this->interventionService->markCompleted($student->id, $request->recommendation_id);

        return response()->json($result);
    }

    public function getPendingCount(StudentRepository $students)
    {
        $user = Auth::user();

        // Student records come from the mock API.
        $student = $students->findByEmail($user->email);

        if (!$student) {
            return response()->json(['count' => 0]);
        }

        $recommendations = $this->interventionService->getStudentRecommendations($student->id);
        $tracking = $this->interventionService->getTrackingStatus($student->id);

        $completedIds = collect($tracking)->where('is_completed', true)->pluck('recommendation_id')->toArray();
        $pendingCount = 0;

        foreach ($recommendations as $rec) {
            if (!in_array($rec->id, $completedIds)) {
                $pendingCount++;
            }
        }

        return response()->json(['count' => $pendingCount]);
    }
}
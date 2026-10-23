<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\CalendarRepository;
use App\Repositories\Api\GradeRepository;
use App\Repositories\Api\PaymentRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use App\Repositories\Local\RiskScoreRepository;
use App\Rules\ApiExists;
use App\Rules\ApiUnique;
use App\Services\RiskScoringCacheService;
use App\Services\RiskThresholdService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; 

class DashboardController extends Controller
{
    public function __construct(protected \App\Services\Api\MirrorWriter $mirror)
    {
    }

    public function index(Request $request)
    {
        $stats = $this->getSystemStats();
        $riskByDepartment = $this->getRiskByDepartment();
        $riskDistribution = $this->getRiskDistribution();
        $riskTrend = $this->getRiskTrend();
        $systemHealth = $this->getSystemHealth();
        $recentActivities = $this->getRecentActivities();
        $errors = $this->getRecentErrors();

        return view('admin.dashboard', [
            'stats' => $stats,
            'riskByDepartment' => $riskByDepartment,
            'riskDistribution' => $riskDistribution,
            'riskTrend' => $riskTrend,
            'systemHealth' => $systemHealth,
            'recentActivities' => $recentActivities,
            'errors' => $errors,
        ]);
    }


    public function users(Request $request)
    {
        $roleFilter = $request->input('role', 'all');
        $departmentFilter = $request->input('department', 'all');
        $programFilter = $request->input('program', 'all');
        $yearLevelFilter = $request->input('year_level', 'all');
        $blockFilter = $request->input('block', 'all');
        $searchFilter = $request->input('search', '');
        
        $structure = app(AcademicStructureRepository::class);
        $staff = app(StaffRepository::class);

        $departmentIndex = $structure->departments()->keyBy(fn ($row) => (int) $row->id);
        $programIndex = $structure->programs()->keyBy(fn ($row) => (int) $row->id);
        $yearLevelIndex = $structure->yearLevels()->keyBy(fn ($row) => (int) $row->id);
        $blockIndex = $structure->blocks()->keyBy(fn ($row) => (int) $row->id);

        $headsByUser = $staff->academicHeadsByUserId();
        $counselorsByUser = $staff->counselorsByUserId();
        $studentsByEmail = app(StudentRepository::class)->keyedByEmail();

        $rows = $staff->users()->map(function ($user) use (
            $departmentIndex,
            $programIndex,
            $yearLevelIndex,
            $blockIndex,
            $headsByUser,
            $counselorsByUser,
            $studentsByEmail
        ) {
            $head = $headsByUser[(int) $user->id] ?? null;
            $counselor = $counselorsByUser[(int) $user->id] ?? null;
            $student = $studentsByEmail[(string) $user->email] ?? null;

            $block = ($student !== null && $student->block_id !== null)
                ? ($blockIndex[(int) $student->block_id] ?? null)
                : null;
            $yearLevel = $block !== null ? ($yearLevelIndex[(int) $block->year_level_id] ?? null) : null;
            $program = $yearLevel !== null ? ($programIndex[(int) $yearLevel->program_id] ?? null) : null;

            $departmentId = $program->department_id
                ?? $head->department_id
                ?? $counselor->department_id
                ?? null;
            $department = $departmentId !== null ? ($departmentIndex[(int) $departmentId] ?? null) : null;

            $row = clone $user;
            $row->mt_department_id = $head->department_id ?? null;
            $row->counselor_department_id = $counselor->department_id ?? null;
            $row->student_number = $student->student_number ?? null;
            // `blocks.id as block_id` was selected LAST, so it wins over students.block_id.
            $row->block_id = $block->id ?? null;
            $row->student_department_id = $department->id ?? null;
            $row->student_department_code = $department->code ?? null;
            $row->student_department_name = $department->name ?? null;
            $row->program_id = $program->id ?? null;
            $row->program_code = $program->code ?? null;
            $row->program_name = $program->name ?? null;
            $row->year_level_id = $yearLevel->id ?? null;
            $row->year_level_name = $yearLevel->name ?? null;
            $row->block_name = $block->name ?? null;

            return $row;
        });
        
        if ($roleFilter !== 'all') {
            $rows = $rows->filter(fn ($row) => (string) $row->role === (string) $roleFilter);
        }
        
        if ($departmentFilter !== 'all') {
            $target = (int) $departmentFilter;

            $rows = $rows->filter(function ($row) use ($target, $programIndex) {
                $programDepartment = $row->program_id !== null
                    ? ($programIndex[(int) $row->program_id]->department_id ?? null)
                    : null;

                return (int) $programDepartment === $target
                    || (int) $row->mt_department_id === $target
                    || (int) $row->counselor_department_id === $target;
            });
        }
        
        if ($programFilter !== 'all') {
            $target = (int) $programFilter;
            $rows = $rows->filter(fn ($row) => (int) $row->program_id === $target);
        }
        
        if ($yearLevelFilter !== 'all') {
            $target = (int) $yearLevelFilter;
            $rows = $rows->filter(fn ($row) => (int) $row->year_level_id === $target);
        }
        
        if ($blockFilter !== 'all') {
            $target = (int) $blockFilter;
            $rows = $rows->filter(fn ($row) => (int) $row->block_id === $target);
        }
        
        if (!empty($searchFilter)) {
            // MySQL's default collation is case-insensitive, which stripos reproduces
            // for these ASCII names, emails and student numbers.
            $rows = $rows->filter(fn ($row) => stripos((string) $row->name, $searchFilter) !== false
                || stripos((string) $row->email, $searchFilter) !== false
                || stripos((string) $row->student_number, $searchFilter) !== false);
        }
        
        // ORDER BY users.created_at DESC, with id ASC as the tiebreak InnoDB would
        // naturally produce for equal timestamps.
        $rows = $rows->sort(function ($a, $b) {
            $cmp = strcmp((string) $b->created_at, (string) $a->created_at);

            return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
        })->values();

        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));

        $users = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $departments = $structure->departments();
        $programs = $structure->programs();
        $yearLevels = $structure->yearLevels();
        $blocks = $structure->blocks();

        // The view used to look departments up one row at a time; the map is handed
        // over instead so no DB access happens inside the Blade template.
        $departmentCodes = $departments
            ->mapWithKeys(fn ($row) => [(int) $row->id => $row->code])
            ->all();

        $roles = ['admin', 'academic_head', 'guidance_counselor', 'student'];
        
        return view('admin.users', compact(
            'users', 'departments', 'programs', 'yearLevels', 'blocks', 'roles',
            'departmentCodes',
            'roleFilter', 'departmentFilter', 'programFilter', 'yearLevelFilter',
            'blockFilter', 'searchFilter'
        ));
    }

    public function createUser()
    {
        $departments = app(AcademicStructureRepository::class)->departments();
        return view('admin.users-create', compact('departments'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,academic_head,guidance_counselor,student',
            'password' => 'required|string|min:6|confirmed',
            'department_id' => ['nullable', new ApiExists('departments', 'id')],
        ]);

        try {
            DB::beginTransaction();

            // Dependency data is resolved BEFORE the transaction opens: no network
            // I/O may happen while a database transaction is held open.
            $blocks = app(AcademicStructureRepository::class)->blocks();
            $randomBlock = $blocks->isNotEmpty() ? $blocks->random() : null;

            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('users', $userId);

            if ($request->role === 'academic_head' && $request->department_id) {
                $headId = DB::table('academic_heads')->insertGetId([
                    'user_id' => $userId,
                    'department_id' => $request->department_id,
                    'employee_number' => 'MT-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => 'General',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mirror->createdFromLocal('academic_heads', $headId);
            }

            if ($request->role === 'guidance_counselor' && $request->department_id) {
                $counselorId = DB::table('counselors')->insertGetId([
                    'user_id' => $userId,
                    'department_id' => $request->department_id,
                    'employee_number' => 'GC-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => 'Academic Counseling',
                    'max_caseload' => 30,
                    'office_location' => 'Guidance Office',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mirror->createdFromLocal('counselors', $counselorId);
            }

            if ($request->role === 'student') {
                $studentId = DB::table('students')->insertGetId([
                    'block_id' => $randomBlock->id ?? 1,
                    'student_number' => 'UDD-' . date('Y') . '-STU-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'first_name' => explode(' ', $request->name)[0],
                    'last_name' => explode(' ', $request->name)[1] ?? 'Student',
                    'email' => $request->email,
                    'status' => 'Active',
                    'year_enrolled' => date('Y'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mirror->createdFromLocal('students', $studentId);
            }

            DB::commit();

            // Network I/O happens only after the transaction has committed.
            $this->mirror->flush();

            $this->logActivity('USER_CREATED', 'Created user: ' . $request->email);
            return redirect()->route('admin.users.index')->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            // Nothing was committed, so the queued mirrors must not be sent.
            $this->mirror->discard();
            return back()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    public function getProgramsByDepartment(int $departmentId)
    {
        // programs are served by the mock API. Filtering in PHP preserves the API's
        // (id) order, which is what an unordered MySQL SELECT returned.
        $programs = app(AcademicStructureRepository::class)->programs()
            ->where('department_id', $departmentId)
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
            ])
            ->values();

        return response()->json($programs);
    }

    public function getYearLevelsByProgram(int $programId)
    {
        $yearLevels = app(AcademicStructureRepository::class)->yearLevels()
            ->where('program_id', $programId)
            ->sortBy('year_number')
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'year_number' => (int) $row->year_number,
                'name' => $row->name,
            ])
            ->values();

        return response()->json($yearLevels);
    }

    public function getBlocksByYearLevel(int $yearLevelId)
    {
        $blocks = app(AcademicStructureRepository::class)->blocks()
            ->where('year_level_id', $yearLevelId)
            ->sortBy('block_number')
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'block_number' => (int) $row->block_number,
                'name' => $row->name,
            ])
            ->values();

        return response()->json($blocks);
    }

    public function getDepartments()
    {
        $departments = app(AcademicStructureRepository::class)->departments()
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
            ])
            ->values();

        return response()->json($departments);
    }

    public function editUser(int $id)
    {
        $structure = app(AcademicStructureRepository::class);
        $staff = app(StaffRepository::class);

        // The users directory, staff records and placement chain are all served by
        // the mock API now.
        $user = $staff->user($id);
        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        $departments = $structure->departments();
        $programs = $structure->programs();
        $yearLevels = $structure->yearLevels();
        $blocks = $structure->blocks();
        
        $additional = null;
        $studentDetails = null;
        
        if ($user->role === 'academic_head') {
            $additional = $staff->academicHeadForUser($user->id);
        } elseif ($user->role === 'guidance_counselor') {
            $additional = $staff->counselorForUser($user->id);
        } elseif ($user->role === 'student') {
            $studentDetails = app(StudentRepository::class)->findByEmail($user->email);

            if ($studentDetails !== null && $studentDetails->block_id !== null) {
                $placement = $structure->blockPlacement($studentDetails->block_id);

                // The previous nested lookups set both keys only when the block and
                // year level resolved.
                if ($placement !== null && $placement['year_level_id'] !== null) {
                    $studentDetails = clone $studentDetails;
                    $studentDetails->program_id = $placement['program_id'];
                    $studentDetails->year_level_id = $placement['year_level_id'];
                }
            }
        }

        return view('admin.users-edit', compact(
            'user', 'additional', 'departments', 'programs', 'yearLevels', 'blocks', 'studentDetails'
        ));
    }

    public function updateUser(Request $request, int $id)
    {
        // The users directory is served by the mock API now.
        $user = app(StaffRepository::class)->user($id);
        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User not found.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,academic_head,guidance_counselor,student',
            'password' => 'nullable|string|min:6',
            'department_id' => ['nullable', 'required_if:role,academic_head', new ApiExists('departments', 'id')],
            'program_id' => ['nullable', new ApiExists('programs', 'id')],
            'year_level_id' => ['nullable', new ApiExists('year_levels', 'id')],
            'block_id' => ['nullable', new ApiExists('blocks', 'id')],
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'student_number' => 'nullable|string|max:30',
            'status' => 'nullable|string|in:Active,Inactive',
        ]);

        // Everything the write path needs from the API is read BEFORE the transaction
        // opens, so no HTTP request is ever made while a transaction is held.
        $studentsByEmail = app(StudentRepository::class)->keyedByEmail();
        $existingStudent = $studentsByEmail[(string) $user->email] ?? null;

        $this->mirror->defer();

        try {
            DB::beginTransaction();

            $isActive = $request->has('is_active') ? 1 : 0;
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'is_active' => $isActive,
                'updated_at' => now(),
            ];

            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            DB::table('users')->where('id', $id)->update($updateData);
            $this->mirror->updatedFromLocal('users', $id);

            $this->syncStaffProfile($request, $id);

            if ($request->role === 'student') {
                $oldBlockId = $existingStudent->block_id ?? null;
                $newBlockId = $request->block_id;
                $blockChanged = ($oldBlockId && $newBlockId && $oldBlockId != $newBlockId);
                
                if ($existingStudent) {
                    $studentUpdateData = [
                        'first_name' => $request->first_name ?? explode(' ', $request->name)[0],
                        'last_name' => $request->last_name ?? (explode(' ', $request->name)[1] ?? 'Student'),
                        'email' => $request->email,
                        'status' => $request->status ?? 'Active',
                        'updated_at' => now(),
                    ];
                    
                    if ($newBlockId) {
                        $studentUpdateData['block_id'] = $newBlockId;
                    }
                    if ($request->student_number) {
                        $studentUpdateData['student_number'] = $request->student_number;
                    }
                    
                    DB::table('students')->where('id', $existingStudent->id)->update($studentUpdateData);
                    $this->mirror->updatedFromLocal('students', $existingStudent->id);
                    
                    if ($blockChanged) {
                        $this->resetStudentData($existingStudent->id);
                        Log::info('Student data reset for student ID: ' . $existingStudent->id);
                    }
                } else {
                    $studentId = DB::table('students')->insertGetId([
                        'block_id' => $newBlockId ?? 1,
                        'student_number' => $request->student_number ?? 'STU-' . str_pad($id, 5, '0', STR_PAD_LEFT),
                        'first_name' => $request->first_name ?? explode(' ', $request->name)[0],
                        'last_name' => $request->last_name ?? (explode(' ', $request->name)[1] ?? 'Student'),
                        'email' => $request->email,
                        'status' => $request->status ?? 'Active',
                        'year_enrolled' => date('Y'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->mirror->createdFromLocal('students', $studentId);
                }
            }

            DB::commit();

            // Network I/O only after the commit.
            $this->mirror->flush();

            return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->mirror->discard();
            Log::error('Update user failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    protected function syncStaffProfile(Request $request, int $userId): void
    {
        $head = DB::table('academic_heads')->where('user_id', $userId)->first();
        $counselor = DB::table('counselors')->where('user_id', $userId)->first();

        $departmentId = $this->resolveDepartment($request, $head ?? $counselor);

        if ($request->role === 'academic_head' && $departmentId !== null) {
            if ($head !== null) {
                DB::table('academic_heads')->where('id', $head->id)->update([
                    'department_id' => $departmentId,
                    'specialization' => $request->input('specialization', $head->specialization),
                    'updated_at' => now(),
                ]);

                $this->mirror->updatedFromLocal('academic_heads', $head->id);
            } else {
                $headId = DB::table('academic_heads')->insertGetId([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'employee_number' => 'MT-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => $request->input('specialization', 'General'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mirror->createdFromLocal('academic_heads', $headId);
            }
        } elseif ($head !== null) {
            DB::table('academic_heads')->where('id', $head->id)->delete();
            $this->mirror->deleted('academic_heads', $head->id);
        }

        if ($request->role === 'guidance_counselor' && ($counselor !== null || $departmentId !== null)) {
            if ($counselor !== null) {
                DB::table('counselors')->where('id', $counselor->id)->update([
                    'department_id' => $departmentId,
                    'specialization' => $request->input('specialization', $counselor->specialization),
                    'max_caseload' => $request->input('max_caseload', $counselor->max_caseload),
                    'office_location' => $request->input('office_location', $counselor->office_location),
                    'updated_at' => now(),
                ]);

                $this->mirror->updatedFromLocal('counselors', $counselor->id);
            } else {
                $counselorId = DB::table('counselors')->insertGetId([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'employee_number' => 'GC-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => $request->input('specialization', 'Academic Counseling'),
                    'max_caseload' => $request->input('max_caseload', 30),
                    'office_location' => $request->input('office_location', 'Guidance Office'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mirror->createdFromLocal('counselors', $counselorId);
            }
        } elseif ($counselor !== null) {
            DB::table('counselors')->where('id', $counselor->id)->delete();
            $this->mirror->deleted('counselors', $counselor->id);
        }
    }

    protected function resolveDepartment(Request $request, ?object $existing): ?int
    {
        if (!$request->exists('department_id')) {
            return $existing !== null && $existing->department_id !== null
                ? (int) $existing->department_id
                : null;
        }

        $value = $request->input('department_id');

        return ($value === null || $value === '') ? null : (int) $value;
    }

    protected function resetStudentData(int $studentId): void
    {
        try {
            foreach (['grades', 'attendance', 'attendance_summaries', 'attendance_warnings'] as $table) {
                $ids = DB::table($table)->where('student_id', $studentId)->pluck('id')->all();
                DB::table($table)->where('student_id', $studentId)->delete();
                $this->mirror->purged($table, $ids);
            }
            
            $deletedRisk = DB::table('risk_scores')->where('student_id', $studentId)->delete();
            Log::info('Risk scores deleted for student ' . $studentId . ': ' . $deletedRisk . ' records');
            
            DB::table('flags')->where('student_id', $studentId)->delete();
            DB::table('intervention_recommendations')->where('student_id', $studentId)->delete();
            DB::table('alert_acknowledgments')->where('student_id', $studentId)->delete();
            DB::table('escalations')->where('student_id', $studentId)->delete();
            
            DB::table('cases')
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->update([
                    'status' => 'Closed',
                    'resolved_reason' => 'Student transferred to new program - data reset',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
            
            $caseIds = DB::table('cases')->where('student_id', $studentId)->pluck('id');
            if ($caseIds->isNotEmpty()) {
                DB::table('case_sessions')->whereIn('case_id', $caseIds)->delete();
            }
            
            // payments are keyed by their own id, so each row is mirrored by id.
            $paymentIds = DB::table('payments')->where('student_id', $studentId)->pluck('id');

            DB::table('payments')
                ->where('student_id', $studentId)
                ->update(['status' => 'Paid', 'updated_at' => now()]);

            foreach ($paymentIds as $paymentId) {
                $this->mirror->updatedFromLocal('payments', $paymentId);
            }
            
            $this->logActivity('STUDENT_DATA_RESET', 'Student ID: ' . $studentId . ' - Data reset for transfer');
            
        } catch (\Exception $e) {
            Log::error('Error resetting student data: ' . $e->getMessage());
        }
    }

    public function deleteUser(int $id)
    {
        // The users directory lives on the mock API now.
        $user = app(StaffRepository::class)->user($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
    
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 403);
        }

        // API reads happen BEFORE the transaction opens.
        $student = $user->role === 'student'
            ? app(StudentRepository::class)->findByEmail($user->email)
            : null;

        // Rows to remove are captured up front: json-server needs the primary keys.
        $staffRowIds = match ($user->role) {
            'academic_head' => DB::table('academic_heads')->where('user_id', $user->id)->pluck('id')->all(),
            'guidance_counselor' => DB::table('counselors')->where('user_id', $user->id)->pluck('id')->all(),
            default => [],
        };

        $purge = [];

        if ($student !== null) {
            // Tables the application never owns are purged on the API in bulk.
            foreach (['parents', 'grades', 'attendance', 'attendance_summaries', 'attendance_warnings'] as $table) {
                $purge[$table] = DB::table($table)->where('student_id', $student->id)->pluck('id')->all();
            }
        }

        $this->mirror->defer();

        try {
            DB::beginTransaction();

            DB::table('audit_logs')->where('user_id', $user->id)->delete();

            if ($user->role === 'academic_head') {
                DB::table('academic_heads')->where('user_id', $user->id)->delete();
            } elseif ($user->role === 'guidance_counselor') {
                DB::table('counselors')->where('user_id', $user->id)->delete();
            } elseif ($user->role === 'student' && $student !== null) {
                foreach (array_keys($purge) as $table) {
                    DB::table($table)->where('student_id', $student->id)->delete();
                }

                DB::table('risk_scores')->where('student_id', $student->id)->delete();
                DB::table('flags')->where('student_id', $student->id)->delete();
                DB::table('intervention_recommendations')->where('student_id', $student->id)->delete();
                DB::table('alert_acknowledgments')->where('student_id', $student->id)->delete();
                DB::table('escalations')->where('student_id', $student->id)->delete();
                DB::table('cases')->where('student_id', $student->id)->delete();
                DB::table('payments')->where('student_id', $student->id)->delete();
                DB::table('students')->where('id', $student->id)->delete();
            }

            DB::table('users')->where('id', $user->id)->delete();

            foreach ($staffRowIds as $staffRowId) {
                $this->mirror->deleted(
                    $user->role === 'academic_head' ? 'academic_heads' : 'counselors',
                    $staffRowId
                );
            }

            foreach ($purge as $table => $ids) {
                $this->mirror->purged($table, $ids);
            }

            if ($student !== null) {
                $this->mirror->deleted('students', $student->id);
            }

            $this->mirror->deleted('users', $user->id);

            DB::commit();

            // Network I/O only after the commit.
            $this->mirror->flush();

            $this->logActivity('USER_DELETED', 'Deleted user: ' . $user->email);
            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->mirror->discard();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function updateRoleSpecificData(int $userId, string $role, array $data)
    {
        // Delegates to syncStaffProfile() so the staff-row logic has exactly one
        // implementation and cannot drift from updateUser().
        $request = Request::create('/admin/users/' . $userId, 'PUT', array_merge($data, ['role' => $role]));

        $this->syncStaffProfile($request, $userId);
    }


    public function academic()
    {
        $structure = app(AcademicStructureRepository::class);

        $departmentIndex = $structure->departments()->keyBy(fn ($row) => (int) $row->id);
        $programIndex = $structure->programs()->keyBy(fn ($row) => (int) $row->id);

        $programCounts = $structure->programs()
            ->groupBy(fn ($row) => (int) $row->department_id)
            ->map->count();

        $departments = $structure->departments()->map(function ($row) use ($programCounts) {
            $model = new \App\Models\Department();
            $model->setRawAttributes((array) $row, true);
            $model->setAttribute('programs_count', (int) ($programCounts[(int) $row->id] ?? 0));

            return $model;
        })->values();

        $programs = $structure->programs()->map(function ($row) use ($departmentIndex) {
            $program = clone $row;
            $department = $departmentIndex[(int) $row->department_id] ?? null;
            $program->department_code = $department->code ?? null;

            return $program;
        })->values();

        // `subjects.*` joined to the owning program's code, ordered by program code,
        // then year level, then semester.
        $subjects = app(\App\Repositories\Api\SubjectRepository::class)->all()
            ->map(function ($row) use ($programIndex) {
                $subject = clone $row;
                $program = $programIndex[(int) $row->program_id] ?? null;
                $subject->program_code = $program->code ?? null;

                return $subject;
            })
            ->sort(function ($a, $b) {
                $cmp = strcmp((string) $a->program_code, (string) $b->program_code);

                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = ((int) $a->year_level <=> (int) $b->year_level);

                return $cmp !== 0 ? $cmp : ((int) $a->semester <=> (int) $b->semester);
            })
            ->values();

        return view('admin.academic', compact('departments', 'programs', 'subjects'));
    }

    public function storeDepartment(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:10', new ApiUnique('departments', 'code')],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        try {
            $id = DB::table('departments')->insertGetId([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('departments', $id);

            $this->logActivity('DEPARTMENT_CREATED', 'Created department: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Department created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create department: ' . $e->getMessage());
        }
    }

    public function updateDepartment(Request $request, int $id)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:10', new ApiUnique('departments', 'code', $id)],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        try {
            DB::table('departments')->where('id', $id)->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'updated_at' => now(),
            ]);

            $this->mirror->updatedFromLocal('departments', $id);

            $this->logActivity('DEPARTMENT_UPDATED', 'Updated department: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Department updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update department: ' . $e->getMessage());
        }
    }

    public function deleteDepartment(int $id)
    {
        try {
            // programs are served by the API, so the guard is evaluated against it.
            $programCount = app(AcademicStructureRepository::class)->programs()
                ->where('department_id', $id)
                ->count();

            if ($programCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with existing programs. Delete programs first.'
                ], 400);
            }

            DB::table('departments')->where('id', $id)->delete();

            $this->mirror->deleted('departments', $id);

            $this->logActivity('DEPARTMENT_DELETED', 'Deleted department ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Department deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeProgram(Request $request)
    {
        $request->validate([
            'department_id' => ['required', new ApiExists('departments', 'id')],
            'code' => ['required', 'string', 'max:10', new ApiUnique('programs', 'code')],
            'name' => 'required|string|max:100',
            'total_students' => 'nullable|integer|min:0',
        ]);

        try {
            $id = DB::table('programs')->insertGetId([
                'department_id' => $request->department_id,
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'total_students' => $request->total_students ?? 160,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('programs', $id);

            $this->logActivity('PROGRAM_CREATED', 'Created program: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Program created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create program: ' . $e->getMessage());
        }
    }

    public function updateProgram(Request $request, int $id)
    {
        $request->validate([
            'department_id' => ['required', new ApiExists('departments', 'id')],
            'code' => ['required', 'string', 'max:10', new ApiUnique('programs', 'code', $id)],
            'name' => 'required|string|max:100',
            'total_students' => 'nullable|integer|min:0',
        ]);

        try {
            DB::table('programs')->where('id', $id)->update([
                'department_id' => $request->department_id,
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'total_students' => $request->total_students ?? 160,
                'updated_at' => now(),
            ]);

            $this->mirror->updatedFromLocal('programs', $id);

            $this->logActivity('PROGRAM_UPDATED', 'Updated program: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Program updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update program: ' . $e->getMessage());
        }
    }

    public function deleteProgram(int $id)
    {
        try {
            // year_levels are served by the API, so the guard is evaluated against it.
            $yearLevelCount = app(AcademicStructureRepository::class)->yearLevels()
                ->where('program_id', $id)
                ->count();

            if ($yearLevelCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete program with existing year levels. Delete year levels first.'
                ], 400);
            }

            DB::table('programs')->where('id', $id)->delete();

            $this->mirror->deleted('programs', $id);

            $this->logActivity('PROGRAM_DELETED', 'Deleted program ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Program deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSubject(Request $request)
    {
        $request->validate([
            'program_id' => ['required', new ApiExists('programs', 'id')],
            'year_level' => 'required|integer|min:1|max:4',
            'semester' => 'required|integer|min:1|max:2',
            'subject_code' => ['required', 'string', 'max:20', new ApiUnique('subjects', 'subject_code')],
            'subject_name' => 'required|string|max:200',
            'units' => 'nullable|integer|min:1|max:6',
        ]);

        try {
            $id = DB::table('subjects')->insertGetId([
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'semester' => $request->semester,
                'subject_code' => strtoupper($request->subject_code),
                'subject_name' => $request->subject_name,
                'units' => $request->units ?? 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('subjects', $id);

            $this->logActivity('SUBJECT_CREATED', 'Created subject: ' . $request->subject_code);
            return redirect()->route('admin.academic.index')->with('success', 'Subject created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subject: ' . $e->getMessage());
        }
    }

    public function updateSubject(Request $request, int $id)
    {
        $request->validate([
            'program_id' => ['required', new ApiExists('programs', 'id')],
            'year_level' => 'required|integer|min:1|max:4',
            'semester' => 'required|integer|min:1|max:2',
            'subject_code' => ['required', 'string', 'max:20', new ApiUnique('subjects', 'subject_code', $id)],
            'subject_name' => 'required|string|max:200',
            'units' => 'nullable|integer|min:1|max:6',
        ]);

        try {
            DB::table('subjects')->where('id', $id)->update([
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'semester' => $request->semester,
                'subject_code' => strtoupper($request->subject_code),
                'subject_name' => $request->subject_name,
                'units' => $request->units ?? 3,
                'updated_at' => now(),
            ]);

            $this->mirror->updatedFromLocal('subjects', $id);

            $this->logActivity('SUBJECT_UPDATED', 'Updated subject: ' . $request->subject_code);
            return redirect()->route('admin.academic.index')->with('success', 'Subject updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update subject: ' . $e->getMessage());
        }
    }

    public function deleteSubject(int $id)
    {
        try {
            // grades are served by the API now, so the guard is evaluated against it.
            $gradeCount = app(GradeRepository::class)->countForSubject($id);

            if ($gradeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete subject with existing grades. Delete grades first.'
                ], 400);
            }

            DB::table('subjects')->where('id', $id)->delete();

            $this->mirror->deleted('subjects', $id);

            $this->logActivity('SUBJECT_DELETED', 'Deleted subject ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Subject deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function editDepartment(int $id)
    {
        $department = app(AcademicStructureRepository::class)->departments()
            ->firstWhere(fn ($row) => (int) $row->id === $id);

        if (!$department) {
            return back()->with('error', 'Department not found.');
        }

        return view('admin.academic-edit-department', compact('department'));
    }

    public function editProgram(int $id)
    {
        $structure = app(AcademicStructureRepository::class);

        $program = $structure->programs()->firstWhere(fn ($row) => (int) $row->id === $id);

        if (!$program) {
            return back()->with('error', 'Program not found.');
        }

        $departments = $structure->departments();

        return view('admin.academic-edit-program', compact('program', 'departments'));
    }

    public function editSubject(int $id)
    {
        // subjects are served by the mock API now.
        $subject = app(\App\Repositories\Api\SubjectRepository::class)->find($id);

        if (!$subject) {
            return back()->with('error', 'Subject not found.');
        }

        $programs = app(AcademicStructureRepository::class)->programs();

        return view('admin.academic-edit-subject', compact('subject', 'programs'));
    }


    public function riskConfig(RiskThresholdService $thresholds)
    {
        // The view renders its band labels from the SAME values the engine classifies
        // with, so display, validation, computation and storage can never drift.
        $config = $thresholds->current();
        $row = DB::table('risk_thresholds')->orderBy('id')->first();

        $editorName = ($row && $row->updated_by)
            ? DB::table('users')->where('id', $row->updated_by)->value('name')
            : null;

        return view('admin.risk-config', [
            'thresholds' => (object) $config,
            'bands' => RiskThresholdService::bandLabels($config),
            'effectiveBands' => RiskThresholdService::effectiveBandLabels($config),
            'configuredAt' => $row->updated_at ?? null,
            'configuredBy' => $editorName,
        ]);
    }

    public function saveRiskConfig(
        Request $request,
        RiskThresholdService $thresholds,
        RiskScoringCacheService $cache,
        RiskScoreRepository $riskScores
    ) {
        $request->validate([
            'low_threshold' => 'required|integer|min:0|max:98',
            'moderate_threshold' => 'required|integer|min:1|max:99',
            'high_threshold' => 'required|integer|min:2|max:100',
        ]);

        $config = RiskThresholdService::normalize([
            'low_threshold' => $request->integer('low_threshold'),
            'moderate_threshold' => $request->integer('moderate_threshold'),
            'high_threshold' => $request->integer('high_threshold'),
        ]);

        // Bands must tile 0-100 with no gap (high = moderate + 1): the engine classifies
        // every integer score exactly once, so display, validation and computation agree.
        $error = RiskThresholdService::consistencyError($config);
        if ($error !== null) {
            return back()->withInput()->with('error', $error);
        }

        $existing = DB::table('risk_thresholds')->orderBy('id')->first();

        $values = [
            'low_threshold' => $config['low_threshold'],
            'moderate_threshold' => $config['moderate_threshold'],
            'high_threshold' => $config['high_threshold'],
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('risk_thresholds')->where('id', $existing->id)->update($values);
            $this->mirror->updatedFromLocal('risk_thresholds', $existing->id);
        } else {
            $thresholdId = DB::table('risk_thresholds')->insertGetId($values + ['created_at' => now()]);
            $this->mirror->createdFromLocal('risk_thresholds', $thresholdId);
        }

        // A deliberate configuration change must never be deferred by the 60-minute
        // scoring cache, and the rows already stored must agree with the new bands.
        $cache->flushAll();
        $thresholds->forget();
        $reclassified = $riskScores->reclassifyByBands($config);

        $summary = 'Low 0-' . $config['low_threshold']
            . ', Moderate ' . ($config['low_threshold'] + 1) . '-' . $config['moderate_threshold']
            . ', High ' . $config['high_threshold'] . '-100';

        $this->logActivity(
            'RISK_CONFIG_UPDATED',
            'Risk thresholds updated to ' . $summary . '; ' . $reclassified
                . ' stored score(s) re-classified; cached risk scores invalidated'
        );

        return back()->with(
            'success',
            'Risk configuration saved (' . $summary . '). ' . $reclassified
                . ' stored score(s) re-classified and cached risk scores invalidated - '
                . 'the next scoring run uses the new bands.'
        );
    }

    public function testAIConnection()
    {
        $apiKey = config('services.aistudio.api_key');
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'message' => 'AI API key is not configured in .env file.']);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'AI API connection successful.']);
            }
            return response()->json(['success' => false, 'message' => 'AI API connection failed: ' . $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'AI API connection error: ' . $e->getMessage()]);
        }
    }


    public function payments(Request $request)
    {
        try {
            $statusFilter = $request->input('status', 'all');
            $departmentFilter = $request->input('department', 'all');
            $searchFilter = $request->input('search', '');
            $paymentType = $request->input('payment_type', 'tuition');

            $payments = collect();

            foreach (app(PaymentRepository::class)->decorated() as $row) {
                if ($statusFilter !== 'all' && (string) $row->status !== (string) $statusFilter) {
                    continue;
                }
                if ($departmentFilter !== 'all' && (string) $row->department_code !== (string) $departmentFilter) {
                    continue;
                }
                if ($searchFilter !== '') {
                    $haystacks = [$row->first_name, $row->last_name, $row->student_number, $row->student_email];
                    $matched = false;

                    foreach ($haystacks as $value) {
                        if (stripos((string) $value, $searchFilter) !== false) {
                            $matched = true;
                            break;
                        }
                    }

                    if (!$matched) {
                        continue;
                    }
                }

                $payments->push($row);
            }

            // ORDER BY payments.due_date ASC, with id ASC as the tiebreak.
            $payments = $payments->sort(function ($a, $b) {
                $cmp = strcmp((string) $a->due_date, (string) $b->due_date);

                return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
            })->values();

            $perPage = 20;
            $page = max(1, (int) $request->input('page', 1));

            $payments = new LengthAwarePaginator(
                $payments->forPage($page, $perPage)->values(),
                $payments->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
            );

            $stats = $this->getPaymentStats($statusFilter, $departmentFilter);
            $departments = app(AcademicStructureRepository::class)->departments();
            $departmentSummary = $this->getDepartmentPaymentSummary();
            $overdueSummary = $this->getOverdueSummary();
            $paymentTrend = $this->getPaymentTrend();
            
            Log::info('Payment Monitoring loaded', [
                'total_payments' => $payments->total(),
                'filter_applied' => $statusFilter,
            ]);
            
            return view('admin.payments', [
                'payments' => $payments,
                'stats' => $stats,
                'departments' => $departments,
                'departmentSummary' => $departmentSummary,
                'overdueSummary' => $overdueSummary,
                'paymentTrend' => $paymentTrend,
                'statusFilter' => $statusFilter,
                'departmentFilter' => $departmentFilter,
                'searchFilter' => $searchFilter,
                'paymentType' => $paymentType,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment Monitoring Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load payment data: ' . $e->getMessage());
        }
    }




    protected function getPaymentStats($statusFilter = 'all', $departmentFilter = 'all')
    {
        try {
            // Aggregation happens in PHP because the joined tables are all served by
            // the mock API (json-server has no SUM/COUNT/GROUP BY).
            $rows = app(PaymentRepository::class)->decorated();

            if ($statusFilter !== 'all') {
                $rows = $rows->where('status', $statusFilter);
            }
            if ($departmentFilter !== 'all') {
                $rows = $rows->where('department_code', $departmentFilter);
            }

            $totalAmount = (float) $rows->sum(fn ($row) => (float) $row->amount);
            $collectedAmount = (float) $rows->sum(fn ($row) => (float) $row->paid_amount);
            $overdueAmount = (float) $rows->where('status', 'Overdue')->sum(fn ($row) => (float) $row->balance);
            $outstandingAmount = (float) $rows
                ->whereIn('status', ['Unpaid', 'Partial', 'Overdue'])
                ->sum(fn ($row) => (float) $row->balance);

            $totalCount = $rows->count();
            $paidCount = $rows->where('status', 'Paid')->count();
            $partialCount = $rows->where('status', 'Partial')->count();
            $unpaidCount = $rows->where('status', 'Unpaid')->count();
            $overdueCount = $rows->where('status', 'Overdue')->count();
            $collectionRate = $totalAmount > 0 ? round(($collectedAmount / $totalAmount) * 100, 1) : 0;
            
            return [
                'total_amount' => $totalAmount,
                'collected_amount' => $collectedAmount,
                'overdue_amount' => $overdueAmount,
                'outstanding_amount' => $outstandingAmount,
                'total_count' => $totalCount,
                'paid_count' => $paidCount,
                'partial_count' => $partialCount,
                'unpaid_count' => $unpaidCount,
                'overdue_count' => $overdueCount,
                'collection_rate' => $collectionRate,
            ];
        } catch (\Exception $e) {
            Log::error('Payment Stats Error: ' . $e->getMessage());
            return [
                'total_amount' => 0,
                'collected_amount' => 0,
                'overdue_amount' => 0,
                'outstanding_amount' => 0,
                'total_count' => 0,
                'paid_count' => 0,
                'partial_count' => 0,
                'unpaid_count' => 0,
                'overdue_count' => 0,
                'collection_rate' => 0,
            ];
        }
    }

    protected function getDepartmentPaymentSummary()
    {
        try {
            // GROUP BY departments.code, with the SUM(CASE WHEN ...) counters folded
            // in PHP because the tables are all served by the mock API.
            return app(PaymentRepository::class)->decorated()
                ->groupBy(fn ($row) => (string) $row->department_code)
                ->map(function ($rows, $department) {
                    return (object) [
                        'department' => $department,
                        'total_students' => $rows->count(),
                        'total_amount' => (float) $rows->sum(fn ($row) => (float) $row->amount),
                        'collected_amount' => (float) $rows->sum(fn ($row) => (float) $row->paid_amount),
                        'outstanding_amount' => (float) $rows->sum(fn ($row) => (float) $row->balance),
                        'paid_count' => $rows->where('status', 'Paid')->count(),
                        'overdue_count' => $rows->where('status', 'Overdue')->count(),
                        'unpaid_count' => $rows->where('status', 'Unpaid')->count(),
                        'partial_count' => $rows->where('status', 'Partial')->count(),
                    ];
                })
                ->sortKeys()
                ->values();
        } catch (\Exception $e) {
            Log::error('Department Payment Summary Error: ' . $e->getMessage());
            return collect();
        }
    }

    protected function getOverdueSummary()
    {
        try {
            $today = $this->databaseToday();

            return app(PaymentRepository::class)->decorated()
                ->where('status', 'Overdue')
                ->sort(function ($a, $b) {
                    $cmp = strcmp((string) $a->due_date, (string) $b->due_date);

                    return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
                })
                ->take(10)
                ->map(function ($row) use ($today) {
                    $summary = clone $row;
                    $summary->email = $row->student_email;
                    $summary->days_overdue = $this->datediff($today, (string) $row->due_date);

                    return $summary;
                })
                ->values();
        } catch (\Exception $e) {
            Log::error('Overdue Summary Error: ' . $e->getMessage());
            return collect();
        }
    }

    protected function databaseToday(): string
    {
        $row = DB::selectOne('SELECT CURDATE() AS today');

        return (string) ($row->today ?? date('Y-m-d'));
    }

    protected function datediff(string $from, string $to): int
    {
        $fromDate = new \DateTimeImmutable($from);
        $toDate = new \DateTimeImmutable($to);
        $days = (int) $fromDate->diff($toDate)->days;

        return $toDate <= $fromDate ? $days : -$days;
    }

protected function getPaymentTrend(): array
{
    try {
        $months = [];
        $collected = [];
        $overdue = [];

        // Both source tables are served by the mock API, so the per-month sums are
        // folded in PHP rather than by SQL aggregates.
        $history = app(PaymentRepository::class)->history();
        $payments = app(PaymentRepository::class)->all();
        
        for ($i = 0; $i < 6; $i++) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M Y');
            $months[] = $monthName;
            
            $collectedAmount = $history
                ->filter(fn ($row) => (int) \Carbon\Carbon::parse($row->created_at)->month === $month->month
                    && (int) \Carbon\Carbon::parse($row->created_at)->year === $month->year
                    && (string) $row->action === 'Payment Received')
                ->sum(fn ($row) => (float) $row->amount_changed);
            
            $collected[] = round((float) $collectedAmount, 2);
            
            $overdueAmount = $payments
                ->filter(fn ($row) => $row->due_date !== null
                    && (int) \Carbon\Carbon::parse($row->due_date)->month === $month->month
                    && (int) \Carbon\Carbon::parse($row->due_date)->year === $month->year
                    && (string) $row->status === 'Overdue')
                ->sum(fn ($row) => (float) $row->balance);
            
            $overdue[] = round((float) $overdueAmount, 2);
        }
        
        return [
            'months' => array_reverse($months),
            'collected' => array_reverse($collected),
            'overdue' => array_reverse($overdue),
        ];
        
    } catch (\Exception $e) {
        Log::error('Payment Trend Error: ' . $e->getMessage());
        return [
            'months' => [],
            'collected' => [],
            'overdue' => [],
        ];
    }
}

public function viewPayment(int $id)
{
    try {
        // payments, students and the placement chain are served by the mock API now.
        $payment = app(PaymentRepository::class)->decoratedById($id);
        
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }
        
        $payment->amount = (float) $payment->amount;
        $payment->paid_amount = (float) $payment->paid_amount;
        $payment->balance = (float) $payment->balance;
        
        return response()->json(['success' => true, 'payment' => $payment]);
        
    } catch (\Exception $e) {
        Log::error('View Payment Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

public function getPaymentInfo($id)
{
    $debug = [
        'method' => 'getPaymentInfo',
        'timestamp' => now()->toDateTimeString(),
        'payment_id' => $id,
        'user_id' => auth()->id(),
    ];

    try {
        $id = (int) $id;
        $debug['payment_id_int'] = $id;

        Log::info('[GetPaymentInfo] Fetching payment info', $debug);

        // payments and students are both served by the mock API; the join is
        // reconstructed in PHP, keeping the INNER JOIN semantics of the original.
        $repository = app(PaymentRepository::class);
        $paymentRow = $repository->find($id);
        $student = $paymentRow !== null
            ? app(StudentRepository::class)->find($paymentRow->student_id)
            : null;

        $payment = null;

        if ($paymentRow !== null && $student !== null) {
            $payment = (object) [
                'balance' => $paymentRow->balance,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
            ];
        }

        if (!$payment) {
            $debug['payment_found'] = false;
            Log::warning('[GetPaymentInfo] Payment not found', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'debug' => $debug,
            ], 404);
        }

        $debug['payment_found'] = true;
        $debug['student_name'] = $payment->first_name . ' ' . $payment->last_name;
        $debug['balance'] = (float) $payment->balance;

        Log::info('[GetPaymentInfo] Payment info retrieved', $debug);

        return response()->json([
            'success' => true,
            'student_name' => $payment->first_name . ' ' . $payment->last_name,
            'balance' => (float) $payment->balance,
            'debug' => $debug,
        ]);

    } catch (\Exception $e) {
        $debug['error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        Log::error('[GetPaymentInfo] Error fetching payment info', $debug);
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'debug' => $debug,
        ], 500);
    }
}
public function recordPayment(Request $request, $id)
{
    $debug = [
        'method' => 'recordPayment',
        'timestamp' => now()->toDateTimeString(),
        'payment_id' => $id,
        'request_data' => $request->all(),
        'user_id' => auth()->id(),
        'ip' => $request->ip(),
    ];

    try {
        $id = (int) $id;
        $debug['payment_id_int'] = $id;
        
        Log::info('[RecordPayment] Starting payment recording', $debug);

        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'reference' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500',
            ]);
            $debug['validation_passed'] = true;
            $debug['validated_data'] = $validated;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $debug['validation_errors'] = $e->errors();
            Log::error('[RecordPayment] Validation failed', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_merge(...array_values($e->errors()))),
                'debug' => $debug,
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // payments is served by the mock API now.
            $payment = app(PaymentRepository::class)->find($id);
            if (!$payment) {
                $debug['payment_found'] = false;
                Log::warning('[RecordPayment] Payment not found', $debug);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                    'debug' => $debug,
                ], 404);
            }
            $debug['payment_found'] = true;
            $debug['payment_data'] = [
                'id' => $payment->id,
                'student_id' => $payment->student_id,
                'amount' => $payment->amount,
                'paid_amount' => $payment->paid_amount,
                'balance' => $payment->balance,
                'status' => $payment->status,
            ];
            Log::info('[RecordPayment] Payment found', $debug);
        } catch (\Exception $e) {
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Database query failed - finding payment', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

        try {
            $newPaidAmount = (float) $payment->paid_amount + (float) $request->amount;
            $newBalance = (float) $payment->amount - $newPaidAmount;
            
            if ($newBalance <= 0.01) {
                $newStatus = 'Paid';
            } elseif ($newPaidAmount > 0) {
                $newStatus = 'Partial';
            } else {
                $newStatus = 'Unpaid';
            }
            
            $debug['calculations'] = [
                'old_paid' => (float) $payment->paid_amount,
                'new_paid' => $newPaidAmount,
                'old_balance' => (float) $payment->balance,
                'new_balance' => $newBalance,
                'old_status' => $payment->status,
                'new_status' => $newStatus,
            ];
            Log::info('[RecordPayment] Calculations completed', $debug);
        } catch (\Exception $e) {
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Calculation error', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Calculation error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

        // Database transaction
        try {
            $this->mirror->defer();

            DB::beginTransaction();
            $debug['transaction_started'] = true;

            $updated = DB::table('payments')
                ->where('id', $id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus,
                    'last_payment_date' => $request->payment_date,
                    'updated_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            $this->mirror->updatedFromLocal('payments', $id);

            $debug['payment_update'] = [
                'rows_affected' => $updated,
                'query' => 'UPDATE payments SET paid_amount = ?, balance = ?, status = ?, last_payment_date = ?, updated_at = ?, updated_by = ? WHERE id = ?',
            ];
            Log::info('[RecordPayment] Payment updated', $debug);

            $historyId = DB::table('payment_history')->insertGetId([
                'payment_id' => $id,
                'action' => 'Payment Received',
                'old_status' => $payment->status,
                'new_status' => $newStatus,
                'amount_changed' => (float) $request->amount,
                'performed_by' => auth()->id(),
                'notes' => ($request->notes ?? '') . ' | Ref: ' . ($request->reference ?? 'N/A'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('payment_history', $historyId);

            $debug['history_inserted'] = $historyId;
            Log::info('[RecordPayment] Payment history recorded', $debug);

            $this->logActivity('PAYMENT_RECORDED', 'Payment recorded for student ' . $payment->student_id . ' | Amount: ' . $request->amount);
            $debug['activity_logged'] = true;

            DB::commit();
            $debug['transaction_committed'] = true;

            // Network I/O only after the commit.
            $this->mirror->flush();

            Log::info('[RecordPayment] Payment recorded successfully', $debug);

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'new_balance' => max(0, $newBalance),
                'new_status' => $newStatus,
                'debug' => $debug,
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
                $debug['transaction_rolled_back'] = true;
            }
            // Nothing was committed, so the queued mirrors must not be sent.
            $this->mirror->discard();
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Transaction error', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Transaction error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
            $debug['transaction_rolled_back'] = true;
        }
        $debug['error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        Log::error('[RecordPayment] Unexpected error', $debug);
        return response()->json([
            'success' => false,
            'message' => 'Unexpected error: ' . $e->getMessage(),
            'debug' => $debug,
        ], 500);
    }
}




public function exportPayments(Request $request)
{
    try {
        $statusFilter = $request->input('status', 'all');
        $departmentFilter = $request->input('department', 'all');
        $searchFilter = $request->input('search', '');
        
        Log::info('[Export] Starting payment export', [
            'status' => $statusFilter,
            'department' => $departmentFilter,
            'search' => $searchFilter,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);
        
        // payments, students and the placement chain are served by the mock API, so the
        // six-table JOIN is rebuilt in PHP (INNER JOIN semantics live in decorated()).
        $payments = app(PaymentRepository::class)->decorated();

        if ($statusFilter !== 'all') {
            $payments = $payments->where('status', $statusFilter);
        }

        if ($departmentFilter !== 'all') {
            $payments = $payments->where('department_code', $departmentFilter);
        }

        if (!empty($searchFilter)) {
            $payments = $payments->filter(function ($row) use ($searchFilter) {
                foreach ([$row->first_name, $row->last_name, $row->student_number, $row->student_email] as $value) {
                    if (stripos((string) $value, $searchFilter) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        // ORDER BY payments.due_date ASC, with id ASC as the tiebreak.
        $payments = $payments->sort(function ($a, $b) {
            $cmp = strcmp((string) $a->due_date, (string) $b->due_date);

            return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
        })->values();
        
        Log::info('[Export] Payment records fetched', ['count' => $payments->count()]);
        
        $filename = 'payment_report_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'r+');
        
        fputcsv($handle, [
            'Student Number',
            'First Name',
            'Last Name',
            'Email',
            'Program',
            'Department',
            'Fee Type',
            'Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Due Date',
            'Last Payment Date'
        ]);
        
        foreach ($payments as $payment) {
            fputcsv($handle, [
                $payment->student_number,
                $payment->first_name,
                $payment->last_name,
                $payment->student_email,
                $payment->program_code . ' - ' . $payment->program_name,
                $payment->department_code,
                $payment->fee_type,
                number_format($payment->amount, 2),
                number_format($payment->paid_amount, 2),
                number_format($payment->balance, 2),
                $payment->status,
                $payment->due_date,
                $payment->last_payment_date ?? 'N/A'
            ]);
        }
        
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);
        
        Log::info('[Export] CSV generated successfully', ['size' => strlen($csvContent)]);
        
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        
    } catch (\Exception $e) {
        Log::error('[Export] Export error: ' . $e->getMessage());
        Log::error('[Export] Export trace: ' . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Failed to export payments: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
}





    public function auditLogs(Request $request)
    {
        $actionFilter = $request->input('action', 'all');
        $userFilter = $request->input('user', 'all');
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        
        // audit_logs stays local, but `users` is served by the mock API, so the join is
        // reconstructed in PHP. The filters are applied against the local rows first.
        $usersById = app(StaffRepository::class)->users()
            ->keyBy(fn ($row) => (int) $row->id);

        $logs = DB::table('audit_logs')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($actionFilter !== 'all') {
            $logs->where('action', $actionFilter);
        }
        if ($userFilter !== 'all') {
            $logs->where('user_id', $userFilter);
        }

        $logs = $logs->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($log) use ($usersById) {
                $user = $usersById[(int) $log->user_id] ?? null;
                $log->user_name = $user->name ?? null;
                $log->user_email = $user->email ?? null;
                $log->created_at = \Carbon\Carbon::parse($log->created_at);

                return $log;
            });

        $users = app(StaffRepository::class)->users()
            ->map(fn ($row) => (object) ['id' => (int) $row->id, 'name' => $row->name])
            ->values();

        $actions = DB::table('audit_logs')->distinct()->pluck('action');
        
        return view('admin.audit-logs', compact('logs', 'users', 'actions', 'dateFrom', 'dateTo'));
    }

    public function exportAuditLogs(Request $request)
    {
        try {
            $actionFilter = $request->input('action', 'all');
            $userFilter = $request->input('user', 'all');
            $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->input('date_to', now()->format('Y-m-d'));

            Log::info('[AuditExport] Starting audit log export', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'action' => $actionFilter,
                'user' => $userFilter,
            ]);

            $usersById = app(StaffRepository::class)->users()
                ->keyBy(fn ($row) => (int) $row->id);

            $logs = DB::table('audit_logs')
                ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($actionFilter !== 'all') {
                $logs->where('action', $actionFilter);
            }
            if ($userFilter !== 'all') {
                $logs->where('user_id', $userFilter);
            }

            $logs = $logs->orderBy('created_at', 'desc')->get();

            $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'r+');

            // The same four columns the table shows, in the same order.
            fputcsv($handle, ['Date/Time', 'User', 'Action', 'IP Address']);

            foreach ($logs as $log) {
                $user = $usersById[(int) $log->user_id] ?? null;

                fputcsv($handle, [
                    \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s'),
                    // Null when the id is not in the directory — the page renders the
                    // cell empty in that case, so the file leaves it empty too.
                    $user->name ?? null,
                    $log->action,
                    $log->ip_address,
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            Log::info('[AuditExport] CSV generated successfully', [
                'rows' => $logs->count(),
                'size' => strlen($csvContent),
            ]);

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            Log::error('[AuditExport] Export error: ' . $e->getMessage());
            Log::error('[AuditExport] Export trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export audit logs: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function schoolYear()
    {
        // school_years is served by the mock API now.
        $schoolYears = app(CalendarRepository::class)->schoolYears()
            ->sortByDesc(fn ($row) => (string) $row->name)
            ->values();

        $currentYear = $schoolYears->first(fn ($row) => (bool) $row->is_active);

        return view('admin.school-year', compact('schoolYears', 'currentYear'));
    }

    public function createSchoolYear(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', new ApiUnique('school_years', 'name')],
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'is_active' => 'boolean',
        ]);

        $this->mirror->defer();

        try {
            DB::beginTransaction();

            if ($request->is_active) {
                $deactivated = DB::table('school_years')->where('is_active', true)->pluck('id')->all();

                DB::table('school_years')->update(['is_active' => false]);

                foreach ($deactivated as $yearId) {
                    $this->mirror->updatedFromLocal('school_years', $yearId);
                }
            }

            $id = DB::table('school_years')->insertGetId([
                'name' => $request->name,
                'started_at' => $request->started_at,
                'ended_at' => $request->ended_at,
                'is_active' => $request->is_active ?? false,
                'is_archived' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirror->createdFromLocal('school_years', $id);

            DB::commit();

            // Network I/O only after the commit.
            $this->mirror->flush();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->mirror->discard();

            return back()->with('error', 'Failed to create school year: ' . $e->getMessage());
        }

        $this->logActivity('SCHOOL_YEAR_CREATED', 'Created school year: ' . $request->name);
        return back()->with('success', 'School year created successfully.');
    }

    public function archiveSchoolYear(Request $request, int $id)
    {
        // school_years is served by the mock API now.
        $year = app(CalendarRepository::class)->findSchoolYear($id);

        if (!$year) {
            return back()->with('error', 'School year not found.');
        }
        if ($year->is_active) {
            return back()->with('error', 'Cannot archive the active school year.');
        }

        DB::table('school_years')->where('id', $id)->update([
            'is_archived' => true,
            'updated_at' => now(),
        ]);

        $this->mirror->updatedFromLocal('school_years', $id);

        $this->logActivity('SCHOOL_YEAR_ARCHIVED', 'Archived school year: ' . $year->name);
        return back()->with('success', 'School year archived successfully.');
    }

    public function rolloverSchoolYear(Request $request)
    {
        $request->validate([
            'from_year' => ['required', new ApiExists('school_years', 'name')],
            'to_year' => ['required', new ApiExists('school_years', 'name'), 'different:from_year'],
        ]);

        $this->logActivity('SCHOOL_YEAR_ROLLOVER', 'Rollover from ' . $request->from_year . ' to ' . $request->to_year);
        return back()->with('success', 'School year rollover initiated. Students will be promoted to the next year level.');
    }


    public function systemHealth()
    {
        $health = [
            'database' => $this->checkDatabase(),
            'ai_api' => $this->checkAIApi(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
        ];

        $errors = $this->getRecentErrors();
        return view('admin.system-health', compact('health', 'errors'));
    }


    protected function getSystemStats(): array
    {
        // students and payments are served by the mock API; risk_scores and cases
        // remain local post-computation aggregates.
        $students = app(StudentRepository::class)->all();
        $payments = app(PaymentRepository::class)->all();

        return [
            'total_students' => $students->where('status', 'Active')->count(),
            'high_risk' => DB::table('risk_scores')
                ->where('risk_level', 'High')->where('grading_period', 'Midterm')->count(),
            'moderate_risk' => DB::table('risk_scores')
                ->where('risk_level', 'Moderate')->where('grading_period', 'Midterm')->count(),
            'critical_cases' => DB::table('cases')
                ->where('priority', 'Critical')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'open_cases' => DB::table('cases')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'resolved_cases' => DB::table('cases')->where('status', 'Resolved')->count(),
            'total_payments' => (float) $payments->sum(fn ($row) => (float) $row->amount),
            'collected_payments' => (float) $payments->sum(fn ($row) => (float) $row->paid_amount),
            'overdue_payments' => $payments->where('status', 'Overdue')->count(),
        ];
    }

    protected function getRiskByDepartment()
    {
        $structure = app(AcademicStructureRepository::class);
        $riskScores = app(RiskScoreRepository::class);
        $data = [];

        foreach ($structure->departments()->sortBy('code')->values() as $department) {
            $studentIds = $structure->studentIdsInDepartment($department->id, false);
            $counts = $riskScores->levelCountsForPeriodAllYears($studentIds, 'Midterm');

            $data[] = (object) [
                'id' => (int) $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'total_students' => count($studentIds),
                'high_risk' => $counts['High'],
                'moderate_risk' => $counts['Moderate'],
                'low_risk' => $counts['Low'],
            ];
        }

        return collect($data);
    }

    protected function getRiskDistribution(): array
    {
        return [
            'Low' => DB::table('risk_scores')->where('risk_level', 'Low')->where('grading_period', 'Midterm')->count(),
            'Moderate' => DB::table('risk_scores')->where('risk_level', 'Moderate')->where('grading_period', 'Midterm')->count(),
            'High' => DB::table('risk_scores')->where('risk_level', 'High')->where('grading_period', 'Midterm')->count(),
        ];
    }

    protected function getRiskTrend(): array
    {
        $periods = ['Prelim', 'Midterm', 'Finals'];
        $trend = [];

        foreach ($periods as $period) {
            $high = DB::table('risk_scores')
                ->where('risk_level', 'High')->where('grading_period', $period)->count();
            $total = DB::table('risk_scores')->where('grading_period', $period)->count();
            $trend[] = [
                'period' => $period,
                'high_risk' => $high,
                'total' => $total,
                'percentage' => $total > 0 ? round(($high / $total) * 100, 1) : 0,
            ];
        }
        return $trend;
    }

    protected function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'ai_api' => $this->checkAIApi(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkAIApi(): array
    {
        $apiKey = config('services.aistudio.api_key');
        if (empty($apiKey)) {
            return ['status' => 'warning', 'message' => 'API key not configured'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
            if ($response->successful()) {
                return ['status' => 'ok', 'message' => 'Connected'];
            }
            return ['status' => 'error', 'message' => 'Connection failed: ' . $response->status()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        $queueDriver = config('queue.default');
        return ['status' => 'ok', 'message' => 'Running (' . $queueDriver . ')'];
    }

    protected function checkStorage(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;
            $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;
            $status = $percentage > 90 ? 'warning' : 'ok';
            return [
                'status' => $status,
                'message' => $percentage . '% used',
                'used' => $this->formatBytes($used),
                'total' => $this->formatBytes($total),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $result = Cache::get('health_check');
            return $result === 'ok'
                ? ['status' => 'ok', 'message' => 'Working']
                : ['status' => 'error', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkSession(): array
    {
        try {
            session()->put('health_check', 'ok');
            $result = session()->get('health_check');
            return $result === 'ok'
                ? ['status' => 'ok', 'message' => 'Working']
                : ['status' => 'error', 'message' => 'Session read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function getRecentActivities()
    {
        // audit_logs stays local; `users` is served by the mock API, so the join is
        // reconstructed in PHP.
        $usersById = app(StaffRepository::class)->users()
            ->keyBy(fn ($row) => (int) $row->id);

        $activities = DB::table('audit_logs')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $activities->map(function ($activity) use ($usersById) {
            $user = $usersById[(int) $activity->user_id] ?? null;
            $activity->user_name = $user->name ?? null;
            $activity->created_at = \Carbon\Carbon::parse($activity->created_at);
            return $activity;
        });
    }

    protected function getRecentErrors(): array
    {
        $errors = [];
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", $content);
            $errorLines = array_filter($lines, function($line) {
                return strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false;
            });
            $errors = array_slice(array_values($errorLines), -5);
        }
        return $errors;
    }

    protected function logActivity(string $action, string $details): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Admin',
            'new_values' => json_encode(['details' => $details]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function formatBytes($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
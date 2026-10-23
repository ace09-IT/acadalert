<?php

namespace App\Http\Controllers;

use App\Repositories\Api\AcademicStructureRepository;
use App\Repositories\Api\StaffRepository;
use App\Repositories\Api\StudentRepository;
use App\Services\Api\MirrorWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function __construct(protected MirrorWriter $mirror)
    {
    }

    public function show(StaffRepository $staff)
    {
        $user = Auth::user();

        $student = null;
        $placement = null;

        if ($user->isStudent()) {
            $student = app(StudentRepository::class)->findByEmail($user->email);
            $placement = $student !== null
                ? app(AcademicStructureRepository::class)->blockPlacement($student->block_id)
                : null;
        }

        // Read-only context shown beside the forms (never editable here: roles and
        // placements are the Admin and Academic Head modules' responsibility).
        $staffProfile = $user->isStudent()
            ? null
            : ($user->isAcademicHead()
                ? $staff->academicHeadForUser($user->id)
                : ($user->isCounselor() ? $staff->counselorForUser($user->id) : null));

        return view('settings', [
            'user' => $user,
            'isStudent' => $user->isStudent(),
            'student' => $student,
            'placement' => $placement,
            'staffProfile' => $staffProfile,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            // Unique against the local `users` table (the auth source of truth),
            // ignoring the current row — the same rule the Admin user editor uses.
            'email' => 'required|email|max:100|unique:users,email,' . $user->id,
        ]);

        // Read everything the write path needs from the API BEFORE opening a
        // transaction: no HTTP request may run while a transaction is held.
        $student = $user->isStudent()
            ? app(StudentRepository::class)->findByEmail($user->email)
            : null;

        $this->mirror->defer();

        try {
            DB::beginTransaction();

            DB::table('users')->where('id', $user->id)->update([
                'name' => $request->name,
                'email' => $request->email,
                'updated_at' => now(),
            ]);

            $this->mirror->updatedFromLocal('users', $user->id);

            if ($student !== null) {
                [$firstName, $lastName] = $this->splitName($request->name, $student->last_name);

                DB::table('students')->where('id', $student->id)->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $request->email,
                    'updated_at' => now(),
                ]);

                $this->mirror->updatedFromLocal('students', $student->id);
            }

            DB::commit();

            // Network I/O only after the commit.
            $this->mirror->flush();

            return redirect()->route('settings')->with('success', 'Account details updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->mirror->discard();
            Log::error('Self-service account update failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to update your account: ' . $e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $this->mirror->defer();

        try {
            DB::beginTransaction();

            DB::table('users')->where('id', $user->id)->update([
                'password' => bcrypt($request->password),
                'updated_at' => now(),
            ]);

            $this->mirror->updatedFromLocal('users', $user->id);

            DB::commit();
            $this->mirror->flush();

            return redirect()->route('settings')->with('success', 'Password changed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->mirror->discard();
            Log::error('Password change failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to change your password: ' . $e->getMessage());
        }
    }

    protected function splitName(string $name, ?string $existingLastName): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        $first = $parts[0] ?? $name;

        if (count($parts) > 1) {
            return [$first, implode(' ', array_slice($parts, 1))];
        }

        // A single word is a given name — never silently blank the surname.
        return [$first, (string) $existingLastName];
    }
}

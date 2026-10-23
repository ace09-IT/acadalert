<?php

namespace App\Services;

use App\Repositories\Api\StudentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentNotificationService
{
    public const FLAG_MESSAGES = [
        'high_risk' => 'You are at High Risk. Please see your guidance counselor.',
        'consecutive_high_risk' => 'You have been at High Risk for multiple periods. Immediate attention needed.',
        'low_attendance' => 'Your attendance has dropped below 70%. Please see your instructor.',
        'failing_grade' => 'You have a failing grade in one or more subjects.',
        'attendance_warning' => 'Your attendance is approaching the warning threshold.',
        'attendance_drop' => 'Your attendance has dropped critically.',
        'counselor_update' => 'Your guidance counselor has updated your case.',
        'counselor_action' => 'Your guidance counselor has taken action on your case.',
        'status_update' => 'Your case status has been updated.',
        'priority_update' => 'Your case priority has been updated.',
        'case_resolved' => 'Your case has been resolved!',
        'case_reopened' => 'Your case has been reopened.',
    ];

    protected array $cache = [];

    public function forStudent(int $studentId): Collection
    {
        return $this->cache[$studentId] ??= $this->query($studentId);
    }

    public function forEmail(?string $email): Collection
    {
        $email = trim((string) $email);

        if ($email === '') {
            return collect();
        }

        $student = app(StudentRepository::class)->findByEmail($email);

        return $student === null ? collect() : $this->forStudent((int) $student->id);
    }

    public function unreadCount(int $studentId): int
    {
        return $this->forStudent($studentId)->where('is_acknowledged', false)->count();
    }

    protected function query(int $studentId): Collection
    {
        $flags = DB::table('flags')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        Log::debug('Retrieved student alerts', [
            'student_id' => $studentId,
            'count' => $flags->count(),
        ]);

        foreach ($flags as $flag) {
            $stored = trim((string) ($flag->message ?? ''));
            $flag->message = $stored !== ''
                ? $stored
                : (self::FLAG_MESSAGES[$flag->flag_type] ?? 'You have a new alert.');
        }

        return $flags;
    }
}
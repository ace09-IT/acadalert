<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    protected $fillable = [
        'student_id', 'subject_id', 'grading_period', 'school_year',
        'semester', 'total_required_hours', 'total_weighted_hours',
        'attendance_rate', 'total_absences', 'total_lates', 'total_excused',
        'warning_issued', 'is_dropped'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
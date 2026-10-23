<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'student_id', 'escalated_by', 'case_id', 'school_year',
        'semester', 'grading_period', 'notes', 'escalated_at',
        'intervention_recommendation_id'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }
}
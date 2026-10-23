<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flag extends Model
{
    protected $fillable = [
        'student_id', 'grading_period', 'school_year', 'semester',
        'flag_type', 'severity', 'is_acknowledged', 
        'escalated_to_counselor_at', 'consecutive_periods_count'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function acknowledgments()
    {
        return $this->hasMany(AlertAcknowledgement::class);
    }

    public function riskScore(): BelongsTo
    {
        return $this->belongsTo(RiskScore::class, 'student_id', 'student_id')
            ->where('grading_period', $this->grading_period);
    }
}
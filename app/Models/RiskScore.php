<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskScore extends Model
{
    protected $fillable = [
        'student_id', 'grading_period', 'school_year', 'semester',
        'risk_score', 'risk_level', 'risk_factors', 'scoring_method',
        'ai_response_raw', 'api_attempt_count', 'last_api_error', 'processing_status'
    ];

    protected $casts = [
        'risk_factors' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(Flag::class, 'student_id', 'student_id')
            ->where('grading_period', $this->grading_period);
    }
}
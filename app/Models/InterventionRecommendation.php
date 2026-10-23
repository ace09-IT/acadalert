<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterventionRecommendation extends Model
{
    protected $fillable = [
        'student_id', 'grading_period', 'school_year', 'risk_factors', 'suggested_actions', 'generated_at'
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'suggested_actions' => 'array',
    ];

    public function scopeForPeriod($query, string $gradingPeriod, ?string $schoolYear = null)
    {
        return $query->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear ?: '2024-2025');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function forwardedCases(): HasMany
    {
        return $this->hasMany(StudentCase::class, 'intervention_source_id');
    }
}
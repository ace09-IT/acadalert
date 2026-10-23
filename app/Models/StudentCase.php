<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCase extends Model
{
    protected $table = 'cases';
    
    protected $fillable = [
        'student_id', 'counselor_id', 'escalated_by', 'escalated_at',
        'school_year', 'semester', 'priority', 'status',
        'risk_level_at_escalation', 'risk_score_at_escalation',
        'intervention_recommendation', 'intervention_included',
        'intervention_edited', 'intervention_source_id',
        'resolved_at', 'resolved_reason', 'reopened_from_case_id'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function interventionSource(): BelongsTo
    {
        return $this->belongsTo(InterventionRecommendation::class, 'intervention_source_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(Counselor::class);
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CaseSession::class);
    }

    public function reopenedFrom(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class, 'reopened_from_case_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }
}
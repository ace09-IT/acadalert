<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskOverride extends Model
{
    protected $fillable = [
        'student_id', 'original_risk_score_id', 'overridden_by',
        'school_year', 'semester', 'grading_period',
        'original_risk_score', 'original_risk_level', 'new_risk_level', 'reason'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function originalRiskScore(): BelongsTo
    {
        return $this->belongsTo(RiskScore::class, 'original_risk_score_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
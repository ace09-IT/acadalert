<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'block_id', 'student_number', 'first_name', 'last_name', 
        'email', 'status', 'year_enrolled'
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(ParentModel::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(Flag::class);
    }

    public function interventionRecommendations(): HasMany
    {
        return $this->hasMany(InterventionRecommendation::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(StudentCase::class);
    }

    public function alertAcknowledgments(): HasMany
    {
        return $this->hasMany(AlertAcknowledgement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
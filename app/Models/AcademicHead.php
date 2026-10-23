<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicHead extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'employee_number', 'specialization'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function academicHeadAssignments(): HasMany
    {
        return $this->hasMany(AcademicHeadAssignment::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class, 'escalated_by');
    }
}

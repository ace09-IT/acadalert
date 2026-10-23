<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counselor extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'employee_number', 'specialization',
        'max_caseload', 'office_location', 'phone_number', 'office_hours'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(StudentCase::class);
    }
}
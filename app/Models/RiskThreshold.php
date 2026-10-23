<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskThreshold extends Model
{
    protected $fillable = [
        'low_threshold', 'moderate_threshold', 'high_threshold',
        'grade_weight', 'attendance_weight', 'updated_by'
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
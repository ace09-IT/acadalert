<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseSession extends Model
{
    protected $fillable = [
        'case_id', 'session_date', 'session_type', 'notes',
        'action_taken', 'follow_up_date', 'status_after_session'
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }
}
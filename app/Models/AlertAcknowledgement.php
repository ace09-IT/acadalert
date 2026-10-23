<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertAcknowledgement extends Model
{
    protected $fillable = [
        'student_id', 'flag_id', 'acknowledged_at', 'ip_address'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }
}
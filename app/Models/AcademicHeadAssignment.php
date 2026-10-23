<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicHeadAssignment extends Model
{
    protected $fillable = [
        'academic_head_id', 'block_id', 'subject_id', 'school_year', 'semester'
    ];

    public function academicHead(): BelongsTo
    {
        return $this->belongsTo(AcademicHead::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

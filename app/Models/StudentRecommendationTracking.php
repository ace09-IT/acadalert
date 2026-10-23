<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRecommendationTracking extends Model
{
    protected $table = 'student_recommendation_tracking';
    
    protected $fillable = [
        'student_id', 'recommendation_id', 'is_completed', 'completed_at'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(InterventionRecommendation::class, 'recommendation_id');
    }
}
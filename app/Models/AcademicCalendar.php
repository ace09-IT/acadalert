<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicCalendar extends Model
{
    protected $table = 'academic_calendar';
    
    protected $fillable = [
        'school_year', 'semester', 'grading_period', 'period_number',
        'start_date', 'end_date', 'risk_scoring_deadline', 'is_active'
    ];
}
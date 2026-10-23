<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentModel extends Model
{
    protected $table = 'parents';
    
    protected $fillable = [
        'student_id', 'full_name', 'relationship', 'contact_number', 
        'email', 'address', 'is_primary_contact', 'lives_with_student'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name', 'code', 'description'
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function academicHeads(): HasMany
    {
        return $this->hasMany(AcademicHead::class);
    }

    public function counselors(): HasMany
    {
        return $this->hasMany(Counselor::class);
    }
}
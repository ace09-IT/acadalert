<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    protected $fillable = [
        'name', 'is_active', 'is_archived', 'started_at', 'ended_at'
    ];
}
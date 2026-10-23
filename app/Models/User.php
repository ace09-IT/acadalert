<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAcademicHead(): bool
    {
        return $this->role === 'academic_head';
    }

    public function isCounselor(): bool
    {
        return $this->role === 'guidance_counselor';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function academicHead()
    {
        return $this->hasOne(AcademicHead::class);
    }

    public function counselor()
    {
        return $this->hasOne(Counselor::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'email', 'email');
    }
}
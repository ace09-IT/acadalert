<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        
        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@udd.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $academicHeads = [
            ['name' => 'Prof. Juan Santos', 'email' => 'mt.site@udd.edu.ph', 'department_code' => 'SITE'],
            ['name' => 'Prof. Maria Reyes', 'email' => 'mt.sba@udd.edu.ph', 'department_code' => 'SBA'],
            ['name' => 'Prof. Carlo Cruz', 'email' => 'mt.soe@udd.edu.ph', 'department_code' => 'SOE'],
            ['name' => 'Prof. Ana Garcia', 'email' => 'mt.sohs@udd.edu.ph', 'department_code' => 'SOHS'],
            ['name' => 'Prof. Mark Mendoza', 'email' => 'mt.sihm@udd.edu.ph', 'department_code' => 'SIHM'],
        ];
        
        foreach ($academicHeads as $mt) {
            DB::table('users')->insert([
                'name' => $mt['name'],
                'email' => $mt['email'],
                'password' => Hash::make('password'),
                'role' => 'academic_head',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $counselors = [
            ['name' => 'Ms. Rica Flores', 'email' => 'gc.site@udd.edu.ph', 'department_code' => 'SITE'],
            ['name' => 'Ms. Isabel Torres', 'email' => 'gc.sba@udd.edu.ph', 'department_code' => 'SBA'],
            ['name' => 'Ms. Patricia Villanueva', 'email' => 'gc.soe@udd.edu.ph', 'department_code' => 'SOE'],
            ['name' => 'Ms. Andrea Gonzales', 'email' => 'gc.sohs@udd.edu.ph', 'department_code' => 'SOHS'],
            ['name' => 'Ms. Nicole Rivera', 'email' => 'gc.sihm@udd.edu.ph', 'department_code' => 'SIHM'],
        ];
        
        foreach ($counselors as $c) {
            DB::table('users')->insert([
                'name' => $c['name'],
                'email' => $c['email'],
                'password' => Hash::make('password'),
                'role' => 'guidance_counselor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Users seeded successfully.');
    }
}
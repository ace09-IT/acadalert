<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CounselorsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('counselors')->truncate();
        
        $users = DB::table('users')->where('role', 'guidance_counselor')->get();
        
        foreach ($users as $user) {
            $departmentCode = strtoupper(explode('@', $user->email)[0]);
            $departmentCode = str_replace('GC.', '', $departmentCode);
            
            $department = DB::table('departments')->where('code', $departmentCode)->first();
            
            DB::table('counselors')->insert([
                'user_id' => $user->id,
                'department_id' => $department->id ?? null,
                'employee_number' => 'GC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                'specialization' => 'Academic Counseling',
                'max_caseload' => 30,
                'office_location' => 'Guidance Office, 2nd Floor, Main Building',
                'phone_number' => '0917' . rand(1000000, 9999999),
                'office_hours' => 'Mon-Fri, 9:00 AM - 4:00 PM',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Counselors seeded successfully.');
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ParentsTableSeeder extends Seeder
{
    private $firstNamesFemale = [
        'Maria', 'Ana', 'Rica', 'Sofia', 'Isabel', 'Patricia', 'Andrea', 'Nicole', 'Jasmine'
    ];
    
    private $firstNamesMale = [
        'Juan', 'Jose', 'Carlo', 'Mark', 'Michael', 'Christian', 'Kevin', 'Ryan'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('parents')->truncate();
        
        $students = DB::table('students')->get();
        $parentCount = 0;
        
        foreach ($students as $student) {
            $motherName = $this->firstNamesFemale[array_rand($this->firstNamesFemale)] . ' ' . $student->last_name;
            DB::table('parents')->insert([
                'student_id' => $student->id,
                'full_name' => $motherName,
                'relationship' => 'Mother',
                'contact_number' => '+639' . rand(100000000, 999999999),
                'email' => strtolower(str_replace(' ', '.', $motherName)) . '@email.com',
                'address' => 'Sample Address, Dagupan City',
                'is_primary_contact' => true,
                'lives_with_student' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $fatherName = $this->firstNamesMale[array_rand($this->firstNamesMale)] . ' ' . $student->last_name;
            DB::table('parents')->insert([
                'student_id' => $student->id,
                'full_name' => $fatherName,
                'relationship' => 'Father',
                'contact_number' => '+639' . rand(100000000, 999999999),
                'email' => strtolower(str_replace(' ', '.', $fatherName)) . '@email.com',
                'address' => 'Sample Address, Dagupan City',
                'is_primary_contact' => false,
                'lives_with_student' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $parentCount += 2;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$parentCount parents seeded successfully.");
    }
}
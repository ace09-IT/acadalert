<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SemestersTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('semesters')->truncate();
        Schema::enableForeignKeyConstraints();
        
        DB::table('semesters')->insert([
            [
                'name' => '1st Semester',
                'semester_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '2nd Semester',
                'semester_number' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        $this->command->info('Semesters seeded successfully.');
    }
}
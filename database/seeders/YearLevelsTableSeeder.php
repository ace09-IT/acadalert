<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class YearLevelsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('year_levels')->truncate();
        
        $programs = DB::table('programs')->get();
        $yearNames = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        foreach ($programs as $program) {
            for ($year = 1; $year <= 4; $year++) {
                DB::table('year_levels')->insert([
                    'program_id' => $program->id,
                    'year_number' => $year,
                    'name' => $yearNames[$year - 1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Year levels seeded successfully.');
    }
}   
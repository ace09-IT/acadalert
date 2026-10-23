<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchoolYearsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('school_years')->truncate();
        
        $csvFile = database_path('csv/school_years.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            return;
        }
        
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);
        
        foreach ($csv as $row) {
            $data = array_combine($header, $row);
            DB::table('school_years')->insert([
                'name' => $data['name'],
                'is_active' => $data['is_active'],
                'is_archived' => $data['is_archived'],
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'] ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('School years seeded successfully.');
    }
}
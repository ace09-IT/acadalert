<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicCalendarTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('academic_calendar')->truncate();
        
        $csvFile = database_path('csv/academic_calendar.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            return;
        }
        
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);
        
        foreach ($csv as $row) {
            $data = array_combine($header, $row);
            DB::table('academic_calendar')->insert([
                'school_year' => $data['school_year'],
                'semester' => $data['semester'],
                'grading_period' => $data['grading_period'],
                'period_number' => $data['period_number'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'risk_scoring_deadline' => $data['risk_scoring_deadline'],
                'is_active' => $data['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Academic calendar seeded successfully.');
    }
}
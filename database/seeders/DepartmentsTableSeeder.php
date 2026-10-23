<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartmentsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('departments')->truncate();
        
        $csvFile = database_path('csv/departments.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            return;
        }
        
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);
        
        foreach ($csv as $row) {
            $data = array_combine($header, $row);
            DB::table('departments')->insert([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Departments seeded successfully.');
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('programs')->truncate();
        
        $csvFile = database_path('csv/programs.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            return;
        }
        
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);
        
        foreach ($csv as $row) {
            $data = array_combine($header, $row);
            
            $department = DB::table('departments')->where('code', $data['department_code'])->first();
            if (!$department) {
                $this->command->error("Department not found: {$data['department_code']}");
                continue;
            }
            
            DB::table('programs')->insert([
                'department_id' => $department->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'total_students' => $data['total_students'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Programs seeded successfully.');
    }
}
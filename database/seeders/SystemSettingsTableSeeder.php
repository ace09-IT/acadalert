<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('system_settings')->truncate();
        
        $csvFile = database_path('csv/system_settings.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            return;
        }
        
        $csv = array_map('str_getcsv', file($csvFile));
        $header = array_shift($csv);
        
        foreach ($csv as $row) {
            $data = array_combine($header, $row);
            DB::table('system_settings')->insert([
                'setting_key' => $data['setting_key'],
                'setting_value' => $data['setting_value'],
                'description' => $data['description'],
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('System settings seeded successfully.');
    }
}
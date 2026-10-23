<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BlocksTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('blocks')->truncate();
        
        $yearLevels = DB::table('year_levels')->get();
        
        foreach ($yearLevels as $yearLevel) {
            for ($blockNum = 1; $blockNum <= 2; $blockNum++) {
                DB::table('blocks')->insert([
                    'year_level_id' => $yearLevel->id,
                    'block_number' => $blockNum,
                    'name' => "Block $blockNum",
                    'max_students' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Blocks seeded successfully.');
    }
}
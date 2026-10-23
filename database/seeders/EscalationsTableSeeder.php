<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EscalationsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('escalations')->truncate();
        
        $cases = DB::table('cases')->get();
        $academicHeads = DB::table('academic_heads')->get();
        
        $escCount = 0;
        
        foreach ($cases as $case) {
            $academicHead = $academicHeads->random();
            
            DB::table('escalations')->insert([
                'student_id' => $case->student_id,
                'escalated_by' => $academicHead->user_id,
                'case_id' => $case->id,
                'school_year' => '2024-2025',
                'semester' => '1st',
                'grading_period' => 'Midterm',
                'notes' => 'Student has been showing signs of academic struggle. Please provide intervention.',
                'escalated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $escCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$escCount escalations seeded successfully.");
    }
}
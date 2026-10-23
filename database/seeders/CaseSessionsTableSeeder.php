<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaseSessionsTableSeeder extends Seeder
{
    private $sessionTypes = ['In-person', 'Phone', 'Virtual', 'Parent Meeting'];
    private $sessionNotes = [
        'Student discussed academic challenges and concerns.',
        'Student showed improvement after tutoring sessions.',
        'Parent meeting scheduled to discuss student progress.',
        'Student referred to peer tutoring program.',
        'Follow-up session to monitor academic progress.'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('case_sessions')->truncate();
        
        $cases = DB::table('cases')->get();
        $sessionCount = 0;
        
        foreach ($cases as $case) {
            $numSessions = rand(1, 3);
            
            for ($i = 1; $i <= $numSessions; $i++) {
                DB::table('case_sessions')->insert([
                    'case_id' => $case->id,
                    'session_date' => now()->subDays(rand(1, 30)),
                    'session_type' => $this->sessionTypes[array_rand($this->sessionTypes)],
                    'notes' => $this->sessionNotes[array_rand($this->sessionNotes)],
                    'action_taken' => 'Counseling session conducted',
                    'follow_up_date' => now()->addDays(rand(7, 14)),
                    'status_after_session' => 'In Progress',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sessionCount++;
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$sessionCount case sessions seeded successfully.");
    }
}
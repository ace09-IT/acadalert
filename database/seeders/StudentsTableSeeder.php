<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class StudentsTableSeeder extends Seeder
{
    private $lastNames = [
        'Santos', 'Reyes', 'Cruz', 'Garcia', 'Mendoza', 'Flores', 'Villanueva', 
        'Gonzales', 'Rivera', 'Torres', 'Ramos', 'Gomez', 'Fernandez', 'Lopez', 
        'Diaz', 'Castillo', 'Ortiz', 'Soriano', 'Aquino', 'Bautista', 'Castro',
        'Concepcion', 'Dela Cruz', 'Delos Santos', 'Dimagiba', 'Espiritu', 'Estrada',
        'Guzman', 'Hernandez', 'Javier', 'Jimenez', 'Lacson', 'Lazaro', 'Lorenzo',
        'Macapagal', 'Mallari', 'Manalo', 'Marcelo', 'Mariano', 'Miguel', 'Miranda',
        'Navarro', 'Nepomuceno', 'Oliva', 'Panganiban', 'Pascual', 'Pineda', 'Quinto'
    ];
    
    private $firstNamesMale = [
        'Juan', 'Jose', 'Carlo', 'Mark', 'Michael', 'Christian', 'Kevin', 'Ryan', 
        'Jericho', 'Paulo', 'Angelo', 'Marvin', 'Eric', 'Joel', 'Raymond', 'Ronald',
        'Samuel', 'Timothy', 'Victor', 'William', 'Alvin', 'Benedict', 'Christopher',
        'Daniel', 'Edward', 'Francis', 'Gabriel', 'Henry', 'Ian', 'John'
    ];
    
    private $firstNamesFemale = [
        'Maria', 'Ana', 'Rica', 'Sofia', 'Isabel', 'Patricia', 'Andrea', 'Nicole', 
        'Camille', 'Jasmine', 'Kristine', 'Lorraine', 'Monica', 'Natasha', 'Olivia',
        'Pamela', 'Queenie', 'Rachel', 'Sarah', 'Tina', 'Ursula', 'Vanessa', 'Wendy',
        'Xenia', 'Yvonne', 'Zara', 'Angelica', 'Bernadette', 'Catherine', 'Diana'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('students')->truncate();
        DB::table('users')->where('role', 'student')->delete();
        Schema::enableForeignKeyConstraints();
        
        $blocks = DB::table('blocks')->get();
        $yearEnrolled = [2024, 2023, 2022, 2021];
        
        $studentCount = 0;
        $usedEmails = [];
        $usedStudentNumbers = [];
        
        foreach ($blocks as $index => $block) {
            $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
            $yearIndex = $yearLevel->year_number - 1;
            $enrolledYear = $yearEnrolled[$yearIndex];
            
            $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
            $blockLetter = chr(64 + $block->block_number);
            
            for ($i = 1; $i <= 20; $i++) {
                $isMale = ($i % 2 == 0);
                $firstName = $isMale 
                    ? $this->firstNamesMale[array_rand($this->firstNamesMale)]
                    : $this->firstNamesFemale[array_rand($this->firstNamesFemale)];
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                
                $baseEmail = strtolower($firstName . '.' . $lastName . '@udd.edu.ph');
                $email = $baseEmail;
                $counter = 1;
                
                while (in_array($email, $usedEmails)) {
                    $email = strtolower($firstName . '.' . $lastName . $counter . '@udd.edu.ph');
                    $counter++;
                }
                $usedEmails[] = $email;
                
                $studentNumber = sprintf("UDD-%d-%s-%d%s-%03d", 
                    $enrolledYear, 
                    $program->code, 
                    $yearLevel->year_number, 
                    $blockLetter, 
                    $i
                );
                
                $counterSN = 1;
                $originalStudentNumber = $studentNumber;
                while (in_array($studentNumber, $usedStudentNumbers)) {
                    $studentNumber = $originalStudentNumber . '-' . $counterSN;
                    $counterSN++;
                }
                $usedStudentNumbers[] = $studentNumber;
                
                DB::table('students')->insert([
                    'block_id' => $block->id,
                    'student_number' => $studentNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'status' => 'Active',
                    'year_enrolled' => $enrolledYear,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                DB::table('users')->insert([
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $studentCount++;
            }
        }
        
        $this->command->info("==================================================");
        $this->command->info(" $studentCount students seeded successfully!");
        $this->command->info(" $studentCount student user accounts created!");
        $this->command->info("==================================================");
        $this->command->info(" Login credentials:");
        $this->command->info("   Email: [student email]");
        $this->command->info("   Password: password");
        $this->command->info("==================================================");
    }
}
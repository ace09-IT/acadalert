<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DepartmentsTableSeeder::class);
        $this->call(ProgramsTableSeeder::class);
        $this->call(SemestersTableSeeder::class);
        $this->call(AcademicCalendarTableSeeder::class);
        $this->call(SchoolYearsTableSeeder::class);
        $this->call(SystemSettingsTableSeeder::class);
        
        $this->call(YearLevelsTableSeeder::class);
        $this->call(BlocksTableSeeder::class);
        $this->call(SubjectsTableSeeder::class);
        
        $this->call(UsersTableSeeder::class);
        $this->call(AcademicHeadsTableSeeder::class);
        $this->call(CounselorsTableSeeder::class);
        
        // ========== RISK THRESHOLDS (Requires admin user) ==========
        $this->call(RiskThresholdsTableSeeder::class);     // MOVED HERE - after UsersTableSeeder
        
        $this->call(StudentsTableSeeder::class);
        $this->call(ParentsTableSeeder::class);
        
        $this->call(AcademicHeadAssignmentsTableSeeder::class);
        
        $this->call(GradesTableSeeder::class);
        $this->call(AttendanceTableSeeder::class);
        $this->call(AttendanceWarningsTableSeeder::class);
        
        $this->call(RiskScoresTableSeeder::class);
        $this->call(FlagsTableSeeder::class);
        $this->call(InterventionRecommendationsTableSeeder::class);
        
        $this->call(CasesTableSeeder::class);
        $this->call(EscalationsTableSeeder::class);
        $this->call(CaseSessionsTableSeeder::class);
        
        $this->call(AlertAcknowledgmentsTableSeeder::class);
        
        $this->call(PaymentsTableSeeder::class);
        $this->call(PaymentHistoryTableSeeder::class);
        
        $this->call(AuditLogsTableSeeder::class);
        
    }
}
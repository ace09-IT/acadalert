<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the flag_type ENUM to include new values
        DB::statement("ALTER TABLE flags MODIFY COLUMN flag_type ENUM(
            'high_risk',
            'consecutive_high_risk',
            'low_attendance',
            'failing_grade',
            'attendance_warning',
            'attendance_drop',
            'counselor_update',
            'counselor_action',
            'status_update',
            'priority_update',
            'case_resolved',
            'case_reopened'
        )");
    }

    public function down(): void
    {
        // Revert to original ENUM values
        DB::statement("ALTER TABLE flags MODIFY COLUMN flag_type ENUM(
            'high_risk',
            'consecutive_high_risk',
            'low_attendance',
            'failing_grade',
            'attendance_warning',
            'attendance_drop'
        )");
    }
};
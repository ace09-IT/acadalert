<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Rename the master teachers table (only if still named master_teachers).
        //    Referencing FKs follow automatically in MySQL.
        if (Schema::hasTable('master_teachers')) {
            Schema::rename('master_teachers', 'academic_heads');
        }

        // 2) Migrate existing role values. The enum must accept 'academic_head' FIRST,
        //    otherwise MySQL rejects the value ("Data truncated for column 'role'").
        $this->includeRoleValue('users', 'academic_head');
        $this->includeRoleValue('permissions', 'academic_head');

        DB::statement("UPDATE users SET role = 'academic_head' WHERE role = 'master_teacher'");
        DB::statement("UPDATE permissions SET role = 'academic_head' WHERE role = 'master_teacher'");

        // 3) Remove the old enum value now that no rows use it.
        $this->setRoleEnum('users', ['admin', 'academic_head', 'guidance_counselor', 'student'], "NOT NULL DEFAULT 'student'");
        $this->setRoleEnum('permissions', ['admin', 'academic_head', 'guidance_counselor', 'student'], 'NOT NULL');

        Schema::table('teacher_assignments', function ($table) {
            // Drop constraints/indexes that reference the old column before renaming it.
            $table->dropUnique('teacher_assignment_unique');
            $table->dropForeign(['master_teacher_id']);
            $table->dropIndex(['master_teacher_id']);
        });

        Schema::rename('teacher_assignments', 'academic_head_assignments');

        Schema::table('academic_head_assignments', function ($table) {
            $table->renameColumn('master_teacher_id', 'academic_head_id');
        });

        // Recreate the index and foreign key with the new column/table names.
        Schema::table('academic_head_assignments', function ($table) {
            $table->foreign('academic_head_id')
                ->references('id')
                ->on('academic_heads')
                ->onDelete('cascade');
            $table->index('academic_head_id');
        });

        Schema::table('academic_head_assignments', function ($table) {
            $table->unique(
                ['academic_head_id', 'block_id', 'subject_id', 'school_year', 'semester'],
                'academic_head_assignment_unique'
            );
        });
    }

    public function down(): void
    {
        // Reverse role values and enums (add master_teacher to the enum before updating data).
        $this->includeRoleValue('users', 'master_teacher');
        $this->includeRoleValue('permissions', 'master_teacher');
        DB::statement("UPDATE users SET role = 'master_teacher' WHERE role = 'academic_head'");
        DB::statement("UPDATE permissions SET role = 'master_teacher' WHERE role = 'academic_head'");
        $this->setRoleEnum('users', ['admin', 'master_teacher', 'guidance_counselor', 'student'], "NOT NULL DEFAULT 'student'");
        $this->setRoleEnum('permissions', ['admin', 'master_teacher', 'guidance_counselor', 'student'], 'NOT NULL');

        if (Schema::hasTable('academic_head_assignments')) {
            Schema::table('academic_head_assignments', function ($table) {
                $table->dropUnique('academic_head_assignment_unique');
                $table->dropForeign(['academic_head_id']);
                $table->dropIndex(['academic_head_id']);
            });

            Schema::table('academic_head_assignments', function ($table) {
                $table->renameColumn('academic_head_id', 'master_teacher_id');
            });

            Schema::rename('academic_head_assignments', 'teacher_assignments');

            Schema::table('teacher_assignments', function ($table) {
                $table->foreign('master_teacher_id')
                    ->references('id')
                    ->on('academic_heads')
                    ->onDelete('cascade');
                $table->index('master_teacher_id');
            });

            Schema::table('teacher_assignments', function ($table) {
                $table->unique(
                    ['master_teacher_id', 'block_id', 'subject_id', 'school_year', 'semester'],
                    'teacher_assignment_unique'
                );
            });
        }

        if (Schema::hasTable('academic_heads') && !Schema::hasTable('master_teachers')) {
            Schema::rename('academic_heads', 'master_teachers');
        }
    }

    protected function includeRoleValue(string $table, string $value): void
    {
        $type = $this->roleType($table);
        if (strpos($type, $value) !== false) {
            return;
        }

        $current = $this->roleEnumValues($type);
        if (!in_array($value, $current, true)) {
            $current[] = $value;
        }

        $extra = $table === 'users' ? "NOT NULL DEFAULT 'student'" : 'NOT NULL';
        $this->setRoleEnum($table, $current, $extra);
    }

    protected function setRoleEnum(string $table, array $values, string $extra): void
    {
        $list = implode("', '", $values);
        DB::statement("ALTER TABLE {$table} MODIFY role ENUM('{$list}') {$extra}");
    }

    protected function roleType(string $table): string
    {
        $rows = DB::select("SHOW COLUMNS FROM {$table} LIKE 'role'");
        if (empty($rows)) {
            return '';
        }
        return $rows[0]->Type ?? '';
    }

    protected function roleEnumValues(string $type): array
    {
        preg_match_all("/'([^']*)'/", $type, $matches);
        return $matches[1] ?? [];
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flags') || Schema::hasColumn('flags', 'message')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            $table->text('message')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('flags') || !Schema::hasColumn('flags', 'message')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }
};
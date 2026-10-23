<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // The exact text forwarded to the counselor (edited version when the
            // Academic Head edited it before sending).
            $table->text('intervention_recommendation')->nullable()->after('risk_score_at_escalation');
            $table->boolean('intervention_included')->default(false)->after('intervention_recommendation');
            $table->boolean('intervention_edited')->default(false)->after('intervention_included');
            $table->foreignId('intervention_source_id')
                ->nullable()
                ->after('intervention_edited')
                ->constrained('intervention_recommendations')
                ->nullOnDelete();

            $table->index('intervention_included');
        });

        Schema::table('escalations', function (Blueprint $table) {
            $table->foreignId('intervention_recommendation_id')
                ->nullable()
                ->after('case_id')
                ->constrained('intervention_recommendations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intervention_recommendation_id');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['intervention_included']);
            $table->dropConstrainedForeignId('intervention_source_id');
            $table->dropColumn([
                'intervention_recommendation',
                'intervention_included',
                'intervention_edited',
            ]);
        });
    }
};

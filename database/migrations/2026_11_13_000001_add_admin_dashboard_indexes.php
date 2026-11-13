<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive indexes for the admin dashboard aggregates (T3.2).
 * Reversible: dropping the indexes leaves the data untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            // KPI windows group by created_at and status; status+updated_at already
            // exists (2026_09_17_000002) for the stale-submission watchdog.
            $table->index(['created_at', 'status'], 'submissions_created_at_status_index');
        });

        Schema::table('submission_processing_events', function (Blueprint $table): void {
            // Failures-per-stage and duration aggregates group by stage/outcome over a period.
            $table->index(['stage', 'outcome', 'created_at'], 'processing_events_stage_outcome_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropIndex('submissions_created_at_status_index');
        });

        Schema::table('submission_processing_events', function (Blueprint $table): void {
            $table->dropIndex('processing_events_stage_outcome_created_index');
        });
    }
};

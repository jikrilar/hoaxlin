<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('processing_stage')->default('queued')->after('status');
            $table->string('content_hash', 64)->nullable()->after('extracted_text')->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->string('last_error_service')->nullable();
            $table->string('last_error_code')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('pipeline_version')->default('1.0');
        });

        Schema::table('detection_results', function (Blueprint $table) {
            $table->json('raw_scores')->nullable();
            $table->unsignedInteger('inference_ms')->default(0);
            $table->boolean('classifier_cached')->default(false);
            $table->string('explanation_status')->default('pending');
            $table->string('explanation_model')->nullable();
            $table->boolean('explanation_cached')->default(false);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
        });

        Schema::create('submission_processing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('stage')->index();
            $table->string('outcome')->index();
            $table->string('service')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_processing_events');

        Schema::table('detection_results', function (Blueprint $table) {
            $table->dropColumn([
                'raw_scores',
                'inference_ms',
                'classifier_cached',
                'explanation_status',
                'explanation_model',
                'explanation_cached',
                'prompt_tokens',
                'completion_tokens',
                'estimated_cost_usd',
            ]);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropColumn([
                'processing_stage',
                'content_hash',
                'processing_started_at',
                'processing_completed_at',
                'last_error_service',
                'last_error_code',
                'attempt_count',
                'pipeline_version',
            ]);
        });
    }
};

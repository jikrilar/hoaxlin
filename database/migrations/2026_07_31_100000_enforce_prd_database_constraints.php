<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('detection_results', function (Blueprint $table) {
            $table->dropIndex(['label']);
            $table->unique('submission_id');
            $table->index(['label', 'created_at']);
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['submission_id', 'user_id']);
        });

        Schema::table('datasets', function (Blueprint $table) {
            $table->dropIndex(['label']);
            $table->string('source')->nullable(false)->change();
            $table->index(['label', 'created_at']);
        });

        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['target_table', 'target_id']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropIndex(['admin_id', 'created_at']);
            $table->dropIndex(['target_table', 'target_id']);
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('datasets', function (Blueprint $table) {
            $table->dropIndex(['label', 'created_at']);
            $table->index('label');
            $table->string('source')->nullable()->change();
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['submission_id', 'user_id']);
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('detection_results', function (Blueprint $table) {
            $table->dropIndex(['label', 'created_at']);
            $table->index('label');
            $table->dropForeign(['submission_id']);
            $table->dropUnique(['submission_id']);
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->index('status');
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};

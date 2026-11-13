<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review trail for feedback (T3.7).
 *
 * Additive and reversible: existing rows keep working with both columns null,
 * which reads as "belum ditinjau". Deleting an admin nulls the reference instead
 * of deleting the feedback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('comment');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};

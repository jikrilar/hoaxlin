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
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->float('baseline_accuracy')->nullable()->after('f1_score');
            $table->float('baseline_f1_score')->nullable()->after('baseline_accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->dropColumn(['baseline_accuracy', 'baseline_f1_score']);
        });
    }
};

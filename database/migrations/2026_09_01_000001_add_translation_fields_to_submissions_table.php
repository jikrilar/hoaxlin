<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('source_language', 16)->nullable()->after('extracted_text');
            $table->longText('translated_text')->nullable()->after('source_language');
            $table->string('translation_provider', 32)->nullable()->after('translated_text');
            $table->string('translation_model')->nullable()->after('translation_provider');
            $table->boolean('translation_cached')->default(false)->after('translation_model');
            $table->unsignedInteger('translation_input_tokens')->default(0)->after('translation_cached');
            $table->unsignedInteger('translation_output_tokens')->default(0)->after('translation_input_tokens');
            $table->decimal('translation_estimated_cost_usd', 12, 6)->default(0)->after('translation_output_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn([
                'source_language',
                'translated_text',
                'translation_provider',
                'translation_model',
                'translation_cached',
                'translation_input_tokens',
                'translation_output_tokens',
                'translation_estimated_cost_usd',
            ]);
        });
    }
};

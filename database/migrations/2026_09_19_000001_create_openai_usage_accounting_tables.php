<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openai_usage_periods', function (Blueprint $table): void {
            $table->string('period', 7)->primary();
            $table->unsignedBigInteger('spent_microusd')->default(0);
            $table->unsignedBigInteger('reserved_microusd')->default(0);
            $table->timestamps();
        });

        Schema::create('openai_usage_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('period', 7)->index();
            $table->string('operation', 32);
            $table->unsignedBigInteger('reserved_microusd');
            $table->unsignedBigInteger('actual_microusd')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('status', 16)->index();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('period')
                ->references('period')
                ->on('openai_usage_periods')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openai_usage_reservations');
        Schema::dropIfExists('openai_usage_periods');
    }
};

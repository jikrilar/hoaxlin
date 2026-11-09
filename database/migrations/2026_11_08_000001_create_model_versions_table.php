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
        Schema::create('model_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version_name')->unique();
            $table->string('dataset_path')->nullable();
            $table->integer('total_rows')->default(0);
            $table->enum('status', ['pending', 'training', 'completed', 'failed'])
                ->default('pending')
                ->index();
            $table->float('accuracy')->nullable();
            $table->float('f1_score')->nullable();
            $table->integer('training_duration')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};

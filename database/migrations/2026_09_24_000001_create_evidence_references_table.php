<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('document_id');
            $table->string('title');
            $table->string('source');
            $table->string('source_url', 2048);
            $table->date('published_at')->nullable();
            $table->decimal('similarity_score', 9, 8);
            $table->unsignedInteger('rank');
            $table->text('snippet');
            $table->string('knowledge_base_version')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_references');
    }
};

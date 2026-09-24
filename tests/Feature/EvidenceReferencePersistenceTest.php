<?php

namespace Tests\Feature;

use App\DataObjects\RetrievedEvidence;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Services\Rag\EvidenceReferencePersister;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvidenceReferencePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_the_expected_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('evidence_references'));
        $this->assertSame([
            'id',
            'submission_id',
            'document_id',
            'title',
            'source',
            'source_url',
            'published_at',
            'similarity_score',
            'rank',
            'snippet',
            'knowledge_base_version',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('evidence_references'));
        $this->assertFalse(Schema::hasColumn('evidence_references', 'confidence_score'));
    }

    public function test_model_maps_all_retrieved_evidence_fields_for_persistence(): void
    {
        $submission = $this->submission();
        $evidence = $this->evidence(
            publishedAt: '2026-09-01',
            sourceUrl: 'https://source.example/'.str_repeat('a', 400),
            score: -0.87654321,
            rank: 2,
        );

        $reference = EvidenceReference::makeFromRetrievedEvidence($submission, $evidence, 'knowledge-base-v1');
        $reference->save();
        $reference->refresh();

        $this->assertSame($submission->id, $reference->submission_id);
        $this->assertSame('doc-001', $reference->document_id);
        $this->assertSame('Judul bukti', $reference->title);
        $this->assertSame('Sumber resmi', $reference->source);
        $this->assertSame($evidence->sourceUrl, $reference->source_url);
        $this->assertSame('2026-09-01', $reference->published_at->format('Y-m-d'));
        $this->assertSame('-0.87654321', $reference->similarity_score);
        $this->assertSame(2, $reference->rank);
        $this->assertSame('Kutipan relevan dari dokumen.', $reference->snippet);
        $this->assertSame('knowledge-base-v1', $reference->knowledge_base_version);
        $this->assertTrue($submission->evidenceReferences()->whereKey($reference->id)->exists());
        $this->assertSame($submission->id, $reference->submission->id);
    }

    public function test_published_at_can_be_null(): void
    {
        $submission = $this->submission();
        $reference = EvidenceReference::makeFromRetrievedEvidence(
            $submission,
            $this->evidence(publishedAt: null),
        );
        $reference->save();

        $this->assertNull($reference->fresh()->published_at);
    }

    public function test_submission_has_many_evidence_and_document_can_belong_to_another_submission(): void
    {
        $firstSubmission = $this->submission();
        $secondSubmission = $this->submission();

        EvidenceReference::makeFromRetrievedEvidence($firstSubmission, $this->evidence())->save();
        EvidenceReference::makeFromRetrievedEvidence(
            $firstSubmission,
            $this->evidence(documentId: 'doc-002', rank: 2),
        )->save();
        EvidenceReference::makeFromRetrievedEvidence($secondSubmission, $this->evidence())->save();

        $this->assertCount(2, $firstSubmission->evidenceReferences);
        $this->assertCount(1, $secondSubmission->evidenceReferences);
        $this->assertSame(3, EvidenceReference::count());
    }

    public function test_database_rejects_duplicate_submission_and_document_pair(): void
    {
        $submission = $this->submission();
        EvidenceReference::makeFromRetrievedEvidence($submission, $this->evidence())->save();

        try {
            EvidenceReference::makeFromRetrievedEvidence($submission, $this->evidence())->save();
            $this->fail('Expected the compound unique constraint to reject a duplicate.');
        } catch (QueryException) {
            $this->assertSame(1, EvidenceReference::count());
        }
    }

    public function test_persistence_is_idempotent_for_repeated_evidence(): void
    {
        $submission = $this->submission();
        $persister = app(EvidenceReferencePersister::class);
        $evidence = $this->evidence(score: 0.12345678);

        $persister->persist($submission, [$evidence]);
        $persister->persist($submission, [$evidence]);

        $this->assertSame(1, $submission->evidenceReferences()->count());
        $this->assertDatabaseHas('evidence_references', [
            'submission_id' => $submission->id,
            'document_id' => 'doc-001',
            'similarity_score' => '0.12345678',
        ]);
    }

    public function test_persistence_updates_existing_evidence_metadata_and_similarity(): void
    {
        $submission = $this->submission();
        $persister = app(EvidenceReferencePersister::class);
        $persister->persist($submission, [$this->evidence()], 'knowledge-base-v1');

        $persister->persist($submission, [$this->evidence(
            title: 'Judul bukti diperbarui',
            source: 'Sumber resmi terbaru',
            sourceUrl: 'https://source.example/updated',
            publishedAt: '2026-09-20',
            score: -0.7654321,
            rank: 3,
            snippet: 'Kutipan terbaru dari dokumen.',
        )], 'knowledge-base-v2');

        $this->assertSame(1, $submission->evidenceReferences()->count());
        $reference = $submission->evidenceReferences()->sole();
        $this->assertSame('Judul bukti diperbarui', $reference->title);
        $this->assertSame('Sumber resmi terbaru', $reference->source);
        $this->assertSame('https://source.example/updated', $reference->source_url);
        $this->assertSame('2026-09-20', $reference->published_at->format('Y-m-d'));
        $this->assertSame('-0.76543210', $reference->similarity_score);
        $this->assertSame(3, $reference->rank);
        $this->assertSame('Kutipan terbaru dari dokumen.', $reference->snippet);
        $this->assertSame('knowledge-base-v2', $reference->knowledge_base_version);
    }

    public function test_submission_deletion_cascades_to_its_evidence_references(): void
    {
        $submission = $this->submission();
        app(EvidenceReferencePersister::class)
            ->persist($submission, [$this->evidence(), $this->evidence(documentId: 'doc-002', rank: 2)]);

        $this->assertSame(2, EvidenceReference::count());

        $submission->delete();

        $this->assertSame(0, EvidenceReference::count());
    }

    public function test_migration_can_be_rolled_back_and_applied_again(): void
    {
        $migration = require database_path('migrations/2026_09_24_000001_create_evidence_references_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('evidence_references'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('evidence_references'));
    }

    private function submission(): Submission
    {
        return Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Teks submission yang digunakan untuk test persistence evidence.',
            'status' => 'pending',
        ]);
    }

    private function evidence(
        string $documentId = 'doc-001',
        string $title = 'Judul bukti',
        string $source = 'Sumber resmi',
        string $sourceUrl = 'https://source.example/reference',
        ?string $publishedAt = '2026-09-10',
        float $score = 0.82,
        int $rank = 1,
        string $snippet = 'Kutipan relevan dari dokumen.',
    ): RetrievedEvidence {
        return new RetrievedEvidence(
            documentId: $documentId,
            title: $title,
            source: $source,
            sourceUrl: $sourceUrl,
            publishedAt: $publishedAt,
            snippet: $snippet,
            score: $score,
            rank: $rank,
        );
    }
}

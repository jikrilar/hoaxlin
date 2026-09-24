<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Contracts\EvidenceRetriever;
use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\DataObjects\RetrievedEvidence;
use App\Enums\DetectionLabel;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Jobs\ClassifySubmission;
use App\Jobs\GenerateSubmissionExplanation;
use App\Jobs\ProcessSubmission;
use App\Jobs\RetrieveSubmissionEvidence;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Models\User;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Extraction\TextInputExtractor;
use App\Services\Fakes\FakeClassifier;
use App\Services\Fakes\FakeEvidenceRetriever;
use App\Services\Fakes\FakeExplainer;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Rag\EvidenceReferencePersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetrieveSubmissionEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private FakeEvidenceRetriever $retriever;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retriever = new FakeEvidenceRetriever;
        $this->app->instance(EvidenceRetriever::class, $this->retriever);
        $this->app->instance(Classifier::class, $this->classifier(DetectionLabel::Hoax, 0.93));
        $this->app->instance(Explainer::class, (new FakeExplainer)->willReturn(
            Explanation::ready('Penjelasan hasil pipeline.', 'openai-test'),
        ));
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));
    }

    public function test_normal_pipeline_classifies_retrieves_persists_then_explains(): void
    {
        $this->retriever->willReturn([$this->evidence()]);
        $submission = $this->submission();

        ProcessSubmission::dispatchSync($submission);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('hoax', $submission->detectionResult->label);
        $this->assertSame(1, $submission->evidenceReferences()->count());
        $this->assertSame([(string) $submission->analysis_text], $this->retriever->queries());

        $events = $submission->processingEvents()->orderBy('id')->get();
        $retrievalIndex = $events->search(fn ($event): bool => $event->stage === ProcessingStage::Retrieving && $event->outcome === EventOutcome::Succeeded
        );
        $explanationIndex = $events->search(fn ($event): bool => $event->stage === ProcessingStage::Explaining && $event->outcome === EventOutcome::Started
        );

        $this->assertNotFalse($retrievalIndex);
        $this->assertNotFalse($explanationIndex);
        $this->assertLessThan($explanationIndex, $retrievalIndex);
    }

    public function test_empty_retrieval_continues_to_explanation_without_placeholder_evidence(): void
    {
        $submission = $this->submission();

        ProcessSubmission::dispatchSync($submission);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('Penjelasan hasil pipeline.', $submission->detectionResult->explanation);
        $this->assertSame(0, $submission->evidenceReferences()->count());
        $this->assertSame(1, $this->retriever->timesCalled());
    }

    public function test_rag_failure_keeps_classifier_result_and_public_status_sanitized(): void
    {
        $secret = 'rag-token-secret C:\\private\\rag\\index.bin';
        $this->retriever->willThrow(AiServiceException::transient('rag', $secret, 503));
        $user = User::factory()->create();
        $submission = $this->submission(['user_id' => $user->id]);

        ProcessSubmission::dispatchSync($submission);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('hoax', $submission->detectionResult->label);
        $this->assertSame(0.93, (float) $submission->detectionResult->confidence_score);
        $this->assertNull($submission->failure_reason);
        $this->assertNull($submission->last_error_service);
        $this->assertSame(0, $submission->evidenceReferences()->count());

        $retrievalEvent = $submission->processingEvents()
            ->where('stage', ProcessingStage::Retrieving->value)
            ->where('outcome', EventOutcome::Degraded->value)
            ->sole();
        $this->assertSame('rag', $retrievalEvent->service);
        $this->assertStringNotContainsString($secret, json_encode($retrievalEvent->metadata, JSON_THROW_ON_ERROR));

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('label', 'hoax')
            ->assertJsonPath('failure_reason', null)
            ->assertDontSee($secret, false);
    }

    public function test_retrying_retrieval_upserts_evidence_and_job_uses_retrieval_queue(): void
    {
        Queue::fake();
        $submission = $this->submission([
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
            'extracted_text' => 'Teks yang telah berhasil diklasifikasikan untuk pencarian bukti.',
        ]);
        $submission->detectionResult()->create([
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'test-model',
            'explanation_status' => 'pending',
        ]);
        $job = new RetrieveSubmissionEvidence($submission->id);
        $this->assertSame('retrieval', $job->queue);

        $this->retriever->willReturn([$this->evidence()]);
        $job->handle(
            $this->retriever,
            app(EvidenceReferencePersister::class),
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
            app(PipelineFailureReporter::class),
        );

        $this->retriever->willReturn([$this->evidence(title: 'Judul metadata terbaru', score: 0.72)]);
        $job->handle(
            $this->retriever,
            app(EvidenceReferencePersister::class),
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
            app(PipelineFailureReporter::class),
        );

        $this->assertSame(1, EvidenceReference::where('submission_id', $submission->id)->count());
        $this->assertSame('Judul metadata terbaru', $submission->evidenceReferences()->sole()->title);
        $this->assertSame('0.72000000', $submission->evidenceReferences()->sole()->similarity_score);
        $this->assertSame(2, $this->retriever->timesCalled());
    }

    public function test_classification_dispatches_retrieval_on_its_queue_before_explanation(): void
    {
        Queue::fake();
        $submission = $this->submission([
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
            'extracted_text' => 'Teks Indonesia dengan panjang cukup untuk classifier dan retrieval.',
        ]);

        (new ClassifySubmission($submission->id))->handle(
            app(Classifier::class),
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
            app(PipelineFailureReporter::class),
        );

        $retrievalJob = Queue::pushed(RetrieveSubmissionEvidence::class)->first();
        $this->assertInstanceOf(RetrieveSubmissionEvidence::class, $retrievalJob);
        $this->assertSame('retrieval', $retrievalJob->queue);
        $this->assertSame($submission->id, $retrievalJob->submissionId);
        Queue::assertNotPushed(GenerateSubmissionExplanation::class);
        $this->assertDatabaseHas('detection_results', [
            'submission_id' => $submission->id,
            'label' => 'hoax',
        ]);
    }

    public function test_retrieving_stage_transitions_and_progress_are_consistent(): void
    {
        $this->assertSame(ProcessingStage::Retrieving, ProcessingStage::Classifying->next());
        $this->assertSame(ProcessingStage::Explaining, ProcessingStage::Retrieving->next());
        $this->assertSame('Pencarian bukti', ProcessingStage::Retrieving->label());
        $this->assertSame(75, ProcessingStage::Retrieving->progressPercentage());
    }

    /** @param array<string, mixed> $overrides */
    private function submission(array $overrides = []): Submission
    {
        return Submission::create(array_merge([
            'input_type' => 'text',
            'raw_input' => 'Klaim publik ini memerlukan pemeriksaan dari sumber resmi dan dokumen yang dapat diverifikasi.',
            'status' => 'pending',
        ], $overrides));
    }

    private function classifier(DetectionLabel $label, float $score): FakeClassifier
    {
        return (new FakeClassifier)->willReturn(new Classification(
            $label,
            $score,
            'indobert-test-v1',
            ['valid' => $label === DetectionLabel::Valid ? $score : 0.05, 'hoax' => $label === DetectionLabel::Hoax ? $score : 0.05],
            9,
        ));
    }

    private function evidence(string $title = 'Judul bukti', float $score = 0.83): RetrievedEvidence
    {
        return new RetrievedEvidence(
            documentId: 'doc-r6-test',
            title: $title,
            source: 'Sumber terverifikasi',
            sourceUrl: 'https://source.example/evidence',
            publishedAt: '2026-09-01',
            snippet: 'Kutipan singkat untuk menguji provenance evidence.',
            score: $score,
            rank: 1,
        );
    }
}

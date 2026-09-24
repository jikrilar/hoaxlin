<?php

namespace Tests\Feature;

use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\DataObjects\RetrievedEvidence;
use App\Enums\DetectionLabel;
use App\Enums\ProcessingStage;
use App\Jobs\GenerateSubmissionExplanation;
use App\Models\DetectionResult;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Services\Fakes\FakeExplainer;
use App\Services\OpenAI\OpenAiExplainer;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Rag\EvidenceReferencePersister;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroundedExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.openai.key', 'sk-test-not-secret');
        config()->set('services.openai.chat_model', 'gpt-test');
        config()->set('services.openai.rate_limit_per_minute', 100);
        config()->set('services.openai.monthly_quota_usd', 100.0);
        config()->set('services.openai.breaker.failures', 5);
    }

    public function test_job_passes_persisted_evidence_to_explainer_and_preserves_classifier_result(): void
    {
        $submission = $this->submission();
        $first = $this->evidence('doc-002', 'Sumber Dua', 2, 0.72);
        $second = $this->evidence('doc-001', 'Sumber Satu', 1, 0.91);
        app(EvidenceReferencePersister::class)->persist($submission, [$first, $second], 'knowledge-base-v1');
        $explainer = (new FakeExplainer)->willReturn(Explanation::ready('Penjelasan dengan konteks.', 'fake-v1'));

        $this->runExplanation($submission, $explainer);

        $this->assertCount(1, $explainer->calls());
        $call = $explainer->calls()[0];
        $this->assertSame([
            $this->evidenceArray('doc-001', 'Sumber Satu', 1, 0.91),
            $this->evidenceArray('doc-002', 'Sumber Dua', 2, 0.72),
        ], $call['evidence']);

        $result = $submission->fresh()->detectionResult;
        $this->assertSame('hoax', $result->label);
        $this->assertSame('0.9100', $result->confidence_score);
        $this->assertSame('indobert-test-v1', $result->model_version);
        $this->assertSame(['valid' => 0.09, 'hoax' => 0.91], $result->raw_scores);
        $this->assertSame('completed', $submission->fresh()->status);
    }

    public function test_job_calls_explainer_with_empty_evidence_and_completes(): void
    {
        $submission = $this->submission();
        $explainer = (new FakeExplainer)->willReturn(Explanation::ready('Penjelasan tanpa evidence.', 'fake-v1'));

        $this->runExplanation($submission, $explainer);

        $this->assertSame([], $explainer->calls()[0]['evidence']);
        $this->assertSame(0, EvidenceReference::count());
        $this->assertSame('completed', $submission->fresh()->status);
        $this->assertSame('ready', $submission->fresh()->detectionResult->explanation_status);
    }

    public function test_openai_request_contains_classification_excerpt_and_structured_provenance(): void
    {
        Http::fake(['*' => Http::response($this->providerResponse())]);
        $classification = $this->classification();
        $evidence = $this->evidenceArray('doc-verified-1', 'Judul rujukan', 1, 0.84);

        $result = $this->explainer()->explain($classification, 'Kutipan yang diklasifikasikan.', [$evidence]);

        $this->assertTrue($result->isReady());
        Http::assertSent(function (Request $request) use ($evidence): bool {
            $messages = $request->data()['messages'];
            $context = json_decode($messages[1]['content'], true, flags: JSON_THROW_ON_ERROR);
            $systemPrompt = $messages[0]['content'];

            $this->assertSame([
                'label' => 'hoax',
                'confidence_score' => 0.91,
                'model_version' => 'indobert-test-v1',
            ], $context['classification']);
            $this->assertSame('Kutipan yang diklasifikasikan.', $context['excerpt']);
            $this->assertSame([$evidence], $context['evidence']);
            $this->assertStringContainsString('jangan mengubah atau melakukan re-classification', $systemPrompt);
            $this->assertStringContainsString('Jangan menciptakan sumber, URL, judul sumber, tanggal publikasi', $systemPrompt);
            $this->assertStringContainsString('similarity_score hanya menunjukkan relevansi hasil retrieval', $systemPrompt);
            $this->assertStringContainsString('bukan confidence classifier, ukuran kebenaran', $systemPrompt);

            return true;
        });
    }

    public function test_empty_evidence_is_sent_as_empty_and_prompt_requires_disclosure(): void
    {
        Http::fake(['*' => Http::response($this->providerResponse())]);

        $this->explainer()->explain($this->classification(), 'Kutipan tanpa evidence.', []);

        Http::assertSent(function (Request $request): bool {
            $messages = $request->data()['messages'];
            $context = json_decode($messages[1]['content'], true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame([], $context['evidence']);
            $this->assertStringContainsString(
                'nyatakan dengan jelas bahwa tidak tersedia evidence yang relevan pada basis pengetahuan saat ini',
                $messages[0]['content'],
            );
            $this->assertStringContainsString('Jangan membuat daftar referensi fiktif', $messages[0]['content']);

            return true;
        });
    }

    public function test_cache_key_changes_with_evidence_and_is_order_independent(): void
    {
        Http::fake(['*' => Http::response($this->providerResponse())]);
        $classification = $this->classification();
        $firstEvidence = $this->evidenceArray('doc-001', 'Satu', 1, 0.91);
        $secondEvidence = $this->evidenceArray('doc-002', 'Dua', 2, 0.73);
        $explainer = $this->explainer();
        $excerpt = 'Kutipan tetap untuk menguji cache.';

        $first = $explainer->explain($classification, $excerpt, [$firstEvidence, $secondEvidence]);
        $sameSet = $explainer->explain($classification, $excerpt, [$secondEvidence, $firstEvidence]);
        $changedEvidence = $firstEvidence;
        $changedEvidence['knowledge_base_version'] = 'knowledge-base-v2';
        $changed = $explainer->explain($classification, $excerpt, [$changedEvidence, $secondEvidence]);
        $sameChangedSet = $explainer->explain($classification, $excerpt, [$changedEvidence, $secondEvidence]);

        $this->assertFalse($first->cached);
        $this->assertTrue($sameSet->cached);
        $this->assertFalse($changed->cached);
        $this->assertTrue($sameChangedSet->cached);
        Http::assertSentCount(2);
    }

    public function test_openai_failure_degrades_while_classifier_and_evidence_remain_unchanged(): void
    {
        Http::fake(['*' => Http::response(['error' => 'provider unavailable'], 503)]);
        $submission = $this->submission();
        app(EvidenceReferencePersister::class)->persist(
            $submission,
            [$this->evidence('doc-preserved', 'Sumber tetap', 1, 0.88)],
            'knowledge-base-v1',
        );

        $this->runExplanation($submission, $this->explainer());

        $submission->refresh()->load('detectionResult', 'evidenceReferences');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('unavailable', $submission->detectionResult->explanation_status);
        $this->assertNull($submission->detectionResult->explanation);
        $this->assertSame('hoax', $submission->detectionResult->label);
        $this->assertSame('0.9100', $submission->detectionResult->confidence_score);
        $this->assertSame('indobert-test-v1', $submission->detectionResult->model_version);
        $this->assertSame(1, $submission->evidenceReferences->count());
    }

    private function runExplanation(Submission $submission, FakeExplainer|OpenAiExplainer $explainer): void
    {
        (new GenerateSubmissionExplanation($submission->id))->handle(
            $explainer,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );
    }

    private function submission(): Submission
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Klaim yang telah dinilai classifier dan akan diberi penjelasan.',
            'extracted_text' => 'Klaim yang telah dinilai classifier dan akan diberi penjelasan.',
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Explaining->value,
        ]);
        DetectionResult::create([
            'submission_id' => $submission->id,
            'label' => 'hoax',
            'confidence_score' => 0.91,
            'model_version' => 'indobert-test-v1',
            'raw_scores' => ['valid' => 0.09, 'hoax' => 0.91],
            'inference_ms' => 8,
            'classifier_cached' => false,
            'explanation_status' => 'pending',
        ]);

        return $submission->load('detectionResult');
    }

    private function evidence(string $documentId, string $title, int $rank, float $score): RetrievedEvidence
    {
        return new RetrievedEvidence(
            documentId: $documentId,
            title: $title,
            source: 'Sumber terverifikasi',
            sourceUrl: 'https://source.example/'.$documentId,
            publishedAt: '2026-09-10',
            snippet: 'Kutipan sumber untuk '.$documentId,
            score: $score,
            rank: $rank,
        );
    }

    /** @return array<string, string|float|int|null> */
    private function evidenceArray(string $documentId, string $title, int $rank, float $score): array
    {
        return [
            'document_id' => $documentId,
            'title' => $title,
            'source' => 'Sumber terverifikasi',
            'source_url' => 'https://source.example/'.$documentId,
            'published_at' => '2026-09-10',
            'snippet' => 'Kutipan sumber untuk '.$documentId,
            'similarity_score' => $score,
            'rank' => $rank,
            'knowledge_base_version' => 'knowledge-base-v1',
        ];
    }

    private function classification(): Classification
    {
        return new Classification(
            DetectionLabel::Hoax,
            0.91,
            'indobert-test-v1',
            ['valid' => 0.09, 'hoax' => 0.91],
            8,
        );
    }

    private function explainer(): OpenAiExplainer
    {
        return new OpenAiExplainer(new CircuitBreaker('openai'), new OpenAiQuota);
    }

    /** @return array<string, mixed> */
    private function providerResponse(): array
    {
        return [
            'choices' => [['message' => ['content' => 'Penjelasan netral berdasarkan konteks yang diberikan.']]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20],
        ];
    }
}

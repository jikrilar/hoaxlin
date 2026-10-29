<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Contracts\EvidenceRetriever;
use App\Contracts\TextExtractor;
use App\Contracts\Translator;
use App\DataObjects\Classification;
use App\DataObjects\ExtractedText;
use App\DataObjects\Translation;
use App\Enums\DetectionLabel;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Jobs\RetrieveSubmissionEvidence;
use App\Jobs\TranslateSubmissionText;
use App\Models\Submission;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Fakes\FakeEvidenceRetriever;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InputTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extraction_translation_classifier_and_query_have_private_exact_fingerprints(): void
    {
        Queue::fake();
        $original = 'Dr. Ayu TIDAK menerima Rp 50 juta pada 29 Oktober 2026; dosis 5 mg.';
        // Explicit fictional adapter outputs, not ground truth or real OCR/translation.
        foreach (['text', 'url', 'image', 'video', 'video_url'] as $inputType) {
            $submission = Submission::create(['input_type' => $inputType, 'raw_input' => $original, 'status' => 'pending']);
            $extracted = new ExtractedText($original, InputType::from($inputType === 'video_url' ? 'video' : $inputType), 'fixture-extractor');
            $resolver = $this->createMock(TextExtractorResolver::class);
            $extractor = new class($extracted) implements TextExtractor
            {
                public function __construct(private ExtractedText $output) {}

                public function supports(Submission $submission): bool
                {
                    return true;
                }

                public function extract(Submission $submission): ExtractedText
                {
                    return $this->output;
                }
            };
            $resolver->method('resolve')->willReturn($extractor);
            $this->app->instance(TextExtractorResolver::class, $resolver);
            $this->app->call([(new ExtractSubmissionText($submission->id)), 'handle']);
            $normalized = TextNormalizer::normalize($original);
            $translation = Translation::notRequired($normalized, 'id');
            $translator = $this->createMock(Translator::class);
            $translator->expects($this->once())->method('translate')->with($normalized)->willReturn($translation);
            $this->app->instance(Translator::class, $translator);
            $this->app->call([(new TranslateSubmissionText($submission->id)), 'handle']);
            $classifier = $this->createMock(Classifier::class);
            $classifier->expects($this->once())->method('classify')->with($normalized)
                ->willReturn(new Classification(DetectionLabel::Hoax, 0.93, 'fixture-model'));
            $this->app->instance(Classifier::class, $classifier);
            $this->app->call([(new ClassifySubmission($submission->id)), 'handle']);
            $retriever = new FakeEvidenceRetriever;
            $this->app->instance(EvidenceRetriever::class, $retriever);
            $this->app->call([(new RetrieveSubmissionEvidence($submission->id)), 'handle']);

            $submission->refresh();
            $events = $submission->processingEvents()->where('outcome', 'succeeded')->get()->keyBy(fn ($e) => $e->stage->value);
            $extract = $events['extracting']->metadata['input_trace'] ?? [];
            $this->assertSame(hash('sha256', $original), $extract['raw_input']['sha256'] ?? null);
            $this->assertSame(hash('sha256', $original), $extract['extractor_output']['sha256'] ?? null);
            $this->assertSame(hash('sha256', $normalized), $extract['extracted_text']['sha256'] ?? null);
            $this->assertSame('text-normalizer:1.0', $extract['extracted_text']['transform_version']);
            $this->assertTrue($extract['extracted_text']['changed_from_parent']);
            foreach (['ayu', 'tidak', 'rp 50 juta', '29 oktober 2026', '5 mg'] as $fact) {
                $this->assertStringContainsString($fact, $submission->analysis_text);
            }
            $classification = $events['classifying']->metadata['input_trace'];
            $this->assertSame(hash('sha256', $normalized), $classification['classifier_input']['sha256']);
            $this->assertSame(mb_strlen($normalized), $classification['classifier_input']['characters']);
            $this->assertSame('id', $classification['classifier_input']['language']);
            $this->assertNull($classification['classifier_input']['tokens']);
            $this->assertNull($classification['classifier_input']['service_truncated']);
            $this->assertSame(hash('sha256', $normalized), $events['translating']->metadata['input_trace']['translation_input']['sha256']);
            $this->assertSame($normalized, $submission->analysis_text);
            $this->assertSame([$normalized], $retriever->queries());
            $this->assertSame('hoax', $submission->detectionResult->label);
            $this->assertSame(0, $submission->evidenceReferences()->count());
            $encoded = json_encode($submission->processingEvents()->pluck('metadata'), JSON_THROW_ON_ERROR);
            foreach ([$original, $normalized, 'Ayu', 'Rp 50', '5 mg'] as $private) {
                $this->assertStringNotContainsString($private, $encoded);
            }
        }
    }

    public function test_translated_analysis_and_bounded_query_report_change_and_truncation_without_mutation(): void
    {
        Queue::fake();
        $source = 'Dr Ayu did NOT receive Rp 50 million on 29 October 2026; dose 5 mg.';
        $translated = 'Dr Ayu TIDAK menerima Rp 50 juta pada 29 Oktober 2026; dosis 5 mg. '.str_repeat('é', 4100);
        $submission = Submission::create(['input_type' => 'text', 'raw_input' => $source, 'extracted_text' => $source, 'status' => 'processing']);
        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())->method('translate')->with($source)
            ->willReturn(new Translation($translated, 'en', true, 'fixture-translation', 'fixture-model'));
        $this->app->instance(Translator::class, $translator);
        $this->app->call([(new TranslateSubmissionText($submission->id)), 'handle']);
        $classifier = $this->createMock(Classifier::class);
        $classifier->expects($this->once())->method('classify')->with($translated)
            ->willReturn(new Classification(DetectionLabel::Valid, 0.99, 'fixture-model'));
        $this->app->instance(Classifier::class, $classifier);
        $this->app->call([(new ClassifySubmission($submission->id)), 'handle']);
        $retriever = new FakeEvidenceRetriever;
        $this->app->instance(EvidenceRetriever::class, $retriever);
        $this->app->call([(new RetrieveSubmissionEvidence($submission->id)), 'handle']);
        $events = $submission->processingEvents()->where('outcome', 'succeeded')->get()->keyBy(fn ($e) => $e->stage->value);
        $trace = $events['translating']->metadata['input_trace'] ?? [];
        $this->assertSame(hash('sha256', $source), $trace['translation_input']['sha256'] ?? null);
        $this->assertSame(hash('sha256', $translated), $trace['translated_text']['sha256']);
        $this->assertSame('en', $trace['translation_input']['language']);
        $this->assertSame('id', $trace['translated_text']['language']);
        $query = mb_substr(trim($translated), 0, 4000);
        $retrieval = $events['retrieving']->metadata['input_trace']['retrieval_query'];
        $this->assertSame(hash('sha256', $translated), $retrieval['parent_sha256']);
        $this->assertSame(hash('sha256', $query), $retrieval['sha256']);
        $this->assertTrue($retrieval['truncated']);
        $this->assertSame(4000, $retrieval['characters']);
        $this->assertSame([$query], $retriever->queries());
        $this->assertSame($translated, $submission->refresh()->analysis_text);
        $this->assertSame('valid', $submission->detectionResult->label);
    }

    public function test_failed_retrieval_retains_started_query_trace_and_retry_does_not_change_input(): void
    {
        Queue::fake();
        $text = 'Nama Ayu tidak menerima 5 mg pada 29 Oktober 2026 di Jakarta.';
        $submission = Submission::create(['input_type' => 'text', 'extracted_text' => $text, 'source_language' => 'id', 'status' => 'processing']);
        $submission->detectionResult()->create(['label' => 'meragukan', 'confidence_score' => 0.6, 'model_version' => 'fixture', 'explanation_status' => 'pending']);
        $retriever = (new FakeEvidenceRetriever)->willThrow(AiServiceException::transient('rag', 'private-provider-message', 503));
        $this->app->instance(EvidenceRetriever::class, $retriever);
        $this->app->call([(new RetrieveSubmissionEvidence($submission->id)), 'handle']);
        $this->app->instance(EvidenceRetriever::class, new FakeEvidenceRetriever);
        $this->app->call([(new RetrieveSubmissionEvidence($submission->id)), 'handle']);
        $started = $submission->processingEvents()->where('stage', 'retrieving')->where('outcome', 'started')->get();
        $this->assertCount(2, $started);
        foreach ($started as $event) {
            $this->assertSame(hash('sha256', $text), $event->metadata['input_trace']['retrieval_query']['sha256']);
            $this->assertFalse($event->metadata['input_trace']['retrieval_query']['truncated']);
        }
        $this->assertSame('meragukan', $submission->refresh()->detectionResult->label);
        $this->assertStringNotContainsString($text, json_encode($submission->processingEvents()->pluck('metadata'), JSON_THROW_ON_ERROR));
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Contracts\EvidenceRetriever;
use App\Contracts\Explainer;
use App\Contracts\Translator;
use App\DataObjects\Classification;
use App\DataObjects\Translation;
use App\Enums\DetectionLabel;
use App\Exceptions\AiServiceException;
use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Models\Submission;
use App\Services\Bert\BertClassifier;
use App\Services\Bert\CircuitBreakingClassifier;
use App\Services\Extraction\OpenAiImageExtractor;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Extraction\TextInputExtractor;
use App\Services\Fakes\FakeClassifier;
use App\Services\Fakes\FakeEvidenceRetriever;
use App\Services\Fakes\FakeExplainer;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(EvidenceRetriever::class, new FakeEvidenceRetriever);
        // Keep this cache test independent from a developer .env threshold;
        // its fixture confidence (0.92) is intentionally above the test
        // contract threshold used by the rest of the fake classifier suite.
        config(['services.bert.confidence_threshold' => 0.65]);
        Cache::flush();
        Cache::forget('breaker:bert:failures');
        Cache::forget('breaker:bert:open');
        Cache::forget('breaker:bert:half-open');
        Cache::forget('breaker:openai:failures');
        Cache::forget('breaker:openai:open');
        Cache::forget('breaker:openai:half-open');
    }

    public function test_bert_cache_hit_reuses_previous_result(): void
    {
        Http::fake([
            config('services.bert.url').'*' => Http::response([
                'request_id' => 'req-1',
                'label' => 'hoax',
                'confidence_score' => 0.92,
                'raw_scores' => ['valid' => 0.08, 'hoax' => 0.92],
                'model_version' => 'v1.0.0',
                'inference_ms' => 42,
            ], 200),
        ]);

        $classifier = app(Classifier::class);
        $text = 'Beredar unggahan di media sosial yang mengklaim bantuan tunai Rp 50 juta';

        $first = $classifier->classify($text);
        $second = $classifier->classify($text);

        $this->assertSame('hoax', $first->label->value);
        $this->assertSame('hoax', $second->label->value);
        // Second should be cached (inferenceMs is 0 or cached flag)
        $this->assertTrue($second->cached || $second->inferenceMs === 0 || $second->inferenceMs === $first->inferenceMs);

        // Only one HTTP call should have been made (second hit cache)
        Http::assertSentCount(1);
    }

    public function test_openai_image_ocr_extracts_stored_image_and_caches_response(): void
    {
        Storage::fake('local');
        config([
            'filesystems.media_disk' => 'local',
            'services.openai.key' => 'test-openai-key',
            'services.openai.vision_model' => 'gpt-4o-mini',
            'services.openai.rate_limit_per_minute' => 1000,
            'services.openai.monthly_quota_usd' => 1000,
        ]);
        $classifier = new FakeClassifier;
        $explainer = new FakeExplainer;
        $translator = new class implements Translator
        {
            /** @var list<string> */
            public array $queries = [];

            public function translate(string $text): Translation
            {
                $this->queries[] = $text;

                return Translation::notRequired($text, 'id');
            }
        };
        $this->app->instance(Classifier::class, $classifier);
        $this->app->instance(Explainer::class, $explainer);
        $this->app->instance(Translator::class, $translator);
        $this->app->instance(
            TextExtractorResolver::class,
            new TextExtractorResolver([app(OpenAiImageExtractor::class)])
        );

        $imageBytes = "\x89PNG\r\n\x1a\n".str_repeat("\0", 12);
        Storage::disk('local')->put('uploads/claim.png', $imageBytes);
        $extractedText = 'Teks klaim hasil OCR dari gambar sintetis untuk regression test.';
        $normalizedText = mb_strtolower($extractedText);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $extractedText]]],
                'usage' => ['prompt_tokens' => 24, 'completion_tokens' => 13],
            ]),
        ]);

        $submission = Submission::create([
            'input_type' => 'image',
            'media_path' => 'uploads/claim.png',
            'status' => 'pending',
        ]);

        ExtractSubmissionText::dispatchSync($submission->id);
        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame($normalizedText, $submission->extracted_text);
        $this->assertSame([$normalizedText], $translator->queries);
        $this->assertSame([$normalizedText], $classifier->classifiedTexts());
        $this->assertSame($normalizedText, $explainer->calls()[0]['excerpt']);

        $cached = app(OpenAiImageExtractor::class)->extract($submission->fresh());
        $this->assertSame($extractedText, $cached->text);
        $this->assertTrue($cached->cached);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($imageBytes): bool {
            $payload = $request->data();
            $imageUrl = data_get($payload, 'messages.0.content.1.image_url.url');

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && data_get($payload, 'messages.0.content.1.type') === 'image_url'
                && is_string($imageUrl)
                && str_starts_with($imageUrl, 'data:')
                && str_ends_with($imageUrl, base64_encode($imageBytes));
        });
    }

    public function test_bert_retryable_failures_are_retried(): void
    {
        Http::fake([
            config('services.bert.url').'*' => Http::response(['error' => ['code' => 'rate_limited']], 429, ['Retry-After' => '5']),
        ]);

        $classifier = app(Classifier::class);

        try {
            $classifier->classify(str_repeat('Klaim yang akan diuji retry. ', 10));
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(429, $e->statusCode);
        }
    }

    public function test_bert_permanent_failures_mark_submission_failed(): void
    {
        Http::fake([
            config('services.bert.url').'*' => Http::response(['error' => ['code' => 'validation_error']], 422),
        ]);

        $classifier = app(Classifier::class);

        try {
            $classifier->classify(str_repeat('Klaim invalid. ', 10));
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->retryable);
            $this->assertSame(422, $e->statusCode);
        }
    }

    public function test_openai_explainer_failure_degrades_but_pipeline_completes(): void
    {
        $this->app->instance(
            Classifier::class,
            (new FakeClassifier)->willReturn(new Classification(DetectionLabel::Valid, 0.88, 'v1.0.0', ['valid' => 0.88, 'hoax' => 0.12], 10))
        );
        // Fake explainer that returns unavailable (simulates server error)
        $this->app->instance(Explainer::class, (new FakeExplainer)->willDegrade('Penjelasan tidak tersedia.'));
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita valid yang perlu penjelasan. ', 10),
            'status' => 'pending',
        ]);

        ExtractSubmissionText::dispatchSync($submission->id);
        ClassifySubmission::dispatchSync($submission->id);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('unavailable', $submission->detectionResult->explanation_status);
        $this->assertSame('valid', $submission->detectionResult->label);
    }

    public function test_circuit_breaker_opens_after_consecutive_failures(): void
    {
        Http::fake([
            config('services.bert.url').'*' => Http::response('Server Error', 500),
        ]);

        $breaker = new CircuitBreaker('bert-test-'.uniqid());
        $classifier = new CircuitBreakingClassifier(
            new BertClassifier,
            $breaker
        );

        // Fail 5 times to trip the breaker
        for ($i = 0; $i < 5; $i++) {
            try {
                $classifier->classify('test text '.str_repeat('a', 50));
            } catch (\Throwable) {
            }
        }

        // Next call should be blocked by open circuit
        try {
            $classifier->classify('test text '.str_repeat('a', 50));
            $this->fail('Expected circuit breaker to be open');
        } catch (AiServiceException $e) {
            $this->assertStringContainsString('mode pemulihan', $e->getMessage());
            $this->assertTrue($e->retryable);
        }
    }

    public function test_explanation_cache_hit(): void
    {
        // Use FakeExplainer for deterministic cache test
        $fake = new FakeExplainer;
        $this->app->instance(Explainer::class, $fake);
        $explainer = app(Explainer::class);
        $classification = new Classification(DetectionLabel::Valid, 0.9, 'v1.0.0', ['valid' => 0.9, 'hoax' => 0.1], 10);
        $excerpt = 'Teks yang sama untuk cache test.';

        $first = $explainer->explain($classification, $excerpt, []);
        $second = $explainer->explain($classification, $excerpt, []);

        $this->assertSame($first->narrative, $second->narrative);
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\Enums\DetectionLabel;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\GenerateSubmissionExplanation;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Services\Fakes\FakeExplainer;
use App\Services\OpenAI\OpenAiExplainer;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExplanationDegradationTest extends TestCase
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

    public function test_cache_hit_is_served_before_quota_and_provider_checks(): void
    {
        $classification = $this->classification();
        $excerpt = 'Kutipan berita yang sudah pernah dijelaskan.';
        Cache::put($this->cacheKey($classification, $excerpt), [
            'narrative' => 'Narasi aman dari cache.',
            'model' => 'gpt-test',
            'prompt_tokens' => 12,
            'completion_tokens' => 8,
        ]);
        $quota = Mockery::mock(OpenAiQuota::class);
        $quota->shouldNotReceive('reserve');
        $breaker = Mockery::mock(CircuitBreaker::class);
        $breaker->shouldNotReceive('check');
        Http::preventStrayRequests();

        $result = (new OpenAiExplainer($breaker, $quota))->explain($classification, $excerpt);

        $this->assertTrue($result->isReady());
        $this->assertTrue($result->cached);
        $this->assertSame('Narasi aman dari cache.', $result->narrative);
        $this->assertSame(0.0, $result->estimatedCostUsd);
        Http::assertNothingSent();
    }

    public function test_cache_hit_remains_available_when_monthly_quota_is_exhausted(): void
    {
        $classification = $this->classification();
        $excerpt = 'Kutipan berita saat kuota telah habis.';
        Cache::put($this->cacheKey($classification, $excerpt), [
            'narrative' => 'Penjelasan yang sebelumnya sudah tersimpan.',
            'model' => 'gpt-test',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
        ]);
        config()->set('services.openai.monthly_quota_usd', 0.0);
        Http::preventStrayRequests();

        $result = $this->explainer()->explain($classification, $excerpt);

        $this->assertTrue($result->isReady());
        $this->assertTrue($result->cached);
        Http::assertNothingSent();
    }

    public function test_missing_api_key_degrades_and_completes_with_bert_result(): void
    {
        config()->set('services.openai.key', '');

        $this->assertDegradedCompletion($this->explainer());
        Http::assertNothingSent();
    }

    public function test_exhausted_quota_degrades_and_completes_with_bert_result(): void
    {
        config()->set('services.openai.monthly_quota_usd', 0.0);

        $this->assertDegradedCompletion($this->explainer());
        Http::assertNothingSent();
    }

    public function test_open_circuit_degrades_and_completes_with_bert_result(): void
    {
        Cache::put('breaker:openai:open', true, 60);

        $this->assertDegradedCompletion($this->explainer());
        Http::assertNothingSent();
    }

    public function test_connection_error_degrades_and_completes_with_bert_result(): void
    {
        Http::fake(fn () => throw new ConnectionException('connection secret'));

        $this->assertDegradedCompletion($this->explainer());
    }

    public function test_timeout_degrades_and_completes_with_bert_result(): void
    {
        Http::fake(fn () => throw new ConnectionException('request timed out with secret'));

        $this->assertDegradedCompletion($this->explainer());
    }

    #[DataProvider('transientHttpStatuses')]
    public function test_transient_http_failure_degrades_and_completes_with_bert_result(int $status): void
    {
        Http::fake(['*' => Http::response(['error' => 'provider secret'], $status)]);

        $this->assertDegradedCompletion($this->explainer());
    }

    /** @return array<string, array{int}> */
    public static function transientHttpStatuses(): array
    {
        return [
            'request timeout' => [408],
            'rate limited' => [429],
            'internal server error' => [500],
            'service unavailable' => [503],
        ];
    }

    #[DataProvider('unusableNarratives')]
    public function test_unusable_provider_narrative_degrades_without_fabricating_text(array $payload): void
    {
        Http::fake(['*' => Http::response($payload)]);

        $this->assertDegradedCompletion($this->explainer());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unusableNarratives(): array
    {
        return [
            'missing narrative' => [['choices' => []]],
            'empty narrative' => [['choices' => [['message' => ['content' => '   ']]]]],
        ];
    }

    public function test_internal_persistence_failure_remains_terminal_and_sanitized(): void
    {
        $submission = $this->submissionWithResult();
        $exception = new QueryException(
            'sqlite',
            'update detection_results set explanation = secret',
            [],
            new PDOException('database password=secret'),
        );
        $state = Mockery::mock(SubmissionStateMachine::class, [
            app(ProcessingEventRecorder::class),
            app(PipelineFailureReporter::class),
        ])->makePartial();
        $state->shouldReceive('markExplained')->once()->andThrow($exception);
        $job = (new GenerateSubmissionExplanation($submission->id))->withFakeQueueInteractions();

        $job->handle(
            (new FakeExplainer)->willReturn(Explanation::ready('Narasi valid.', 'test')),
            app(ProcessingEventRecorder::class),
            $state,
        );

        $job->assertFailed()->assertNotReleased();
        $submission->refresh()->load('detectionResult');
        $this->assertSame('failed', $submission->status);
        $this->assertSame(PipelineFailureReporter::PERSISTENCE_ERROR, $submission->last_error_code);
        $this->assertSame('pending', $submission->detectionResult->explanation_status);
        $this->assertStringNotContainsString('secret', $submission->failure_reason);
    }

    public function test_provider_auth_contract_error_remains_fatal(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid api key secret'], 401)]);
        $submission = $this->submissionWithResult();
        $job = (new GenerateSubmissionExplanation($submission->id))->withFakeQueueInteractions();

        $job->handle(
            $this->explainer(),
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        $job->assertFailed()->assertNotReleased();
        $submission->refresh();
        $this->assertSame('failed', $submission->status);
        $this->assertSame(PipelineFailureReporter::PROVIDER_REQUEST_REJECTED, $submission->last_error_code);
        $this->assertStringNotContainsString('secret', $submission->failure_reason);
    }

    public function test_unavailable_redelivery_is_idempotent_and_does_not_call_provider(): void
    {
        $submission = $this->submissionWithResult();
        $submission->detectionResult->update(['explanation_status' => 'unavailable', 'explanation' => null]);
        $explainer = Mockery::mock(Explainer::class);
        $explainer->shouldNotReceive('explain');

        (new GenerateSubmissionExplanation($submission->id))->handle(
            $explainer,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        $submission->refresh();
        $this->assertSame('completed', $submission->status);
        $this->assertSame('hoax', $submission->detectionResult->fresh()->label);
        $eventCount = $submission->processingEvents()->count();

        (new GenerateSubmissionExplanation($submission->id))->handle(
            $explainer,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        $this->assertSame($eventCount, $submission->processingEvents()->count());
        $this->assertSame('completed', $submission->fresh()->status);
    }

    private function assertDegradedCompletion(OpenAiExplainer $explainer): void
    {
        $submission = $this->submissionWithResult();
        $job = (new GenerateSubmissionExplanation($submission->id))->withFakeQueueInteractions();

        $job->handle(
            $explainer,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        $job->assertNotFailed()->assertNotReleased();
        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame(ProcessingStage::Done->value, $submission->processing_stage);
        $this->assertSame('hoax', $submission->detectionResult->label);
        $this->assertSame('indobert-test-v1', $submission->detectionResult->model_version);
        $this->assertSame('unavailable', $submission->detectionResult->explanation_status);
        $this->assertNull($submission->detectionResult->explanation);
        $this->assertSame(1, $submission->processingEvents()->where('outcome', EventOutcome::Degraded->value)->count());
        $this->assertSame(0, $submission->processingEvents()->whereIn('outcome', [
            EventOutcome::Retried->value,
            EventOutcome::Failed->value,
        ])->count());
    }

    private function explainer(): OpenAiExplainer
    {
        return new OpenAiExplainer(new CircuitBreaker('openai'), new OpenAiQuota);
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

    private function submissionWithResult(): Submission
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Klaim berita yang sudah diklasifikasikan BERT. ', 3),
            'extracted_text' => str_repeat('Klaim berita yang sudah diklasifikasikan BERT. ', 3),
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

    private function cacheKey(Classification $classification, string $excerpt): string
    {
        return 'explanation:'.sha1(implode('|', [
            config('app.ai_prompt_version', '1.0'),
            config('services.openai.chat_model'),
            $classification->label->value,
            $classification->confidenceBand(),
            $excerpt,
        ]));
    }
}

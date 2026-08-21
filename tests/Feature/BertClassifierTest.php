<?php

namespace Tests\Feature;

use App\DataObjects\Classification;
use App\Enums\DetectionLabel;
use App\Exceptions\AiServiceException;
use App\Services\Bert\BertClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BertClassifierTest extends TestCase
{
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = config('services.bert.url');
        // Use a high threshold matching the exported model (0.99) for meragukan tests,
        // but keep the default 0.65 for most tests — override per-test as needed.
        config(['services.bert.confidence_threshold' => 0.65]);
    }

    private function classifier(): BertClassifier
    {
        return new BertClassifier;
    }

    private function successResponse(string $label = 'hoax', float $confidence = 0.94, string $version = 'v1.0.0'): array
    {
        return [
            'request_id' => 'req-123',
            'label' => $label,
            'confidence_score' => $confidence,
            'raw_scores' => ['valid' => $label === 'valid' ? $confidence : 0.03, 'hoax' => $label === 'hoax' ? $confidence : 0.03],
            'model_version' => $version,
            'inference_ms' => 42.5,
        ];
    }

    public function test_classify_success_returns_classification_with_persistable_fields(): void
    {
        Http::fake([
            $this->url.'*' => Http::response($this->successResponse('hoax', 0.94, 'v1.0.0'), 200),
        ]);

        $result = $this->classifier()->classify('Beredar unggahan di media sosial yang mengklaim bansos');

        $this->assertInstanceOf(Classification::class, $result);
        $this->assertSame(DetectionLabel::Hoax, $result->label);
        $this->assertEqualsWithDelta(0.94, $result->confidenceScore, 0.001);
        $this->assertSame('v1.0.0', $result->modelVersion);
        $this->assertSame(0.94, $result->rawScores['hoax']);
        $this->assertSame(42, (int) $result->inferenceMs);
    }

    public function test_classify_success_valid_label(): void
    {
        Http::fake([
            $this->url.'*' => Http::response($this->successResponse('valid', 0.91, 'v1.0.0'), 200),
        ]);

        $result = $this->classifier()->classify('Jakarta (ANTARA) - Bank Indonesia mencatat pertumbuhan');

        $this->assertSame(DetectionLabel::Valid, $result->label);
        $this->assertEqualsWithDelta(0.91, $result->confidenceScore, 0.001);
    }

    public function test_classify_applies_confidence_threshold_to_produce_meragukan(): void
    {
        config(['services.bert.confidence_threshold' => 0.99]);

        Http::fake([
            $this->url.'*' => Http::response($this->successResponse('hoax', 0.85, 'v1.0.0'), 200),
        ]);

        $result = $this->classifier()->classify('Ambiguous claim with medium confidence');

        // 0.85 < 0.99 → meragukan per DetectionLabel::fromConfidence
        $this->assertSame(DetectionLabel::Meragukan, $result->label);
        $this->assertEqualsWithDelta(0.85, $result->confidenceScore, 0.001);
        // Raw scores are preserved
        $this->assertArrayHasKey('hoax', $result->rawScores);
    }

    public function test_classify_returns_meragukan_when_fastapi_already_returns_it(): void
    {
        Http::fake([
            $this->url.'*' => Http::response($this->successResponse('meragukan', 0.64, 'v1.0.0'), 200),
        ]);

        $result = $this->classifier()->classify('Halo dunia hello world');

        $this->assertSame(DetectionLabel::Meragukan, $result->label);
    }

    public function test_classify_throws_transient_on_connection_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(AiServiceException::class);
        try {
            $this->classifier()->classify('test');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->retryable);
            $this->assertStringContainsString('tidak dapat dihubungi', $e->getMessage());
            throw $e;
        }
    }

    public function test_classify_throws_transient_on_429_with_retry_after(): void
    {
        Http::fake([
            $this->url.'*' => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'Too many']], 429, ['Retry-After' => '120']),
        ]);

        try {
            $this->classifier()->classify('test');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(429, $e->statusCode);
            $this->assertSame(120, $e->retryAfterSeconds);
        }
    }

    public function test_classify_throws_transient_on_503_model_not_ready(): void
    {
        Http::fake([
            $this->url.'*' => Http::response(['error' => ['code' => 'model_unavailable', 'message' => 'Model is not ready']], 503),
        ]);

        try {
            $this->classifier()->classify('test');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(503, $e->statusCode);
        }
    }

    public function test_classify_throws_transient_on_500(): void
    {
        Http::fake([
            $this->url.'*' => Http::response('Internal Server Error', 500),
        ]);

        try {
            $this->classifier()->classify('test');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(500, $e->statusCode);
        }
    }

    public function test_classify_throws_permanent_on_422(): void
    {
        Http::fake([
            $this->url.'*' => Http::response(['error' => ['code' => 'validation_error', 'message' => 'bad']], 422),
        ]);

        try {
            $this->classifier()->classify('   ');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->retryable);
            $this->assertSame(422, $e->statusCode);
        }
    }

    public function test_classify_throws_permanent_on_malformed_json_missing_fields(): void
    {
        Http::fake([
            $this->url.'*' => Http::response(['label' => 'hoax'], 200), // missing confidence_score, model_version
        ]);

        try {
            $this->classifier()->classify('test');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->retryable);
            $this->assertStringContainsString('tidak sesuai kontrak', $e->getMessage());
        }
    }

    public function test_classify_throws_permanent_on_invalid_label(): void
    {
        Http::fake([
            $this->url.'*' => Http::response([
                'request_id' => 'req-123',
                'label' => 'invalid_label',
                'confidence_score' => 0.9,
                'raw_scores' => ['valid' => 0.1, 'hoax' => 0.9],
                'model_version' => 'v1.0.0',
                'inference_ms' => 10,
            ], 200),
        ]);

        try {
            $this->classifier()->classify('test');
            $this->fail('Expected AiServiceException');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->retryable);
            $this->assertStringContainsString('unsupported label', $e->getMessage());
        }
    }

    public function test_classify_sends_correct_payload_and_auth_header(): void
    {
        Http::fake([
            $this->url.'*' => Http::response($this->successResponse('hoax', 0.9, 'v1.0.0'), 200),
        ]);

        config(['services.bert.internal_token' => 'secret-token-xyz']);

        $this->classifier()->classify('payload text check');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/predict')
                && $request->data()['text'] === 'payload text check'
                && $request->hasHeader('Authorization', 'Bearer secret-token-xyz')
                && $request->header('Accept')[0] === 'application/json';
        });
    }
}

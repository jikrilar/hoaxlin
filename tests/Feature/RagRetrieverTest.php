<?php

namespace Tests\Feature;

use App\Contracts\EvidenceRetriever;
use App\DataObjects\RetrievedEvidence;
use App\Exceptions\AiServiceException;
use App\Services\Fakes\FakeEvidenceRetriever;
use App\Services\Rag\RagRetriever;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RagRetrieverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.rag.url' => 'http://rag.test',
            'services.rag.internal_token' => null,
            'services.rag.connect_timeout' => 2,
            'services.rag.timeout' => 8,
            'services.rag.top_k' => 3,
            'services.rag.min_score' => null,
        ]);
    }

    public function test_it_posts_query_using_the_configured_default_top_k(): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['results' => []])]);

        $this->retriever()->retrieve('  klaim yang diperiksa  ');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://rag.test/retrieve'
            && $request->data() === ['text' => 'klaim yang diperiksa', 'top_k' => 3]
            && $request->header('Accept')[0] === 'application/json');
    }

    public function test_it_applies_explicit_top_k_and_sends_optional_min_score(): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['results' => []])]);

        $this->retriever()->retrieve('query', topK: 7, minScore: 0.25);

        Http::assertSent(fn (Request $request): bool => $request->data() === [
            'text' => 'query', 'top_k' => 7, 'min_score' => 0.25,
        ]);
    }

    public function test_it_sends_configured_min_score_when_no_override_is_passed(): void
    {
        config(['services.rag.min_score' => '0.4']);
        Http::fake(['http://rag.test/*' => Http::response(['results' => []])]);

        $this->retriever()->retrieve('query');

        Http::assertSent(fn (Request $request): bool => $request->data()['min_score'] === 0.4);
    }

    public function test_it_sends_bearer_token_and_forwards_request_id(): void
    {
        config(['services.rag.internal_token' => 'rag-test-token']);
        $this->app['request']->headers->set('X-Request-Id', 'request-abc');
        Http::fake(['http://rag.test/*' => Http::response(['results' => []])]);

        $this->retriever()->retrieve('query');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer rag-test-token')
            && $request->hasHeader('X-Request-Id', 'request-abc'));
    }

    public function test_it_maps_result_fields_without_losing_source_provenance(): void
    {
        $record = $this->resultPayload();
        Http::fake(['http://rag.test/*' => Http::response(['results' => [$record]])]);

        $results = $this->retriever()->retrieve('query');

        $this->assertCount(1, $results);
        $this->assertInstanceOf(RetrievedEvidence::class, $results[0]);
        $this->assertSame('doc-001', $results[0]->documentId);
        $this->assertSame('Badan sumber', $results[0]->source);
        $this->assertSame('https://source.example/check', $results[0]->sourceUrl);
        $this->assertSame('2026-09-01', $results[0]->publishedAt);
        $this->assertSame('Kutipan dari dokumen.', $results[0]->snippet);
        $this->assertSame(0.82, $results[0]->score);
        $this->assertSame(1, $results[0]->rank);
    }

    public function test_empty_results_are_returned_as_an_empty_list(): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['results' => []])]);

        $this->assertSame([], $this->retriever()->retrieve('query'));
    }

    public function test_malformed_response_is_a_clear_permanent_error(): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['results' => [['title' => 'missing fields']]])]);

        try {
            $this->retriever()->retrieve('query');
            $this->fail('Expected an invalid response error.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame(200, $exception->statusCode);
            $this->assertStringContainsString('tidak sesuai kontrak', $exception->getMessage());
            $this->assertStringNotContainsString('C:\\xampp', $exception->getMessage());
        }
    }

    public function test_response_cannot_exceed_requested_top_k_or_repeat_a_document(): void
    {
        $first = $this->resultPayload();
        $second = array_replace($first, ['rank' => 2]);
        Http::fake(['http://rag.test/*' => Http::response(['results' => [$first, $second]])]);

        $this->expectException(AiServiceException::class);
        $this->retriever()->retrieve('query', topK: 2);
    }

    public function test_invalid_query_and_top_k_fail_before_http(): void
    {
        Http::fake();

        foreach ([['   ', 3], ['query', 11], [str_repeat('a', 4001), 3]] as [$text, $topK]) {
            try {
                $this->retriever()->retrieve($text, $topK);
                $this->fail('Expected invalid request to be rejected.');
            } catch (AiServiceException $exception) {
                $this->assertFalse($exception->retryable);
            }
        }

        Http::assertNothingSent();
    }

    public function test_connection_failure_is_transient(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection timed out');
        });

        try {
            $this->retriever()->retrieve('query');
            $this->fail('Expected connection failure.');
        } catch (AiServiceException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame('rag', $exception->service);
            $this->assertStringContainsString('tidak dapat dihubungi', $exception->getMessage());
        }
    }

    #[DataProvider('temporaryStatuses')]
    public function test_rate_limit_and_server_errors_are_transient(int $status): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['detail' => 'private detail'], $status)]);

        try {
            $this->retriever()->retrieve('query');
            $this->fail('Expected transient service failure.');
        } catch (AiServiceException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame($status, $exception->statusCode);
            $this->assertStringNotContainsString('private detail', $exception->getMessage());
        }
    }

    public static function temporaryStatuses(): array
    {
        return ['rate limited' => [429], 'not ready' => [503], 'server error' => [500], 'gateway error' => [502]];
    }

    #[DataProvider('permanentStatuses')]
    public function test_non_transient_client_errors_are_permanent(int $status): void
    {
        Http::fake(['http://rag.test/*' => Http::response(['detail' => 'private detail'], $status)]);

        try {
            $this->retriever()->retrieve('query');
            $this->fail('Expected permanent service failure.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame($status, $exception->statusCode);
            $this->assertStringNotContainsString('private detail', $exception->getMessage());
        }
    }

    public static function permanentStatuses(): array
    {
        return ['bad request' => [400], 'unauthorized' => [401], 'validation error' => [422]];
    }

    public function test_container_binds_contract_to_http_adapter(): void
    {
        $this->assertInstanceOf(RagRetriever::class, $this->app->make(EvidenceRetriever::class));
    }

    public function test_fake_returns_configured_evidence_tracks_queries_and_can_throw(): void
    {
        $evidence = RetrievedEvidence::fromResponse($this->resultPayload());
        $fake = new FakeEvidenceRetriever([$evidence]);

        $this->assertSame([$evidence], $fake->retrieve('first query', 4, 0.2));
        $this->assertSame(['first query'], $fake->queries());
        $this->assertSame(['text' => 'first query', 'top_k' => 4, 'min_score' => 0.2], $fake->requests()[0]);
        $this->assertSame(1, $fake->timesCalled());

        $failure = AiServiceException::transient('rag', 'temporary');
        $fake->willThrow($failure);
        try {
            $fake->retrieve('second query');
            $this->fail('Expected configured fake exception.');
        } catch (AiServiceException $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertSame(['first query', 'second query'], $fake->queries());
    }

    private function retriever(): RagRetriever
    {
        return new RagRetriever;
    }

    /** @return array<string, string|float|int|null> */
    private function resultPayload(): array
    {
        return [
            'document_id' => 'doc-001',
            'title' => 'Judul sumber',
            'source' => 'Badan sumber',
            'source_url' => 'https://source.example/check',
            'published_at' => '2026-09-01',
            'snippet' => 'Kutipan dari dokumen.',
            'score' => 0.82,
            'rank' => 1,
        ];
    }
}

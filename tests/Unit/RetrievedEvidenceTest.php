<?php

namespace Tests\Unit;

use App\DataObjects\RetrievedEvidence;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetrievedEvidenceTest extends TestCase
{
    public function test_it_maps_a_valid_r3_result_and_allows_null_published_date(): void
    {
        $payload = $this->validPayload();
        $payload['published_at'] = null;

        $evidence = RetrievedEvidence::fromResponse($payload);

        $this->assertSame('doc-001', $evidence->documentId);
        $this->assertSame('Judul sumber', $evidence->title);
        $this->assertSame('https://source.example/article', $evidence->sourceUrl);
        $this->assertNull($evidence->publishedAt);
        $this->assertSame(0.82, $evidence->score);
        $this->assertSame(1, $evidence->rank);
        $this->assertSame($payload, $evidence->toArray());
    }

    public function test_it_rejects_missing_or_extra_response_fields(): void
    {
        $payload = $this->validPayload();
        unset($payload['snippet']);

        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    public function test_it_rejects_non_string_required_fields(): void
    {
        $payload = $this->validPayload();
        $payload['title'] = 123;

        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    public function test_it_rejects_invalid_source_url(): void
    {
        $payload = $this->validPayload();
        $payload['source_url'] = 'javascript:alert(1)';

        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    public function test_it_rejects_invalid_published_date(): void
    {
        $payload = $this->validPayload();
        $payload['published_at'] = '2026-02-30';

        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    public function test_it_rejects_out_of_range_score_and_non_positive_rank(): void
    {
        $payload = $this->validPayload();
        $payload['score'] = 1.01;

        try {
            RetrievedEvidence::fromResponse($payload);
            $this->fail('Expected invalid score to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('score', $exception->getMessage());
        }

        $payload = $this->validPayload();
        $payload['rank'] = 0;
        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    public static function malformedPayloads(): array
    {
        $base = [
            'document_id' => 'doc-001',
            'title' => 'Judul sumber',
            'source' => 'Sumber',
            'source_url' => 'https://source.example/article',
            'published_at' => '2026-09-01',
            'snippet' => 'Cuplikan evidence yang dirujuk.',
            'score' => 0.82,
            'rank' => 1,
        ];

        return [
            'boolean score is not a JSON number' => [array_replace($base, ['score' => true])],
            'float rank is not a JSON integer' => [array_replace($base, ['rank' => 1.0])],
            'empty title is rejected' => [array_replace($base, ['title' => '  '])],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function test_it_rejects_invalid_types_and_empty_values(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        RetrievedEvidence::fromResponse($payload);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'document_id' => 'doc-001',
            'title' => 'Judul sumber',
            'source' => 'Sumber',
            'source_url' => 'https://source.example/article',
            'published_at' => '2026-09-01',
            'snippet' => 'Cuplikan evidence yang dirujuk.',
            'score' => 0.82,
            'rank' => 1,
        ];
    }
}

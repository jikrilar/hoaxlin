<?php

namespace App\Services\Fakes;

use App\Contracts\EvidenceRetriever;
use App\DataObjects\RetrievedEvidence;
use App\Exceptions\AiServiceException;

/** In-memory evidence retriever for tests that must not make HTTP requests. */
class FakeEvidenceRetriever implements EvidenceRetriever
{
    /** @var list<array{text: string, top_k: ?int, min_score: ?float}> */
    private array $requests = [];

    /** @param list<RetrievedEvidence> $results */
    public function __construct(
        private array $results = [],
        private ?AiServiceException $exception = null,
    ) {}

    /**
     * @return list<RetrievedEvidence>
     */
    public function retrieve(string $text, ?int $topK = null, ?float $minScore = null): array
    {
        $this->requests[] = ['text' => $text, 'top_k' => $topK, 'min_score' => $minScore];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->results;
    }

    /** @param list<RetrievedEvidence> $results */
    public function willReturn(array $results): self
    {
        $this->results = $results;
        $this->exception = null;

        return $this;
    }

    public function willThrow(AiServiceException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /** @return list<array{text: string, top_k: ?int, min_score: ?float}> */
    public function requests(): array
    {
        return $this->requests;
    }

    /** @return list<string> */
    public function queries(): array
    {
        return array_column($this->requests, 'text');
    }

    public function timesCalled(): int
    {
        return count($this->requests);
    }
}

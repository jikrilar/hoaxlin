<?php

namespace App\Services\Fakes;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;

/**
 * Stand-in for the OpenAI narrative service, including its degraded path.
 */
class FakeExplainer implements Explainer
{
    /**
     * @var list<array{
     *     classification: Classification,
     *     excerpt: string,
     *     evidence: list<array{
     *         document_id: string,
     *         title: string,
     *         source: string,
     *         source_url: string,
     *         published_at: string|null,
     *         snippet: string,
     *         similarity_score: float,
     *         rank: int,
     *         knowledge_base_version: string|null
     *     }>
     * }>
     */
    private array $calls = [];

    public function __construct(private ?Explanation $result = null) {}

    /**
     * @param list<array{
     *     document_id: string,
     *     title: string,
     *     source: string,
     *     source_url: string,
     *     published_at: string|null,
     *     snippet: string,
     *     similarity_score: float,
     *     rank: int,
     *     knowledge_base_version: string|null
     * }> $evidence
     */
    public function explain(Classification $classification, string $excerpt, array $evidence): Explanation
    {
        $this->calls[] = ['classification' => $classification, 'excerpt' => $excerpt, 'evidence' => $evidence];

        return $this->result ?? Explanation::ready(
            narrative: sprintf(
                'Model menilai konten ini %s dengan tingkat keyakinan %s persen.',
                $classification->label->label(),
                $classification->confidencePercentage(),
            ),
            model: 'fake-explainer-v1',
        );
    }

    public function willReturn(Explanation $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function willDegrade(?string $fallbackNarrative = null): self
    {
        $this->result = Explanation::unavailable($fallbackNarrative);

        return $this;
    }

    /**
     * @return list<array{
     *     classification: Classification,
     *     excerpt: string,
     *     evidence: list<array{
     *         document_id: string,
     *         title: string,
     *         source: string,
     *         source_url: string,
     *         published_at: string|null,
     *         snippet: string,
     *         similarity_score: float,
     *         rank: int,
     *         knowledge_base_version: string|null
     *     }>
     * }>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function timesCalled(): int
    {
        return count($this->calls);
    }
}

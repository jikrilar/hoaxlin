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
    /** @var list<array{classification: Classification, excerpt: string}> */
    private array $calls = [];

    public function __construct(private ?Explanation $result = null) {}

    public function explain(Classification $classification, string $excerpt): Explanation
    {
        $this->calls[] = ['classification' => $classification, 'excerpt' => $excerpt];

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
     * @return list<array{classification: Classification, excerpt: string}>
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

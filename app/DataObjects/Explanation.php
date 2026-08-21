<?php

namespace App\DataObjects;

use App\Enums\ExplanationStatus;
use InvalidArgumentException;

/**
 * Result of the narrative stage. Supports an explicit "unavailable" state so a
 * failed OpenAI call degrades the explanation without failing the submission.
 */
final readonly class Explanation
{
    public function __construct(
        public ?string $narrative,
        public ExplanationStatus $status,
        public ?string $model = null,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public float $estimatedCostUsd = 0.0,
        public bool $cached = false,
    ) {
        if ($status === ExplanationStatus::Ready && trim((string) $narrative) === '') {
            throw new InvalidArgumentException('A ready explanation must contain a narrative.');
        }

        if ($promptTokens < 0 || $completionTokens < 0) {
            throw new InvalidArgumentException('Token counts must not be negative.');
        }

        if ($estimatedCostUsd < 0.0) {
            throw new InvalidArgumentException('Explanation cost must not be negative.');
        }
    }

    public static function ready(
        string $narrative,
        string $model,
        int $promptTokens = 0,
        int $completionTokens = 0,
        float $estimatedCostUsd = 0.0,
        bool $cached = false,
    ): self {
        return new self(
            narrative: $narrative,
            status: ExplanationStatus::Ready,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $estimatedCostUsd,
            cached: $cached,
        );
    }

    /**
     * Used when OpenAI is unreachable or over quota. The caller still persists
     * a completed submission carrying the BERT verdict.
     */
    public static function unavailable(?string $fallbackNarrative = null): self
    {
        return new self(
            narrative: $fallbackNarrative,
            status: ExplanationStatus::Unavailable,
        );
    }

    public static function pending(): self
    {
        return new self(narrative: null, status: ExplanationStatus::Pending);
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function isReady(): bool
    {
        return $this->status === ExplanationStatus::Ready;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'narrative' => $this->narrative,
            'status' => $this->status->value,
            'model' => $this->model,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'cached' => $this->cached,
        ];
    }
}

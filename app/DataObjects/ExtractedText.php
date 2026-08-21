<?php

namespace App\DataObjects;

use App\Enums\InputType;
use App\Support\TextNormalizer;
use InvalidArgumentException;

/**
 * Result of the extraction stage. Carries provenance and cost so the pipeline
 * can attribute spend and reproduce how the analysed text was obtained.
 */
final readonly class ExtractedText
{
    public function __construct(
        public string $text,
        public InputType $inputType,
        public string $provider,
        public ?float $confidence = null,
        public bool $cached = false,
        public int $durationMs = 0,
        public float $estimatedCostUsd = 0.0,
    ) {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Extracted text must not be empty.');
        }

        if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
            throw new InvalidArgumentException('Extraction confidence must be between 0 and 1.');
        }

        if ($durationMs < 0) {
            throw new InvalidArgumentException('Extraction duration must not be negative.');
        }

        if ($estimatedCostUsd < 0.0) {
            throw new InvalidArgumentException('Extraction cost must not be negative.');
        }
    }

    /**
     * Normalized text is what gets hashed and sent to the model, so cache keys
     * stay stable across cosmetic differences in forwarded messages.
     */
    public function normalized(): string
    {
        return TextNormalizer::normalize($this->text);
    }

    public function contentHash(): string
    {
        return TextNormalizer::hash($this->text);
    }

    public function excerpt(int $length = 1500): string
    {
        return mb_substr($this->text, 0, $length);
    }

    public function isBillable(): bool
    {
        return $this->estimatedCostUsd > 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'input_type' => $this->inputType->value,
            'provider' => $this->provider,
            'confidence' => $this->confidence,
            'cached' => $this->cached,
            'duration_ms' => $this->durationMs,
            'estimated_cost_usd' => $this->estimatedCostUsd,
        ];
    }
}

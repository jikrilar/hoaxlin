<?php

namespace App\DataObjects;

use App\Enums\DetectionLabel;
use InvalidArgumentException;

/**
 * Result of the BERT classification stage.
 *
 * Built through fromResponse() so a FastAPI contract change fails at the
 * network boundary with a clear message, instead of silently persisting a
 * malformed detection result.
 */
final readonly class Classification
{
    /**
     * @param  array<string, float>  $rawScores
     */
    public function __construct(
        public DetectionLabel $label,
        public float $confidenceScore,
        public string $modelVersion,
        public array $rawScores = [],
        public int $inferenceMs = 0,
        public bool $cached = false,
    ) {
        if ($confidenceScore < 0.0 || $confidenceScore > 1.0) {
            throw new InvalidArgumentException('Confidence score must be between 0 and 1.');
        }

        if (trim($modelVersion) === '') {
            throw new InvalidArgumentException('Model version must not be empty.');
        }

        if ($inferenceMs < 0) {
            throw new InvalidArgumentException('Inference duration must not be negative.');
        }

        foreach ($rawScores as $class => $score) {
            if (DetectionLabel::tryFrom((string) $class) === null) {
                throw new InvalidArgumentException("Unknown class in raw scores: {$class}.");
            }

            if (! is_numeric($score) || $score < 0.0 || $score > 1.0) {
                throw new InvalidArgumentException("Raw score for {$class} must be between 0 and 1.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload, bool $cached = false): self
    {
        foreach (['label', 'confidence_score', 'model_version'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new InvalidArgumentException("Classifier response is missing the [{$key}] field.");
            }
        }

        $label = DetectionLabel::tryFrom((string) $payload['label']);

        if ($label === null) {
            throw new InvalidArgumentException("Classifier returned an unsupported label: {$payload['label']}.");
        }

        if (! is_numeric($payload['confidence_score'])) {
            throw new InvalidArgumentException('Classifier returned a non-numeric confidence score.');
        }

        /** @var array<string, float> $rawScores */
        $rawScores = array_map(
            static fn (mixed $score): float => (float) $score,
            is_array($payload['raw_scores'] ?? null) ? $payload['raw_scores'] : [],
        );

        return new self(
            label: $label,
            confidenceScore: (float) $payload['confidence_score'],
            modelVersion: (string) $payload['model_version'],
            rawScores: $rawScores,
            inferenceMs: (int) ($payload['inference_ms'] ?? 0),
            cached: $cached,
        );
    }

    /**
     * Re-evaluate the reported label against the configured threshold. Keeps
     * the "meragukan" band tunable from config without retraining the model.
     */
    public function withConfidenceThreshold(float $threshold): self
    {
        $label = DetectionLabel::fromConfidence($this->label, $this->confidenceScore, $threshold);

        if ($label === $this->label) {
            return $this;
        }

        return new self(
            label: $label,
            confidenceScore: $this->confidenceScore,
            modelVersion: $this->modelVersion,
            rawScores: $this->rawScores,
            inferenceMs: $this->inferenceMs,
            cached: $this->cached,
        );
    }

    public function asCached(): self
    {
        return new self(
            label: $this->label,
            confidenceScore: $this->confidenceScore,
            modelVersion: $this->modelVersion,
            rawScores: $this->rawScores,
            inferenceMs: $this->inferenceMs,
            cached: true,
        );
    }

    public function confidencePercentage(): float
    {
        return round($this->confidenceScore * 100, 2);
    }

    /**
     * Buckets confidence into 0.05 bands so near-identical results can reuse a
     * cached explanation narrative.
     */
    public function confidenceBand(): string
    {
        return number_format(floor($this->confidenceScore * 20) / 20, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label->value,
            'confidence_score' => $this->confidenceScore,
            'model_version' => $this->modelVersion,
            'raw_scores' => $this->rawScores,
            'inference_ms' => $this->inferenceMs,
            'cached' => $this->cached,
        ];
    }
}

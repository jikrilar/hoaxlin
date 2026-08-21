<?php

namespace App\Services\Fakes;

use App\Contracts\Classifier;
use App\DataObjects\Classification;
use App\Enums\DetectionLabel;
use App\Exceptions\AiServiceException;

/**
 * Deterministic stand-in for the IndoBERT service.
 *
 * Lets the pipeline be built and tested end to end before the fine-tuned model
 * exists, and lets tests assert failure handling without HTTP fakery.
 */
class FakeClassifier implements Classifier
{
    /** @var list<string> */
    private array $classified = [];

    public function __construct(
        private ?Classification $result = null,
        private ?AiServiceException $exception = null,
    ) {}

    public function classify(string $text): Classification
    {
        $this->classified[] = $text;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result ?? new Classification(
            label: DetectionLabel::Meragukan,
            confidenceScore: 0.6000,
            modelVersion: 'fake-classifier-v1',
            rawScores: ['valid' => 0.2, 'hoax' => 0.2, 'meragukan' => 0.6],
            inferenceMs: 1,
        );
    }

    public function willReturn(Classification $result): self
    {
        $this->result = $result;
        $this->exception = null;

        return $this;
    }

    public function willThrow(AiServiceException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function classifiedTexts(): array
    {
        return $this->classified;
    }

    public function timesCalled(): int
    {
        return count($this->classified);
    }
}

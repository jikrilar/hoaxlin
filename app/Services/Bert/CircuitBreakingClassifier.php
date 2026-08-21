<?php

namespace App\Services\Bert;

use App\Contracts\Classifier;
use App\DataObjects\Classification;
use App\Exceptions\AiServiceException;
use App\Services\Resilience\CircuitBreaker;

class CircuitBreakingClassifier implements Classifier
{
    public function __construct(
        private readonly Classifier $classifier,
        private readonly CircuitBreaker $breaker,
    ) {}

    public function classify(string $text): Classification
    {
        $this->breaker->check();

        try {
            $result = $this->classifier->classify($text);
        } catch (AiServiceException $exception) {
            if ($exception->retryable) {
                $this->breaker->recordFailure();
            }

            throw $exception;
        }

        $this->breaker->recordSuccess();

        return $result;
    }
}

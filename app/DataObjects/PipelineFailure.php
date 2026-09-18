<?php

namespace App\DataObjects;

final readonly class PipelineFailure
{
    public function __construct(
        public string $publicMessage,
        public string $service,
        public string $errorCode,
        public bool $retryable,
        public string $reference,
        public ?int $providerStatus = null,
        public ?int $retryAfterSeconds = null,
    ) {}
}

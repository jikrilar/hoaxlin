<?php

namespace App\Exceptions;

use RuntimeException;

final class SanitizedPipelineException extends RuntimeException
{
    public function __construct(
        string $publicMessage,
        public readonly string $service,
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly ?int $providerStatus = null,
    ) {
        parent::__construct($publicMessage);
    }
}

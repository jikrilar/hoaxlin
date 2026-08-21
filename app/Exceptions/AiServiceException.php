<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised by infrastructure adapters (BERT, OpenAI) so the application layer can
 * decide between retrying and failing without inspecting HTTP details.
 */
class AiServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $service,
        public readonly bool $retryable,
        public readonly ?int $statusCode = null,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * Connection errors, timeouts, 429s and 5xx responses are worth another
     * attempt once the dependency recovers.
     */
    public static function transient(
        string $service,
        string $message,
        ?int $statusCode = null,
        ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: $message,
            service: $service,
            retryable: true,
            statusCode: $statusCode,
            retryAfterSeconds: $retryAfterSeconds,
            previous: $previous,
        );
    }

    /**
     * Validation errors, auth failures and malformed contracts will fail again
     * identically, so they must not consume retry attempts.
     */
    public static function permanent(
        string $service,
        string $message,
        ?int $statusCode = null,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: $message,
            service: $service,
            retryable: false,
            statusCode: $statusCode,
            previous: $previous,
        );
    }
}

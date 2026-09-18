<?php

namespace App\Services\Pipeline;

use App\DataObjects\PipelineFailure;

final class PipelineRetryPolicy
{
    public function shouldRetry(PipelineFailure $failure, int $attempt, int $maxAttempts): bool
    {
        return $failure->retryable && $attempt < $maxAttempts;
    }

    /** @param list<int> $backoff */
    public function delay(PipelineFailure $failure, int $attempt, array $backoff = []): int
    {
        $maximum = max(1, (int) config('pipeline.retry.max_delay_seconds', 900));

        if ($failure->retryAfterSeconds !== null && $failure->retryAfterSeconds > 0) {
            return min($failure->retryAfterSeconds, $maximum);
        }

        $delays = $backoff !== []
            ? array_values($backoff)
            : config('pipeline.retry.backoff', [5, 15, 60]);
        $delays = array_values(array_filter((array) $delays, fn (mixed $delay): bool => is_numeric($delay) && (int) $delay > 0));
        if ($delays === []) {
            $delays = [5, 15, 60];
        }
        $index = min(max(0, $attempt - 1), count($delays) - 1);

        return min(max(1, (int) $delays[$index]), $maximum);
    }
}

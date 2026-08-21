<?php

namespace App\Services\Pipeline;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Models\Submission;

class ProcessingEventRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        Submission $submission,
        ProcessingStage $stage,
        EventOutcome $outcome,
        ?string $service = null,
        int $attempt = 1,
        ?int $durationMs = null,
        ?string $errorCode = null,
        array $metadata = [],
    ): void {
        $submission->processingEvents()->create([
            'stage' => $stage,
            'outcome' => $outcome,
            'service' => $service,
            'attempt' => $attempt,
            'duration_ms' => $durationMs,
            'error_code' => $errorCode,
            'metadata' => $metadata ?: null,
        ]);
    }
}

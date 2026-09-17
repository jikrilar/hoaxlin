<?php

namespace App\Jobs\Concerns;

trait UniqueSubmissionStage
{
    public function uniqueId(): string
    {
        if (property_exists($this, 'submissionId')) {
            return (string) $this->submissionId;
        }

        return (string) $this->submission->getKey();
    }

    public function uniqueFor(): int
    {
        return max(60, (int) config('pipeline.stale_after_minutes', 45) * 60);
    }
}

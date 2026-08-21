<?php

namespace App\Models;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionProcessingEvent extends Model
{
    protected $fillable = [
        'submission_id',
        'stage',
        'outcome',
        'service',
        'attempt',
        'duration_ms',
        'error_code',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ProcessingStage::class,
            'outcome' => EventOutcome::class,
            'metadata' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

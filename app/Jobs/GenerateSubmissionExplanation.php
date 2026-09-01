<?php

namespace App\Jobs;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\Enums\DetectionLabel;
use App\Enums\ExplanationStatus;
use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateSubmissionExplanation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return config('services.openai.backoff', [10, 60, 180]);
    }

    public function handle(Explainer $explainer, ProcessingEventRecorder $events, SubmissionStateMachine $state): void
    {
        Cache::lock("submission:{$this->submissionId}:explain", 75)->block(5, function () use ($explainer, $state): void {
            $submission = Submission::with('detectionResult')->findOrFail($this->submissionId);
            $result = $submission->detectionResult;

            if ($result === null) {
                return;
            }

            if ($result->explanation_status === ExplanationStatus::Ready->value) {
                $state->markCompleted($submission, $this->attempts());

                return;
            }

            $state->markProcessing($submission, ProcessingStage::Explaining, $this->attempts());
            $classification = new Classification(
                DetectionLabel::from($result->label),
                (float) $result->confidence_score,
                $result->model_version,
                $result->raw_scores ?? [],
                $result->inference_ms,
                $result->classifier_cached,
            );
            $explanation = $explainer->explain($classification, mb_substr((string) $submission->analysis_text, 0, 1500));
            $state->markExplained($submission, $explanation, $this->attempts());
            $state->markCompleted($submission, $this->attempts());
        });
    }

    public function failed(Throwable $exception): void
    {
        $submission = Submission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        try {
            app(SubmissionStateMachine::class)->markFailedFinal(
                $submission,
                ProcessingStage::Explaining,
                $exception,
                $this->attempts(),
            );
        } catch (Throwable) {
        }
    }
}

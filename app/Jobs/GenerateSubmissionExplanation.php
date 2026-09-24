<?php

namespace App\Jobs;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\Enums\DetectionLabel;
use App\Enums\ExplanationStatus;
use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Jobs\Concerns\UniqueSubmissionStage;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GenerateSubmissionExplanation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels, UniqueSubmissionStage;

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
            $submission = Submission::with(['detectionResult', 'evidenceReferences'])->findOrFail($this->submissionId);

            if ($submission->isTerminal()) {
                return;
            }

            $result = $submission->detectionResult;

            if ($result === null) {
                return;
            }

            if (in_array($result->explanation_status, [
                ExplanationStatus::Ready->value,
                ExplanationStatus::Unavailable->value,
            ], true)) {
                $state->markCompleted($submission, $this->attempts());

                return;
            }

            try {
                $state->markProcessing($submission, ProcessingStage::Explaining, $this->attempts());
                $classification = new Classification(
                    DetectionLabel::from($result->label),
                    (float) $result->confidence_score,
                    $result->model_version,
                    $result->raw_scores ?? [],
                    $result->inference_ms,
                    $result->classifier_cached,
                );
                $evidence = $submission->evidenceReferences
                    ->sort(fn (EvidenceReference $left, EvidenceReference $right): int => [
                        $left->rank,
                        $left->document_id,
                    ] <=> [
                        $right->rank,
                        $right->document_id,
                    ])
                    ->map(fn (EvidenceReference $reference): array => [
                        'document_id' => $reference->document_id,
                        'title' => $reference->title,
                        'source' => $reference->source,
                        'source_url' => $reference->source_url,
                        'published_at' => $reference->published_at?->format('Y-m-d'),
                        'snippet' => $reference->snippet,
                        'similarity_score' => (float) $reference->similarity_score,
                        'rank' => (int) $reference->rank,
                        'knowledge_base_version' => $reference->knowledge_base_version,
                    ])
                    ->values()
                    ->all();

                $explanation = $explainer->explain(
                    $classification,
                    mb_substr((string) $submission->analysis_text, 0, 1500),
                    $evidence,
                );
                $state->markExplained($submission, $explanation, $this->attempts());
                $state->markCompleted($submission, $this->attempts());
            } catch (Throwable $exception) {
                $this->handlePipelineFailure($submission, ProcessingStage::Explaining, $exception, $state, $this->tries, $this->backoff());
            }
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

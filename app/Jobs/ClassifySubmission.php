<?php

namespace App\Jobs;

use App\Contracts\Classifier;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Jobs\Concerns\UniqueSubmissionStage;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifySubmission implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels, UniqueSubmissionStage;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return config('services.bert.backoff', [5, 15, 45, 120, 300]);
    }

    public function handle(
        Classifier $classifier,
        ProcessingEventRecorder $events,
        SubmissionStateMachine $state,
        PipelineFailureReporter $failures,
    ): void {
        Cache::lock("submission:{$this->submissionId}:classify", 60)->block(5, function () use ($classifier, $events, $state, $failures): void {
            $submission = Submission::findOrFail($this->submissionId);

            if ($submission->isTerminal()) {
                return;
            }

            $existing = DetectionResult::where('submission_id', $submission->id)->first();

            if ($existing !== null) {
                $this->dispatchRetrieval($submission, $events, $failures);

                return;
            }

            $started = hrtime(true);

            try {
                $state->markProcessing($submission, ProcessingStage::Classifying, $this->attempts());
                $classification = $classifier->classify((string) $submission->analysis_text);
                $state->markClassified($submission, $classification, $this->attempts(), (int) ((hrtime(true) - $started) / 1_000_000));
            } catch (Throwable $exception) {
                $this->handlePipelineFailure($submission, ProcessingStage::Classifying, $exception, $state, $this->tries, $this->backoff());

                return;
            }

            $this->dispatchRetrieval($submission, $events, $failures);
        });
    }

    private function dispatchRetrieval(
        Submission $submission,
        ProcessingEventRecorder $events,
        PipelineFailureReporter $failures,
    ): void {
        try {
            RetrieveSubmissionEvidence::dispatch($submission->getKey())->onQueue('retrieval');

            return;
        } catch (Throwable $exception) {
            $ragFailure = AiServiceException::transient(
                'rag',
                'Retrieval could not be added to its queue.',
                previous: $exception,
            );
            $attempt = max(1, $this->attempts());

            try {
                $failure = $failures->report($submission, ProcessingStage::Retrieving, $ragFailure, $attempt);
                $events->record(
                    $submission,
                    ProcessingStage::Retrieving,
                    EventOutcome::Degraded,
                    $failure->service,
                    $attempt,
                    errorCode: $failure->errorCode,
                    metadata: ['error_reference' => $failure->reference],
                );
            } catch (Throwable $auditException) {
                Log::warning('RAG retrieval could not be queued or fully audited.', [
                    'submission_id' => $submission->getKey(),
                    'stage' => ProcessingStage::Retrieving->value,
                    'exception_class' => $exception::class,
                    'audit_exception_class' => $auditException::class,
                ]);
            }

            try {
                GenerateSubmissionExplanation::dispatch($submission->getKey())->onQueue('explanation');
            } catch (Throwable $dispatchException) {
                try {
                    $failures->report($submission, ProcessingStage::Explaining, $dispatchException, $attempt);
                } catch (Throwable $auditException) {
                    Log::warning('Explanation could not be queued after RAG queue failure.', [
                        'submission_id' => $submission->getKey(),
                        'stage' => ProcessingStage::Explaining->value,
                        'exception_class' => $dispatchException::class,
                        'audit_exception_class' => $auditException::class,
                    ]);
                }
            }
        }
    }

    /**
     * Final failure handler — marks the submission as failed so it never stays
     * in "processing" after the last retry (C7).
     */
    public function failed(Throwable $exception): void
    {
        $submission = Submission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        try {
            app(SubmissionStateMachine::class)->markFailedFinal(
                $submission,
                ProcessingStage::Classifying,
                $exception,
                $this->attempts(),
            );
        } catch (Throwable) {
        }
    }
}

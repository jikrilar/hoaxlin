<?php

namespace App\Jobs;

use App\Contracts\EvidenceRetriever;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\Concerns\UniqueSubmissionStage;
use App\Models\Submission;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Rag\EvidenceReferencePersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetrieveSubmissionEvidence implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UniqueSubmissionStage;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $submissionId)
    {
        $this->onQueue('retrieval');
    }

    public function backoff(): array
    {
        return config('pipeline.retry.backoff', [5, 15, 60]);
    }

    public function handle(
        EvidenceRetriever $retriever,
        EvidenceReferencePersister $persister,
        ProcessingEventRecorder $events,
        SubmissionStateMachine $state,
        PipelineFailureReporter $failures,
    ): void {
        Cache::lock("submission:{$this->submissionId}:retrieve", 45)->block(5, function () use ($retriever, $persister, $events, $state, $failures): void {
            $submission = Submission::with('detectionResult')->findOrFail($this->submissionId);

            if ($submission->isTerminal() || $submission->detectionResult === null) {
                return;
            }

            $started = hrtime(true);
            $attempt = max(1, $this->attempts());

            try {
                $state->markProcessing($submission, ProcessingStage::Retrieving, $attempt);
                $query = mb_substr(trim((string) $submission->analysis_text), 0, 4000);
                $evidence = $retriever->retrieve($query);
                $persister->persist($submission, $evidence);

                $events->record(
                    $submission,
                    ProcessingStage::Retrieving,
                    EventOutcome::Succeeded,
                    'rag',
                    $attempt,
                    (int) ((hrtime(true) - $started) / 1_000_000),
                    metadata: ['result_count' => count($evidence)],
                );
            } catch (Throwable $exception) {
                try {
                    $failure = $failures->report($submission, ProcessingStage::Retrieving, $exception, $attempt);
                    $events->record(
                        $submission,
                        ProcessingStage::Retrieving,
                        EventOutcome::Degraded,
                        $failure->service,
                        $attempt,
                        (int) ((hrtime(true) - $started) / 1_000_000),
                        errorCode: $failure->errorCode,
                        metadata: ['error_reference' => $failure->reference],
                    );
                } catch (Throwable $auditException) {
                    Log::warning('RAG retrieval failed and its processing event could not be recorded.', [
                        'submission_id' => $submission->getKey(),
                        'stage' => ProcessingStage::Retrieving->value,
                        'exception_class' => $exception::class,
                        'audit_exception_class' => $auditException::class,
                    ]);
                }

            }

            GenerateSubmissionExplanation::dispatch($submission->getKey())->onQueue('explanation');
        });
    }

    /**
     * Queue-level failures must not turn a successful classification into a
     * failed submission. Make one best-effort attempt to continue to explanation.
     */
    public function failed(Throwable $exception): void
    {
        $submission = Submission::with('detectionResult')->find($this->submissionId);
        if (! $submission || $submission->isTerminal() || $submission->detectionResult === null) {
            return;
        }

        try {
            $failure = app(PipelineFailureReporter::class)->report(
                $submission,
                ProcessingStage::Retrieving,
                $exception,
                max(1, $this->attempts()),
            );
            app(ProcessingEventRecorder::class)->record(
                $submission,
                ProcessingStage::Retrieving,
                EventOutcome::Degraded,
                $failure->service,
                max(1, $this->attempts()),
                errorCode: $failure->errorCode,
                metadata: ['error_reference' => $failure->reference],
            );
        } catch (Throwable $auditException) {
            Log::warning('RAG retrieval job failed and could not be fully audited.', [
                'submission_id' => $submission->getKey(),
                'stage' => ProcessingStage::Retrieving->value,
                'exception_class' => $exception::class,
                'audit_exception_class' => $auditException::class,
            ]);
        }

        try {
            GenerateSubmissionExplanation::dispatch($submission->getKey())->onQueue('explanation');
        } catch (Throwable $dispatchException) {
            app(PipelineFailureReporter::class)->report(
                $submission,
                ProcessingStage::Retrieving,
                $dispatchException,
                max(1, $this->attempts()),
            );
        }
    }
}

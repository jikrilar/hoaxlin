<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Enums\EventOutcome;
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
use Throwable;

class TranslateSubmissionText implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return config('services.openai.backoff', [10, 60, 180]);
    }

    public function handle(Translator $translator, SubmissionStateMachine $state, ProcessingEventRecorder $events): void
    {
        Cache::lock("submission:{$this->submissionId}:translate", 75)->block(5, function () use ($translator, $state, $events): void {
            $submission = Submission::findOrFail($this->submissionId);

            if (filled($submission->source_language)) {
                $this->continueToClassification($submission->id);

                return;
            }

            $started = hrtime(true);
            $state->markProcessing($submission, ProcessingStage::Translating, $this->attempts());

            try {
                $translation = $translator->translate((string) $submission->extracted_text);
                $submission->update([
                    'source_language' => $translation->sourceLanguage,
                    'translated_text' => $translation->translated ? $translation->text : null,
                    'translation_provider' => $translation->provider,
                    'translation_model' => $translation->model,
                    'translation_cached' => $translation->cached,
                    'translation_input_tokens' => $translation->inputTokens,
                    'translation_output_tokens' => $translation->outputTokens,
                    'translation_estimated_cost_usd' => $translation->estimatedCostUsd,
                ]);
                $events->record(
                    $submission,
                    ProcessingStage::Translating,
                    EventOutcome::Succeeded,
                    $translation->provider,
                    $this->attempts(),
                    (int) ((hrtime(true) - $started) / 1_000_000),
                    metadata: [
                        'source_language' => $translation->sourceLanguage,
                        'translated' => $translation->translated,
                        'model' => $translation->model,
                        'cached' => $translation->cached,
                    ],
                );
                $this->continueToClassification($submission->id);
            } catch (Throwable $exception) {
                $state->markFailed($submission, ProcessingStage::Translating, $exception, $this->attempts());
            }
        });
    }

    private function continueToClassification(int $submissionId): void
    {
        ClassifySubmission::dispatch($submissionId)->onQueue('inference');
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
                ProcessingStage::Translating,
                $exception,
                $this->attempts(),
            );
        } catch (Throwable) {
        }
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Exceptions\SanitizedPipelineException;
use App\Jobs\ClassifySubmission;
use App\Models\Submission;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\TimeoutExceededException;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

class PipelineRetryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permanent_provider_error_fails_immediately_without_retry_event(): void
    {
        [$job, $submission] = $this->runFailure(
            AiServiceException::permanent('bert', 'provider secret', 422),
        );

        $job->assertFailedWith(SanitizedPipelineException::class)->assertNotReleased();
        $submission->refresh();

        $this->assertSame('failed', $submission->status);
        $this->assertSame(1, $submission->attempt_count);
        $this->assertSame(0, $submission->processingEvents()->where('outcome', EventOutcome::Retried->value)->count());
        $this->assertSame(1, $submission->processingEvents()->where('outcome', EventOutcome::Failed->value)->count());
    }

    public function test_connection_error_is_released_with_defined_backoff(): void
    {
        [$job, $submission] = $this->runFailure(new ConnectionException('connection secret'));

        $job->assertReleased(5)->assertNotFailed();
        $submission->refresh();

        $this->assertSame('processing', $submission->status);
        $this->assertSame(1, $submission->attempt_count);
        $this->assertSame(1, $submission->processingEvents()->where('outcome', EventOutcome::Retried->value)->count());
    }

    public function test_timeout_is_classified_as_retryable(): void
    {
        $failure = app(PipelineFailureReporter::class)->describe(new TimeoutExceededException('timed out with secret'));

        $this->assertTrue($failure->retryable);
        $this->assertSame(PipelineFailureReporter::DEPENDENCY_UNAVAILABLE, $failure->errorCode);
        $this->assertStringNotContainsString('secret', $failure->publicMessage);
    }

    public function test_http_429_uses_provider_retry_after(): void
    {
        [$job, $submission] = $this->runFailure(
            AiServiceException::transient('bert', 'rate limit secret', 429, 120),
        );

        $job->assertReleased(120)->assertNotFailed();
        $this->assertSame('processing', $submission->fresh()->status);
    }

    public function test_retry_after_is_bounded_to_safe_maximum(): void
    {
        config()->set('pipeline.retry.max_delay_seconds', 900);
        [$job] = $this->runFailure(
            AiServiceException::transient('openai', 'rate limit secret', 429, 99_999),
        );

        $job->assertReleased(900);
    }

    public function test_http_5xx_is_retryable_and_http_4xx_is_permanent(): void
    {
        [$serverJob, $serverSubmission] = $this->runFailure(
            AiServiceException::transient('openai', 'server secret', 503),
        );
        $serverJob->assertReleased(5)->assertNotFailed();
        $this->assertSame('processing', $serverSubmission->fresh()->status);

        [$clientJob, $clientSubmission] = $this->runFailure(
            AiServiceException::permanent('openai', 'auth secret', 401),
        );
        $clientJob->assertFailed()->assertNotReleased();
        $this->assertSame('failed', $clientSubmission->fresh()->status);
    }

    public function test_transient_error_at_max_attempts_becomes_terminal_failed(): void
    {
        [$job, $submission] = $this->runFailure(
            AiServiceException::transient('bert', 'server secret', 500),
            5,
        );

        $job->assertFailed()->assertNotReleased();
        $submission->refresh();

        $this->assertSame('failed', $submission->status);
        $this->assertSame(5, $submission->attempt_count);
        $event = $submission->processingEvents()->latest('id')->firstOrFail();
        $this->assertSame(EventOutcome::Failed, $event->outcome);
        $this->assertSame(5, $event->attempt);
    }

    public function test_final_failure_callback_is_idempotent(): void
    {
        $exception = AiServiceException::permanent('bert', 'provider secret', 400);
        [$job, $submission] = $this->runFailure($exception);
        $count = $submission->processingEvents()->count();

        $job->failed($job->job->failedWith);

        $this->assertSame($count, $submission->processingEvents()->count());
        $this->assertSame('failed', $submission->fresh()->status);
    }

    public function test_failed_submission_is_not_returned_to_processing_by_redelivery(): void
    {
        [$job, $submission] = $this->runFailure(
            AiServiceException::permanent('bert', 'contract secret', 422),
        );
        $classifier = $this->mock(Classifier::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('classify');
        });
        $redelivered = (new ClassifySubmission($submission->id))->withFakeQueueInteractions();

        $redelivered->handle(
            $classifier,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        $job->assertFailed();
        $redelivered->assertNotFailed()->assertNotReleased();
        $this->assertSame('failed', $submission->fresh()->status);
    }

    /**
     * @return array{ClassifySubmission, Submission}
     */
    private function runFailure(Throwable $exception, int $attempt = 1): array
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk menguji kebijakan retry. ', 3),
            'extracted_text' => str_repeat('Berita untuk menguji kebijakan retry. ', 3),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ]);
        $classifier = $this->mock(Classifier::class, function (MockInterface $mock) use ($exception): void {
            $mock->shouldReceive('classify')->once()->andThrow($exception);
        });
        $job = (new ClassifySubmission($submission->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        $job->handle(
            $classifier,
            app(ProcessingEventRecorder::class),
            app(SubmissionStateMachine::class),
        );

        return [$job, $submission];
    }
}

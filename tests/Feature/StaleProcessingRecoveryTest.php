<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Jobs\GenerateSubmissionExplanation;
use App\Jobs\ProcessSubmission;
use App\Jobs\RetrieveSubmissionEvidence;
use App\Jobs\TranslateSubmissionText;
use App\Models\Submission;
use App\Services\Pipeline\StaleSubmissionRecovery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class StaleProcessingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pipeline.stale_after_minutes' => 10]);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_recent_processing_submission_is_not_stale_or_touched(): void
    {
        Queue::fake();
        $submission = $this->processingSubmission(ProcessingStage::Classifying);
        $originalUpdatedAt = $submission->updated_at;

        $this->artisan('submissions:recover-stale')
            ->expectsOutput('Found 0 stale in-flight submissions (threshold: 10 minutes).')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertTrue($submission->fresh()->updated_at->equalTo($originalUpdatedAt));
        $this->assertDatabaseCount('submission_processing_events', 0);
    }

    public function test_processing_past_configured_threshold_is_detected_in_dry_run(): void
    {
        Queue::fake();
        $submission = $this->makeStale($this->processingSubmission(ProcessingStage::Classifying));

        $this->artisan('submissions:recover-stale', ['--dry-run' => true])
            ->expectsOutput('Found 1 stale in-flight submissions (threshold: 10 minutes).')
            ->expectsOutput('Dry run - no state changed and no jobs dispatched.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertTrue($submission->updated_at->equalTo($submission->fresh()->updated_at));
        $this->assertDatabaseCount('submission_processing_events', 0);
    }

    public function test_completed_and_failed_submissions_are_not_recovered_as_stale_processing(): void
    {
        Queue::fake();

        $this->makeStale($this->processingSubmission(ProcessingStage::Done, ['status' => 'completed']));
        $this->makeStale($this->processingSubmission(ProcessingStage::Classifying, ['status' => 'failed']));

        $this->artisan('submissions:recover-stale')
            ->expectsOutput('Found 0 stale in-flight submissions (threshold: 10 minutes).')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('submission_processing_events', 0);
    }

    public function test_stale_classifying_submission_restarts_only_classification(): void
    {
        Queue::fake();
        $submission = $this->makeStale($this->processingSubmission(ProcessingStage::Classifying));

        $this->artisan('submissions:recover-stale')
            ->expectsOutput('Recovery queues: inference=1.')
            ->assertSuccessful();

        Queue::assertPushed(ClassifySubmission::class, function (ClassifySubmission $job): bool {
            return $job->queue === 'inference';
        });
        Queue::assertNotPushed(GenerateSubmissionExplanation::class);
        $this->assertSame('processing', $submission->fresh()->status);
    }

    public function test_stale_pending_submission_is_requeued_from_the_queued_stage(): void
    {
        Queue::fake();
        $submission = $this->makeStale(Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita pending untuk watchdog. ', 3),
            'status' => 'pending',
            'processing_stage' => ProcessingStage::Queued->value,
        ]));

        $this->artisan('submissions:recover-stale')->assertSuccessful();

        Queue::assertPushed(ProcessSubmission::class, function (ProcessSubmission $job): bool {
            return $job->queue === 'default';
        });
        $this->assertSame('pending', $submission->fresh()->status);
    }

    public function test_stale_submission_with_result_resumes_at_retrieval_without_duplicate_result(): void
    {
        Queue::fake();
        $submission = $this->processingSubmission(ProcessingStage::Classifying);
        $submission->detectionResult()->create([
            'label' => 'hoax',
            'confidence_score' => 0.94,
            'model_version' => 'test',
            'explanation_status' => 'pending',
        ]);
        $this->makeStale($submission);

        $this->artisan('submissions:recover-stale')->assertSuccessful();

        Queue::assertPushed(RetrieveSubmissionEvidence::class, function (RetrieveSubmissionEvidence $job): bool {
            return $job->queue === 'retrieval';
        });
        Queue::assertNotPushed(ClassifySubmission::class);
        Queue::assertNotPushed(GenerateSubmissionExplanation::class);
        $this->assertDatabaseCount('detection_results', 1);
        $this->assertSame('processing', $submission->fresh()->status);
    }

    public function test_repeated_watchdog_runs_do_not_duplicate_recovery_job(): void
    {
        Queue::fake();
        $submission = $this->makeStale($this->processingSubmission(ProcessingStage::Classifying));

        $this->artisan('submissions:recover-stale')->assertSuccessful();
        $this->artisan('submissions:recover-stale')->assertSuccessful();

        Queue::assertPushed(ClassifySubmission::class, 1);
        $this->assertDatabaseCount('submission_processing_events', 1);
        $this->assertTrue($submission->fresh()->updated_at->equalTo(now()));
    }

    public function test_recovery_records_an_auditable_processing_event(): void
    {
        Queue::fake();
        $submission = $this->makeStale($this->processingSubmission(ProcessingStage::Classifying));

        $this->artisan('submissions:recover-stale')->assertSuccessful();

        $event = $submission->processingEvents()->sole();
        $this->assertSame(ProcessingStage::Classifying, $event->stage);
        $this->assertSame(EventOutcome::Retried, $event->outcome);
        $this->assertSame('watchdog', $event->service);
        $this->assertSame('STALE_PROCESSING', $event->error_code);
        $this->assertSame('ClassifySubmission', $event->metadata['recovery_job']);
        $this->assertSame('inference', $event->metadata['queue']);
        $this->assertSame(10, $event->metadata['stale_after_minutes']);
    }

    public function test_unsafe_stale_stage_without_required_result_is_failed_safely(): void
    {
        Queue::fake();
        $submission = $this->makeStale($this->processingSubmission(ProcessingStage::Explaining));

        $this->artisan('submissions:recover-stale')->assertSuccessful();

        Queue::assertNothingPushed();
        $submission->refresh();
        $this->assertSame('failed', $submission->status);
        $this->assertSame('watchdog', $submission->last_error_service);
        $this->assertSame('STALE_RECOVERY_UNSAFE', $submission->last_error_code);
        $this->assertSame(EventOutcome::Failed, $submission->processingEvents()->sole()->outcome);
    }

    public function test_pipeline_stage_jobs_are_unique_per_submission_for_the_stale_window(): void
    {
        $submission = $this->processingSubmission(ProcessingStage::Queued);
        $jobs = [
            new ProcessSubmission($submission),
            new ExtractSubmissionText($submission->id),
            new TranslateSubmissionText($submission->id),
            new ClassifySubmission($submission->id),
            new RetrieveSubmissionEvidence($submission->id),
            new GenerateSubmissionExplanation($submission->id),
        ];

        foreach ($jobs as $job) {
            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertSame((string) $submission->id, $job->uniqueId());
            $this->assertSame(600, $job->uniqueFor());
        }
    }

    public function test_horizon_workers_cover_every_pipeline_queue(): void
    {
        $queues = collect(config('horizon.environments.production'))
            ->flatMap(fn (array $supervisor): array => $supervisor['queue'])
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'default',
            'extract-text',
            'extract-media',
            'inference',
            'retrieval',
            'explanation',
        ], $queues);
    }

    public function test_recovery_command_is_scheduled_without_overlap(): void
    {
        $event = collect(Schedule::events())->first(
            fn ($event): bool => str_contains($event->command ?? '', 'submissions:recover-stale'),
        );

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_stale_scope_uses_the_single_configured_threshold(): void
    {
        $recent = $this->processingSubmission(ProcessingStage::Classifying);
        $stale = $this->makeStale($this->processingSubmission(ProcessingStage::Classifying));

        $ids = app(StaleSubmissionRecovery::class)->staleQuery()->pluck('id');

        $this->assertFalse($ids->contains($recent->id));
        $this->assertTrue($ids->contains($stale->id));
    }

    /** @param array<string, mixed> $overrides */
    private function processingSubmission(ProcessingStage $stage, array $overrides = []): Submission
    {
        return Submission::create(array_merge([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk watchdog processing. ', 3),
            'status' => 'processing',
            'processing_stage' => $stage->value,
            'processing_started_at' => now(),
        ], $overrides));
    }

    private function makeStale(Submission $submission): Submission
    {
        $staleAt = now()->subMinutes(11);
        DB::table('submissions')->where('id', $submission->id)->update(['updated_at' => $staleAt]);

        return $submission->fresh();
    }
}

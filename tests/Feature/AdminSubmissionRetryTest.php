<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\Pages\ViewSubmission;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Jobs\ProcessSubmission;
use App\Models\AdminLog;
use App\Models\Submission;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Pipeline\SubmissionRecovery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** T3.4: retrying a submission from the admin panel, without a terminal. */
class AdminSubmissionRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        Queue::fake();
        config(['pipeline.stale_after_minutes' => 10]);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_retry_requeues_a_failed_submission_and_clears_the_failure(): void
    {
        $admin = $this->admin();
        $submission = $this->failed();

        Livewire::test(ListSubmissions::class)
            ->callTableAction('retry', $submission)
            ->assertHasNoTableActionErrors();

        Queue::assertPushed(ProcessSubmission::class, 1);
        $submission->refresh();
        $this->assertSame('pending', $submission->status);
        $this->assertSame(ProcessingStage::Queued->value, $submission->processing_stage);
        $this->assertNull($submission->failure_reason);
        $this->assertNull($submission->last_error_code);
        $this->assertNull($submission->last_error_service);
        $this->assertNull($submission->processing_completed_at);
        $this->assertSame($admin->id, AdminLog::query()->withAction('submission_retried')->sole()->admin_id);
    }

    public function test_retry_action_is_only_offered_for_failed_or_stalled_submissions(): void
    {
        $this->admin();
        $failed = $this->failed();
        $stalled = Submission::factory()->create(['status' => 'processing', 'processing_stage' => ProcessingStage::Classifying->value, 'updated_at' => now()->subMinutes(11)]);
        $fresh = Submission::factory()->create(['status' => 'processing', 'updated_at' => now()->subMinute()]);
        $completed = Submission::factory()->create(['status' => 'completed']);
        $pending = Submission::factory()->create(['status' => 'pending']);

        $page = Livewire::test(ListSubmissions::class);

        foreach ([$failed, $stalled] as $retryable) {
            $page->assertTableActionVisible('retry', $retryable);
        }
        // A fresh pending/processing row is normal work in progress, not stalled.
        foreach ([$fresh, $completed, $pending] as $blocked) {
            $page->assertTableActionHidden('retry', $blocked);
        }
    }

    public function test_retry_is_idempotent_and_never_duplicates_the_job(): void
    {
        $this->admin();
        $submission = $this->failed();

        Livewire::test(ListSubmissions::class)->callTableAction('retry', $submission);

        // The row is no longer failed, so the action disappears and a second
        // attempt through the service is refused.
        Livewire::test(ListSubmissions::class)->assertTableActionHidden('retry', $submission->fresh());
        $this->assertFalse(app(SubmissionRecovery::class)->retry($submission->fresh()));

        Queue::assertPushed(ProcessSubmission::class, 1);
    }

    public function test_retry_is_rejected_for_a_submission_that_moved_on(): void
    {
        $this->admin();
        $submission = $this->failed();
        $submission->update(['status' => 'completed']);

        $result = app(SubmissionRecovery::class)->retry($submission->fresh());

        $this->assertFalse($result);
        Queue::assertNothingPushed();
    }

    public function test_bulk_retry_recovers_only_eligible_rows(): void
    {
        $this->admin();
        $first = $this->failed();
        $second = $this->failed();
        $completed = Submission::factory()->create(['status' => 'completed']);

        Livewire::test(ListSubmissions::class)
            ->callTableBulkAction('retrySelected', [$first, $second, $completed])
            ->assertHasNoTableBulkActionErrors();

        Queue::assertPushed(ProcessSubmission::class, 2);
        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame('completed', $completed->fresh()->status);
    }

    public function test_retry_writes_an_allowlisted_admin_log_without_user_content(): void
    {
        $admin = $this->admin();
        $submission = $this->failed(['raw_input' => 'PRIVATE_RETRY_INPUT', 'failure_reason' => 'PRIVATE_RETRY_REASON']);

        Livewire::test(ListSubmissions::class)->callTableAction('retry', $submission);

        $log = AdminLog::query()->withAction('submission_retried')->sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('submissions', $log->target_table);
        $this->assertSame($submission->id, $log->target_id);
        $this->assertSame(1, $log->metadata['count']);
        $this->assertSame([$submission->id], $log->metadata['submission_ids']);
        $encoded = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_RETRY_INPUT', $encoded);
        $this->assertStringNotContainsString('PRIVATE_RETRY_REASON', $encoded);
        $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()
            ->assertSee('Pemeriksaan dicoba ulang')
            ->assertDontSee('PRIVATE_RETRY_INPUT');
    }

    public function test_retry_invalidates_the_dashboard_cache_so_numbers_change_immediately(): void
    {
        $this->admin();
        $submission = $this->failed();

        // Warm the aggregates the dashboard reads.
        $metrics = app(DashboardMetrics::class);
        $this->assertSame(1, $metrics->needsAction()['failed']);
        $this->assertSame(1, $metrics->kpis(30)['failed']);

        Livewire::test(ListSubmissions::class)->callTableAction('retry', $submission);

        // No waiting for the 60-second window: the next read is already fresh.
        $this->assertSame(0, $metrics->needsAction()['failed']);
        $this->assertSame(0, $metrics->kpis(30)['failed']);
    }

    public function test_retry_modal_copy_matches_the_real_behaviour(): void
    {
        $this->admin();
        $failed = $this->failed();
        $stalled = Submission::factory()->create([
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
            'updated_at' => now()->subMinutes(11),
        ]);

        $page = Livewire::test(ListSubmissions::class);
        $page->mountTableAction('retry', $failed);
        $failedCopy = $page->instance()->getMountedAction()->getModalDescription();
        $page->unmountAction();

        $page->mountTableAction('retry', $stalled);
        $stalledCopy = $page->instance()->getMountedAction()->getModalDescription();

        $this->assertStringContainsString('diulang dari awal', $failedCopy);
        $this->assertStringContainsString('dilanjutkan dari tahap terakhir', $stalledCopy);
        $this->assertNotSame($failedCopy, $stalledCopy);
    }

    public function test_retry_requires_an_admin(): void
    {
        $submission = $this->failed();
        $this->get(SubmissionResource::getUrl('index'))->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(SubmissionResource::getUrl('index'))->assertForbidden();
        $this->assertSame('failed', $submission->fresh()->status);
        Queue::assertNothingPushed();
        $this->assertSame(0, AdminLog::count());
    }

    public function test_retry_is_available_on_the_detail_page_too(): void
    {
        $this->admin();
        $submission = $this->failed();

        Livewire::test(ViewSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('retry')
            ->assertHasNoActionErrors();

        Queue::assertPushed(ProcessSubmission::class, 1);
        $this->assertSame('pending', $submission->fresh()->status);
    }

    public function test_detail_page_hides_retry_once_the_submission_is_completed(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'completed']);

        Livewire::test(ViewSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertActionHidden('retry');
    }

    public function test_retry_never_requeues_a_completed_submission_even_under_concurrency(): void
    {
        $this->admin();
        $submission = $this->failed();

        // Simulate the job finishing between the click and the update.
        $first = app(SubmissionRecovery::class)->retry($submission);
        $submission->update(['status' => 'completed']);
        $second = app(SubmissionRecovery::class)->retry($submission->fresh());

        $this->assertTrue($first);
        $this->assertFalse($second);
        Queue::assertPushed(ProcessSubmission::class, 1);
    }

    public function test_recovery_service_keeps_the_command_behaviour_for_failed_submissions(): void
    {
        $recent = $this->failed();
        $old = $this->failed();
        DB::table('submissions')->where('id', $old->id)->update(['processing_completed_at' => now()->subDays(30)]);

        $this->artisan('submissions:recover', ['--days' => 7])->assertSuccessful();

        Queue::assertPushed(ProcessSubmission::class, 1);
        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame('failed', $old->fresh()->status);
    }

    public function test_recovery_service_can_target_one_submission_and_honour_dry_run(): void
    {
        $target = $this->failed();
        $other = $this->failed();

        $this->artisan('submissions:recover', ['--id' => $target->id, '--dry-run' => true])
            ->expectsOutput('Found 1 failed submissions to recover.')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('submissions:recover', ['--id' => $target->id])->assertSuccessful();
        Queue::assertPushed(ProcessSubmission::class, 1);
        $this->assertSame('pending', $target->fresh()->status);
        $this->assertSame('failed', $other->fresh()->status);
    }

    private function failed(array $overrides = []): Submission
    {
        return Submission::factory()->create(array_merge([
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Classifying->value,
            'failure_reason' => 'Layanan klasifikasi tidak tersedia.',
            'last_error_service' => 'bert',
            'last_error_code' => 'DEPENDENCY_UNAVAILABLE',
            'processing_completed_at' => now(),
            'attempt_count' => 3,
        ], $overrides));
    }

    private function admin(): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}

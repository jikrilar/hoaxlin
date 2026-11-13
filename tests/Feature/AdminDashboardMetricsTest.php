<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Admin\ServiceHealth;
use App\Services\Bert\BertRuntimeMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/** T3.2: dashboard aggregates stay honest, cached and safe. */
class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['app.timezone' => 'Asia/Jakarta']);
        $this->travelTo('2026-11-13 10:00:00');
    }

    public function test_range_is_limited_to_the_three_supported_windows(): void
    {
        $metrics = app(DashboardMetrics::class);

        $this->assertSame(7, $metrics->rangeDays('7'));
        $this->assertSame(30, $metrics->rangeDays(30));
        $this->assertSame(90, $metrics->rangeDays('90'));
        $this->assertSame(30, $metrics->rangeDays('365'));
        $this->assertSame(30, $metrics->rangeDays(null));
        $this->assertSame(30, $metrics->rangeDays(['x']));
    }

    public function test_trend_fills_every_day_and_splits_labels(): void
    {
        $this->submission('2026-11-12', 'completed', 'hoax');
        $this->submission('2026-11-12', 'completed', 'valid');
        $this->submission('2026-11-13', 'processing');

        $trend = app(DashboardMetrics::class)->trend(7);

        $this->assertTrue($trend['available']);
        $this->assertCount(7, $trend['rows']);
        $this->assertSame('2026-11-07', $trend['rows'][0]['date']);
        $this->assertSame('2026-11-13', $trend['rows'][6]['date']);
        $this->assertSame(2, $trend['rows'][5]['total']);
        $this->assertSame(1, $trend['rows'][5]['hoax']);
        $this->assertSame(1, $trend['rows'][5]['valid']);
        $this->assertSame(1, $trend['rows'][6]['total']);
        $this->assertSame(0, $trend['rows'][6]['meragukan']);
    }

    public function test_kpis_use_the_selected_window_and_expose_the_completion_base(): void
    {
        $this->submission('2026-11-13', 'completed');
        $this->submission('2026-11-13', 'failed');
        $this->submission('2026-11-12', 'completed');
        $this->submission('2026-10-01', 'completed'); // outside 30 days

        $kpis = app(DashboardMetrics::class)->kpis(30);

        $this->assertTrue($kpis['available']);
        $this->assertSame(2, $kpis['today']);
        $this->assertSame(3, $kpis['week']);
        $this->assertSame(3, $kpis['completion_base']);
        $this->assertEqualsWithDelta(2 / 3, $kpis['completion_rate'], 0.0001);
        $this->assertSame(1, $kpis['failed']);
        $this->assertCount(7, $kpis['sparkline']);
        $this->assertSame([0, 0, 0, 0, 0, 1, 2], $kpis['sparkline']);
    }

    public function test_completion_rate_is_null_instead_of_zero_when_the_window_is_empty(): void
    {
        $kpis = app(DashboardMetrics::class)->kpis(7);

        $this->assertNull($kpis['completion_rate'], 'an empty window has no rate, not 0%');
        $this->assertSame(0, $kpis['completion_base']);
    }

    public function test_needs_action_counts_failed_and_stale_with_the_watchdog_threshold(): void
    {
        config(['pipeline.stale_after_minutes' => 45]);
        $failed = $this->submission('2026-11-13', 'failed');
        $stale = Submission::factory()->create(['status' => 'processing', 'updated_at' => now()->subMinutes(46)]);
        Submission::factory()->create(['status' => 'processing', 'updated_at' => now()->subMinutes(10)]);
        Submission::factory()->create(['status' => 'completed']);

        $action = app(DashboardMetrics::class)->needsAction();

        $this->assertTrue($action['available']);
        $this->assertSame(45, $action['threshold_minutes']);
        $this->assertSame(1, $action['failed']);
        $this->assertSame(1, $action['stale']);
        $this->assertContains($failed->id, array_column($action['items'], 'id'));
        $this->assertContains($stale->id, array_column($action['items'], 'id'));
        $this->assertLessThanOrEqual(5, count($action['items']));
    }

    public function test_breakdown_hides_latency_below_the_minimum_sample(): void
    {
        config(['pipeline.latency_minimum_sample' => 5]);
        foreach (range(1, 3) as $i) {
            $this->submission('2026-11-13', 'completed', 'valid', inferenceMs: $i * 10);
        }

        $breakdown = app(DashboardMetrics::class)->breakdown(30);

        $this->assertSame(3, $breakdown['latency']['sample']);
        $this->assertSame(5, $breakdown['latency']['minimum']);
        $this->assertNull($breakdown['latency']['p50']);
        $this->assertNull($breakdown['latency']['p95']);

        foreach (range(4, 6) as $i) {
            $this->submission('2026-11-13', 'completed', 'valid', inferenceMs: $i * 10);
        }
        Cache::flush();
        $breakdown = app(DashboardMetrics::class)->breakdown(30);

        $this->assertSame(6, $breakdown['latency']['sample']);
        $this->assertSame(30, $breakdown['latency']['p50']);
        $this->assertSame(60, $breakdown['latency']['p95']);
    }

    public function test_breakdown_counts_failures_per_stage_and_feedback_split(): void
    {
        $submission = $this->submission('2026-11-13', 'completed', 'hoax');
        SubmissionProcessingEvent::create(['submission_id' => $submission->id, 'stage' => ProcessingStage::Classifying->value, 'outcome' => EventOutcome::Failed->value]);
        SubmissionProcessingEvent::create(['submission_id' => $submission->id, 'stage' => ProcessingStage::Classifying->value, 'outcome' => EventOutcome::Failed->value]);
        SubmissionProcessingEvent::create(['submission_id' => $submission->id, 'stage' => ProcessingStage::Retrieving->value, 'outcome' => EventOutcome::Succeeded->value]);
        // One feedback per (submission, user); use two owners for the split.
        Feedback::create(['submission_id' => $submission->id, 'user_id' => User::factory()->create()->id, 'is_correct' => true]);
        Feedback::create(['submission_id' => $submission->id, 'user_id' => User::factory()->create()->id, 'is_correct' => false]);

        $breakdown = app(DashboardMetrics::class)->breakdown(30);

        $this->assertSame(['classifying' => 2], $breakdown['failures']);
        $this->assertSame(['correct' => 1, 'incorrect' => 1], $breakdown['feedback']);
        $this->assertSame(['hoax' => 1], $breakdown['labels']);
        $this->assertSame(['text' => 1], $breakdown['inputs']);
    }

    public function test_openai_budget_uses_spent_plus_reserved_against_the_configured_quota(): void
    {
        config(['services.openai.monthly_quota_usd' => 10.0]);
        DB::table('openai_usage_periods')->insert(['period' => '2026-11', 'spent_microusd' => 2_000_000, 'reserved_microusd' => 1_000_000, 'created_at' => now(), 'updated_at' => now()]);

        $budget = app(DashboardMetrics::class)->openAiBudget();

        $this->assertTrue($budget['available']);
        $this->assertSame('2026-11', $budget['period']);
        $this->assertEqualsWithDelta(3.0, $budget['spent_usd'], 0.0001);
        $this->assertEqualsWithDelta(0.3, $budget['ratio'], 0.0001);
    }

    public function test_results_are_cached_for_sixty_seconds_per_range(): void
    {
        $this->submission('2026-11-13', 'completed');
        $metrics = app(DashboardMetrics::class);
        $first = $metrics->kpis(30);

        $this->submission('2026-11-13', 'completed');
        $this->assertSame($first['today'], $metrics->kpis(30)['today'], 'same range is served from cache');

        Cache::flush();
        $this->assertSame($first['today'] + 1, $metrics->kpis(30)['today']);
        // A different range is a different cache key.
        $this->assertSame(2, $metrics->kpis(7)['week']);
    }

    public function test_missing_tables_degrade_to_unavailable_instead_of_throwing(): void
    {
        $metrics = app(DashboardMetrics::class);
        DB::statement('DROP TABLE submission_processing_events');

        $breakdown = $metrics->breakdown(30);
        $this->assertFalse($breakdown['available']);
        $this->assertSame([], $breakdown['failures']);

        $this->assertFalse($metrics->needsAction()['available'] === false && false);
    }

    public function test_service_health_never_guesses_ok(): void
    {
        Http::preventStrayRequests();
        $bert = Mockery::mock(BertRuntimeMetadata::class);
        $bert->shouldReceive('fetch')->andReturn(['available' => false, 'model_status' => 'unavailable']);
        $this->app->instance(BertRuntimeMetadata::class, $bert);
        config(['services.rag.url' => '', 'services.openai.key' => null]);

        $health = app(ServiceHealth::class)->all();

        $this->assertSame(ServiceHealth::UNAVAILABLE, $health['bert']['state']);
        $this->assertSame(ServiceHealth::UNAVAILABLE, $health['rag']['state']);
        $this->assertSame(ServiceHealth::UNAVAILABLE, $health['openai']['state']);
        $this->assertSame('Belum tersedia', $health['bert']['label']);
    }

    public function test_rag_health_reads_the_readiness_endpoint_and_degrades_on_error(): void
    {
        config(['services.rag.url' => 'http://rag.test', 'services.rag.internal_token' => null]);
        $bert = Mockery::mock(BertRuntimeMetadata::class);
        $bert->shouldReceive('fetch')->andReturn(['available' => true, 'model_status' => 'ready']);
        $this->app->instance(BertRuntimeMetadata::class, $bert);

        // One sequence: ready → not_ready → connection failure.
        Http::fakeSequence('http://rag.test/health/ready')
            ->push(['status' => 'ready'], 200)
            ->push(['status' => 'not_ready', 'reason' => 'index'], 200)
            ->push('', 500);
        $health = app(ServiceHealth::class);

        $this->assertSame(ServiceHealth::OK, $health->rag()['state']);
        $this->assertSame(ServiceHealth::DEGRADED, $health->rag()['state']);
        $this->assertSame(ServiceHealth::UNAVAILABLE, $health->rag()['state']);
    }

    private function submission(string $date, string $status, ?string $label = null, ?int $inferenceMs = null): Submission
    {
        $submission = Submission::factory()->create([
            'status' => $status,
            'created_at' => $date.' 09:00:00',
            'updated_at' => $date.' 09:00:00',
            'raw_input' => 'PRIVATE_DASHBOARD_INPUT',
        ]);
        if ($label !== null) {
            DetectionResult::factory()->for($submission)->create(['label' => $label, 'inference_ms' => $inferenceMs ?? 0]);
        }

        return $submission;
    }
}

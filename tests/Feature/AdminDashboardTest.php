<?php

namespace Tests\Feature;

use App\Filament\Widgets\ModelVersionWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SubmissionTrendChart;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private array $runtimePayload = [];

    private int $runtimeStatus = 503;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Jakarta', 'app.debug' => false, 'services.bert.url' => 'http://bert.test']);
        $this->travelTo(CarbonImmutable::parse('2026-03-31 12:00:00', 'Asia/Jakarta'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        Http::fake(['http://bert.test/version' => fn () => Http::response($this->runtimePayload, $this->runtimeStatus)]);
    }

    public function test_dashboard_orders_operational_widgets_and_keeps_native_panel_access(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertSame([StatsOverview::class, SubmissionTrendChart::class, ModelVersionWidget::class], array_values($panel->getWidgets()));
        $this->get($panel->getUrl())->assertRedirect($panel->getLoginUrl());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($panel->getUrl())->assertForbidden();
        $this->admin();
        $response = $this->get($panel->getUrl())->assertOk()->assertSee('Ringkasan operasi')->assertSee('Tren submission')->assertSee('Model dan evaluasi');
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Tren submission'), strpos($html, 'Ringkasan operasi'));
        $this->assertLessThan(strpos($html, 'Model dan evaluasi'), strpos($html, 'Tren submission'));
        $response->assertDontSee('fi-account-widget')->assertDontSee('fi-wi-stats-overview-stat');
    }

    public function test_counts_preserve_all_time_nonfinal_labels_and_exclude_private_records(): void
    {
        $this->admin();
        $owner = User::factory()->create(['name' => 'PRIVATE_ADMIN_OWNER', 'email' => 'private-owner@example.test']);
        foreach ([['2024-01-01', 'completed', 'hoax'], ['2026-03-31', 'pending', null], ['2026-03-31', 'processing', 'valid'], ['2026-03-15', 'failed', 'meragukan'], ['2026-03-05', 'completed', 'hoax']] as [$date, $status, $label]) {
            $submission = Submission::factory()->for($owner)->create(['created_at' => $date.' 12:00:00', 'status' => $status, 'raw_input' => 'PRIVATE_ADMIN_INPUT', 'failure_reason' => 'PRIVATE_ADMIN_ERROR']);
            if ($label) {
                DetectionResult::factory()->for($submission)->create(['label' => $label, 'explanation' => 'PRIVATE_ADMIN_EXPLANATION']);
            }
        }
        DB::table('failed_jobs')->insert(['uuid' => 'fixture-job', 'connection' => 'fixture', 'queue' => 'default', 'payload' => 'PRIVATE_ADMIN_PAYLOAD', 'exception' => 'PRIVATE_ADMIN_TRACE', 'failed_at' => now()]);
        $stats = Livewire::test(StatsOverview::class);
        $data = $stats->instance()->render()->getData();
        $this->assertSame(['5', '2', '2', '1', '1'], array_map(fn ($stat) => $stat->getValue(), $data['stats']));
        $this->assertSame([1, 2, 1], array_column($data['labels'], 'count'));
        $stats->assertSee('semua versi model')->assertSee('termasuk hasil pada submission nonfinal')->assertSee('Informasi belum terverifikasi oleh sumber terpercaya')->assertSee('bukan bukti informasi benar atau palsu');
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk()->assertDontSee('PRIVATE_ADMIN_')->assertDontSee('private-owner@example.test');
    }

    public function test_trend_uses_exact_thirty_dates_and_two_grouped_queries_without_changing_populations(): void
    {
        foreach ([['2026-03-01 23:59:59', 'hoax'], ['2026-03-02 00:00:00', 'hoax'], ['2026-03-31 23:59:59', 'hoax'], ['2026-04-01 00:00:00', 'hoax']] as [$date, $label]) {
            $submission = Submission::factory()->create(['created_at' => $date, 'status' => 'processing']);
            DetectionResult::factory()->for($submission)->create(['label' => $label]);
        }
        Submission::factory()->create(['created_at' => '2026-03-31 00:00:00', 'status' => 'pending']);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $widget = new SubmissionTrendChart;
        $trend = $widget->getTrend();
        $this->assertCount(2, DB::connection()->getQueryLog());
        $this->assertCount(30, $trend['rows']);
        $this->assertSame('2026-03-02', $trend['rows'][0]['date']);
        $this->assertSame('2026-03-31', $trend['rows'][29]['date']);
        $this->assertSame([1, 1], [$trend['rows'][0]['total'], $trend['rows'][0]['hoax']]);
        $this->assertSame([2, 1], [$trend['rows'][29]['total'], $trend['rows'][29]['hoax']]);
        $this->assertSame('Asia/Jakarta', $trend['timezone']);
        $chart = (new ReflectionMethod($widget, 'getData'))->invoke($widget);
        $this->assertSame(array_column($trend['rows'], 'total'), $chart['datasets'][0]['data']);
        $this->assertSame(array_column($trend['rows'], 'hoax'), $chart['datasets'][1]['data']);
        $this->assertSame([6, 4], $chart['datasets'][1]['borderDash']);
        $this->assertCount(2, DB::connection()->getQueryLog());
    }

    public function test_empty_data_is_real_zero_and_empty_chart_is_distinct_from_unavailable(): void
    {
        $this->admin();
        Livewire::test(StatsOverview::class)->assertSee('Belum ada hasil dengan label tersimpan.');
        $widget = Livewire::test(SubmissionTrendChart::class)->assertSee('Belum ada submission dalam periode ini');
        $this->assertCount(30, $widget->instance()->getTrend()['rows']);
        $this->assertSame(0, array_sum(array_column($widget->instance()->getTrend()['rows'], 'total')));
        $this->assertSame([], (new ReflectionMethod($widget->instance(), 'getData'))->invoke($widget->instance()));
        Livewire::test(ModelVersionWidget::class)->assertSee('n/a')->assertSee('Runtime BERT tidak tersedia')->assertSee('Belum ada riwayat versi.');
    }

    public function test_runtime_provenance_and_top_three_groups_are_escaped_without_promoting_history_to_active(): void
    {
        $this->admin();
        foreach (['v-old', 'v-old', 'v-two', 'v-three', 'v-four', 'v-five'] as $version) {
            DetectionResult::factory()->create(['model_version' => $version]);
        }
        $this->runtimeStatus = 200;
        $this->runtimePayload = [
            'model_status' => 'ready', 'model_version' => 'v-active', 'threshold' => 0.77,
            'evaluation_model_version' => 'v-active', 'evaluation_accuracy' => 0.88, 'evaluation_sample_count' => 120,
            'evaluation_dataset_name' => '<script id="attack">corpus</script>'.str_repeat('long', 60), 'evaluation_dataset_version' => 'v2',
            'evaluation_split' => 'held-out test', 'exported_at' => '2026-09-01T00:00:00Z', 'api_key' => 'PRIVATE_ADMIN_API_KEY',
        ];
        $widget = Livewire::test(ModelVersionWidget::class)->assertSee('v-active')->assertSee('0.7700')->assertSee('88.00%')->assertSee('held-out test')->assertSee('n=120')->assertSee('Versi dataset v2')->assertSee('bukan jumlah seluruh versi model')
            ->assertDontSee('<script id="attack">', false)->assertDontSee('PRIVATE_ADMIN_API_KEY');
        $data = $widget->instance()->render()->getData();
        $this->assertCount(3, $data['history']['rows']);
        $this->assertSame('3 kelompok teratas', $data['stats'][3]->getValue());
        $this->assertSame('v-old', $data['history']['rows'][0]['version']);
        $this->assertSame(2, $data['history']['rows'][0]['count']);
        $this->assertSame('v-active', $data['stats'][0]->getValue());
    }

    public function test_mismatched_evaluation_keeps_active_runtime_but_suppresses_metric(): void
    {
        $this->admin();
        $this->runtimeStatus = 200;
        $this->runtimePayload = ['model_status' => 'ready', 'model_version' => 'v-active', 'evaluation_model_version' => 'v-old', 'evaluation_accuracy' => 1];
        Livewire::test(ModelVersionWidget::class)->assertSee('v-active')->assertSee('Artefak evaluasi model aktif tidak tersedia.')->assertDontSee('100.00%');
    }

    public function test_database_failure_renders_unavailable_instead_of_false_zeros_or_private_exception(): void
    {
        $this->admin();
        $fail = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$fail): void {
            if ($fail && (str_contains($query, 'submissions') || str_contains($query, 'detection_results'))) {
                throw new QueryException('sqlite', $query, [], new PDOException('PRIVATE_ADMIN_TRACE sk-fixture-secret'));
            }
        });
        try {
            $ops = Livewire::test(StatsOverview::class)->assertSee('Ringkasan operasi belum tersedia (n/a)')->assertDontSee('PRIVATE_ADMIN_TRACE')->assertDontSee('Belum ada hasil');
            $this->assertSame([null, null, null], array_column($ops->instance()->render()->getData()['labels'], 'count'));
            Livewire::test(SubmissionTrendChart::class)->assertSee('Data tren belum tersedia')->assertDontSee('PRIVATE_ADMIN_TRACE')->assertDontSee('Belum ada submission');
            Livewire::test(ModelVersionWidget::class)->assertSee('Riwayat versi belum tersedia (n/a)')->assertDontSee('PRIVATE_ADMIN_TRACE');
        } finally {
            $fail = false;
        }
    }

    #[DataProvider('widgets')]
    public function test_widget_mount_and_hydration_use_the_same_admin_rule_without_requiring_verification(string $class): void
    {
        Livewire::test($class)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Livewire::test($class)->assertForbidden();
        $admin = $this->admin();
        $component = Livewire::test($class)->assertStatus(200);
        $admin->update(['is_admin' => false]);
        $this->actingAs($admin->fresh());
        $component->call('$refresh')->assertForbidden();
    }

    public static function widgets(): array
    {
        return [[StatsOverview::class], [SubmissionTrendChart::class], [ModelVersionWidget::class]];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true, 'email_verified_at' => null]);
        $this->actingAs($user);

        return $user;
    }
}

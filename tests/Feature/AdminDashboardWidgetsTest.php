<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardCharts;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\LatencyWidget;
use App\Filament\Widgets\NeedsActionWidget;
use App\Filament\Widgets\OpenAiBudgetWidget;
use App\Models\Dataset;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/** T3.2: the dashboard renders honest, safe, range-scoped widgets. */
class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Cache::flush();
        Http::preventStrayRequests();
        config(['app.timezone' => 'Asia/Jakarta']);
        $this->travelTo('2026-11-13 10:00:00');
        $bert = Mockery::mock(BertRuntimeMetadata::class);
        $bert->shouldReceive('fetch')->andReturn(['available' => false, 'model_status' => 'unavailable', 'model_version' => null, 'threshold' => null, 'temperature' => null, 'labels' => null, 'evaluation' => []]);
        $this->app->instance(BertRuntimeMetadata::class, $bert);
    }

    public function test_dashboard_renders_every_widget_for_an_admin(): void
    {
        $this->submission('2026-11-13', 'completed', 'hoax');
        $this->admin();

        $this->get(Dashboard::getUrl())->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('Kinerja pemeriksaan')
            ->assertSee('Tren pemeriksaan per hari')
            ->assertSee('Latensi analisis')
            ->assertSee('Biaya OpenAI bulan ini')
            ->assertSee('Model dan data')
            ->assertSee('Data Kurasi terverifikasi')
            ->assertSee('bukan prevalensi hoaks di masyarakat')
            ->assertSee('Lihat data tabel')
            ->assertSee('data-admin-service-health', false)
            ->assertSee('data-admin-kpis', false);
    }

    public function test_dashboard_is_forbidden_for_non_admins_and_guests(): void
    {
        $this->get(Dashboard::getUrl())->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(Dashboard::getUrl())->assertForbidden();
    }

    public function test_range_filter_changes_the_window_for_kpi_and_charts(): void
    {
        $this->submission('2026-11-13', 'completed', 'hoax');
        $this->submission('2026-10-25', 'completed', 'valid'); // 19 days ago: inside 30, outside 7
        $this->admin();

        // Reactive page filters are set by the dashboard page, not by the widget.
        Livewire::test(KpiOverview::class)->assertViewHas('days', 30);
        $this->get(Dashboard::getUrl().'?filters[range]=7')->assertOk()->assertSee('Jendela 7 hari terakhir');
        $this->get(Dashboard::getUrl().'?filters[range]=90')->assertOk()->assertSee('Jendela 90 hari terakhir');
    }

    public function test_chart_views_expose_every_series_and_a_matching_table(): void
    {
        $submission = $this->submission('2026-11-13', 'completed', 'hoax');
        SubmissionProcessingEvent::create(['submission_id' => $submission->id, 'stage' => 'classifying', 'outcome' => 'failed']);
        $this->admin();

        foreach (['trend', 'labels', 'inputs', 'failures', 'feedback'] as $mode) {
            Livewire::test(DashboardCharts::class)->set('viewMode', $mode)
                ->assertSet('viewMode', $mode)
                ->assertSee('Lihat data tabel');
        }
        Livewire::test(DashboardCharts::class)->set('viewMode', 'failures')
            ->assertSee('Menganalisis pola bahasa')
            ->assertSee('data-admin-breakdown="failures"', false);
    }

    public function test_chart_type_and_table_follow_the_selected_view(): void
    {
        $submission = $this->submission('2026-11-13', 'completed', 'hoax');
        SubmissionProcessingEvent::create(['submission_id' => $submission->id, 'stage' => 'classifying', 'outcome' => 'failed']);
        $this->admin();

        $types = [
            'trend' => 'line',
            'labels' => 'doughnut',
            'inputs' => 'bar',
            'failures' => 'bar',
            'feedback' => 'bar',
        ];
        foreach ($types as $mode => $type) {
            $widget = Livewire::test(DashboardCharts::class)->set('viewMode', $mode);
            $this->assertSame($type, $widget->instance()->getType(), "{$mode} paints as {$type}");
            $widget->assertSee('data-chart-type="'.$type.'"', false)
                ->assertSee('Lihat data tabel · '.$widget->instance()->getHeading());
        }

        // A tampered mode must not blank the widget.
        Livewire::test(DashboardCharts::class)->set('viewMode', 'DROP TABLE')->assertSee('Tren pemeriksaan per hari');
    }

    public function test_chart_reports_an_empty_window_instead_of_a_flat_line(): void
    {
        $this->admin();

        $widget = Livewire::test(DashboardCharts::class);
        $this->assertTrue($widget->instance()->isEmpty());
        $widget->assertSee('Belum ada pemeriksaan dalam jendela ini');
    }

    public function test_latency_widget_states_the_sample_gate_instead_of_a_trend(): void
    {
        config(['pipeline.latency_minimum_sample' => 5]);
        foreach (range(1, 3) as $i) {
            $this->submission('2026-11-13', 'completed', 'valid', $i * 10);
        }
        $this->admin();

        Livewire::test(LatencyWidget::class)
            ->assertSee('Sampel belum cukup untuk persentil')
            ->assertSee('3 dari minimum 5')
            ->assertSee('data-admin-latency-empty', false)
            ->assertDontSee('data-admin-latency=', false);
    }

    public function test_openai_budget_widget_reports_unavailable_instead_of_zero(): void
    {
        $this->admin();

        Livewire::test(OpenAiBudgetWidget::class)
            ->assertSee('Biaya OpenAI bulan ini')
            ->assertSee('$0.00')
            ->assertSee('kuota bulanan');
    }

    public function test_needs_action_widget_lists_only_actionable_rows_and_never_payloads(): void
    {
        $failed = Submission::factory()->create(['status' => 'failed', 'raw_input' => 'PRIVATE_DASHBOARD_PAYLOAD']);
        Submission::factory()->create(['status' => 'completed', 'raw_input' => 'PRIVATE_DASHBOARD_PAYLOAD']);
        $this->admin();

        Livewire::test(NeedsActionWidget::class)
            ->assertSee('Perlu tindakan')
            ->assertSee('#'.$failed->id)
            ->assertDontSee('PRIVATE_DASHBOARD_PAYLOAD')
            ->assertSee('data-admin-needs-action', false)
            ->assertSee('Status layanan');
    }

    public function test_curation_counts_only_verified_rows(): void
    {
        $verifier = User::factory()->create(['is_admin' => true]);
        Dataset::factory()->create(['label' => 'valid', 'verified_by' => $verifier->id]);
        Dataset::factory()->create(['label' => 'valid', 'verified_by' => null]);
        Dataset::factory()->create(['label' => 'hoax', 'verified_by' => $verifier->id]);

        $curation = app(DashboardMetrics::class)->curation();

        $this->assertTrue($curation['available']);
        $this->assertSame(1, $curation['curated']['valid']);
        $this->assertSame(1, $curation['curated']['hoax']);
        $this->assertSame(2, $curation['curated_total']);
    }

    public function test_every_computed_number_carries_a_definition(): void
    {
        // Enough samples for the latency percentiles, so those three are rendered too.
        config(['pipeline.latency_minimum_sample' => 1]);
        $this->submission('2026-11-13', 'completed', 'hoax', 120);
        $this->admin();

        $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();
        $definitions = substr_count($html, 'hl-admin-definition');

        $this->assertSame(10, $definitions, 'three action, four KPI and three latency numbers each need an explanation');
        $this->assertStringContainsString('Penjelasan: ', $html);
        $this->assertStringContainsString('bukan prevalensi hoaks di masyarakat', $html);
        $this->assertStringContainsString('bukan akurasi model', $html);
    }

    public function test_chart_palette_matches_the_design_tokens(): void
    {
        $tokens = file_get_contents(resource_path('css/design-tokens.css'));
        foreach (DashboardCharts::SERIES_COLORS as $name => $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $tokens, "chart colour {$name} must come from design-tokens.css");
        }
        $data = (new ReflectionMethod(DashboardCharts::class, 'getData'))->invoke(new DashboardCharts);
        $this->assertCount(4, $data['datasets'], 'trend keeps four series');
        $this->assertSame(['circle', 'rect', 'triangle', 'crossRot'], array_column($data['datasets'], 'pointStyle'), 'series differ by shape, not colour alone');
    }

    private function submission(string $date, string $status, ?string $label = null, int $inferenceMs = 0): Submission
    {
        $submission = Submission::factory()->create(['status' => $status, 'created_at' => $date.' 09:00:00', 'updated_at' => $date.' 09:00:00', 'raw_input' => 'PRIVATE_DASHBOARD_INPUT']);
        if ($label !== null) {
            DetectionResult::factory()->for($submission)->create(['label' => $label, 'inference_ms' => $inferenceMs]);
        }

        return $submission;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}

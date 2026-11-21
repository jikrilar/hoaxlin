<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardCharts;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\LatencyWidget;
use App\Filament\Widgets\ModelVersionWidget;
use App\Filament\Widgets\NeedsActionWidget;
use App\Filament\Widgets\OpenAiBudgetWidget;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** T3.2: dashboard composition, provenance and degradation. */
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
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['http://bert.test/version' => fn () => Http::response($this->runtimePayload, $this->runtimeStatus)]);
    }

    public function test_dashboard_orders_widgets_by_action_kpi_chart_then_model_context(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertSame([
            NeedsActionWidget::class,
            KpiOverview::class,
            DashboardCharts::class,
            LatencyWidget::class,
            OpenAiBudgetWidget::class,
            ModelVersionWidget::class,
        ], array_values($panel->getWidgets()));

        $this->get($panel->getUrl())->assertRedirect($panel->getLoginUrl());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($panel->getUrl())->assertForbidden();

        $this->admin();
        $html = $this->get($panel->getUrl())->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('Kinerja pemeriksaan')
            ->assertSee('Tren pemeriksaan per hari')
            ->assertSee('Latensi analisis')
            ->assertSee('Biaya OpenAI bulan ini')
            ->assertSee('Model dan data')
            ->getContent();

        $order = ['Perlu tindakan', 'Kinerja pemeriksaan', 'Tren pemeriksaan per hari', 'Latensi analisis', 'Biaya OpenAI bulan ini', 'Model dan data'];
        $positions = array_map(fn (string $needle): int => (int) strpos($html, $needle), $order);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'widgets render in the documented order');
        $this->assertStringNotContainsString('fi-account-widget', $html);
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
            Livewire::test(NeedsActionWidget::class)->assertSee('Data tindakan belum tersedia')->assertDontSee('PRIVATE_ADMIN_TRACE');
            Livewire::test(KpiOverview::class)->assertSee('Data kinerja belum tersedia')->assertDontSee('PRIVATE_ADMIN_TRACE')->assertDontSee('data-admin-kpis', false);
            Livewire::test(DashboardCharts::class)->assertSee('Lihat data tabel')->assertDontSee('PRIVATE_ADMIN_TRACE');
            Livewire::test(LatencyWidget::class)->assertSee('Sampel belum cukup')->assertDontSee('PRIVATE_ADMIN_TRACE');
            Livewire::test(ModelVersionWidget::class)->assertSee('Riwayat versi belum tersedia')->assertDontSee('PRIVATE_ADMIN_TRACE');
        } finally {
            $fail = false;
        }
    }

    public function test_private_submission_content_never_reaches_the_dashboard(): void
    {
        $owner = User::factory()->create(['name' => 'PRIVATE_ADMIN_OWNER', 'email' => 'private-owner@example.test']);
        $submission = Submission::factory()->for($owner)->create([
            'status' => 'failed', 'raw_input' => 'PRIVATE_ADMIN_INPUT', 'failure_reason' => 'PRIVATE_ADMIN_ERROR',
        ]);
        DetectionResult::factory()->for($submission)->create(['label' => 'hoax', 'explanation' => 'PRIVATE_ADMIN_EXPLANATION']);
        DB::table('failed_jobs')->insert(['uuid' => 'fixture-job', 'connection' => 'fixture', 'queue' => 'default', 'payload' => 'PRIVATE_ADMIN_PAYLOAD', 'exception' => 'PRIVATE_ADMIN_TRACE', 'failed_at' => now()]);
        $this->admin();

        $this->get(Filament::getPanel('admin')->getUrl())->assertOk()
            ->assertSee('#'.$submission->id)
            ->assertDontSee('PRIVATE_ADMIN_')
            ->assertDontSee('private-owner@example.test');
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
        return [
            [NeedsActionWidget::class], [KpiOverview::class], [DashboardCharts::class],
            [LatencyWidget::class], [OpenAiBudgetWidget::class], [ModelVersionWidget::class],
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true, 'email_verified_at' => null]);
        $this->actingAs($user);

        return $user;
    }
}

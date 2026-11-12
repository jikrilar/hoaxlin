<?php

namespace Tests\Feature;

use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Filament\Resources\ModelVersions\Pages\ListModelVersions;
use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\AdminGlossary;
use App\Filament\Widgets\ModelVersionWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\Submission;
use App\Models\User;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** T1.7: admin technical terms carry plain-language explanations and no "n/a" leftovers. */
class AdminJargonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        $this->mockRuntime(['available' => false, 'model_version' => null, 'evaluation' => []]);
    }

    public function test_glossary_covers_every_admin_term_with_a_plain_explanation(): void
    {
        foreach (['threshold', 'f1_score', 'baseline', 'baseline_comparison', 'hot_swap', 'incremental_fine_tuning', 'active_inference_status', 'failed_jobs', 'pipeline_stage', 'attempts', 'error_code', 'not_available'] as $term) {
            $this->assertNotSame(AdminGlossary::NOT_AVAILABLE, AdminGlossary::explain($term), $term.' needs an explanation.');
            $this->assertLessThanOrEqual(200, mb_strlen(AdminGlossary::explain($term)));
        }
        $this->assertSame('Belum tersedia', AdminGlossary::NOT_AVAILABLE);
    }

    public function test_model_version_table_columns_explain_threshold_f1_baseline_and_activation(): void
    {
        $this->admin();
        $table = Livewire::test(ListModelVersions::class)->instance()->getTable();

        $this->assertSame(AdminGlossary::explain('f1_score'), $table->getColumn('f1_score')->getTooltip());
        $this->assertSame(AdminGlossary::explain('baseline_comparison'), $table->getColumn('baseline_accuracy')->getTooltip());
        $this->assertSame(AdminGlossary::explain('active_inference_status'), $table->getColumn('is_active')->getTooltip());
    }

    public function test_activation_action_explains_hot_swap_and_baseline_comparison_limit(): void
    {
        $this->admin();
        $description = ModelVersionResource::activationDescription('v1.1.0', 'Akurasi +1.00% (baseline 90.00%)');

        $this->assertStringContainsString('tanpa memulai ulang layanan', $description);
        $this->assertStringContainsString('tidak menjamin kualitas pada data baru', $description);
        $this->assertStringContainsString('Akurasi +1.00%', $description);
    }

    public function test_dashboard_widgets_use_plain_language_instead_of_na(): void
    {
        $this->admin();
        $html = Livewire::test(ModelVersionWidget::class)->html();

        $this->assertStringNotContainsString('n/a', $html);
        $this->assertStringContainsString('Belum tersedia', $html);
        $this->assertStringContainsString(AdminGlossary::explain('threshold'), $html);
        $this->assertStringContainsString(AdminGlossary::explain('active_inference_status'), $html);
    }

    public function test_processing_event_screens_explain_stage_attempts_and_error_code(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'processing']);
        $event = $submission->processingEvents()->create([
            'stage' => 'classifying', 'outcome' => 'failed', 'service' => 'bert',
            'attempt' => 2, 'duration_ms' => 1200, 'error_code' => 'timeout',
        ]);

        $this->get(SubmissionProcessingEventResource::getUrl('view', ['record' => $event]))
            ->assertOk()
            ->assertSee(AdminGlossary::explain('pipeline_stage'))
            ->assertSee(AdminGlossary::explain('attempts'))
            ->assertSee(AdminGlossary::explain('error_code'));
    }

    public function test_submission_detail_explains_stage_and_confirmation_notices_are_plain(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'processing', 'processing_stage' => 'classifying']);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee(AdminGlossary::explain('pipeline_stage'));

        foreach ([ModelVersionResource::TRAINING_NOTICE, DatasetResource::CURATION_NOTICE] as $notice) {
            $this->assertStringNotContainsString('incremental fine-tuning', $notice);
            $this->assertStringNotContainsString('hot-swap', $notice);
            $this->assertStringContainsString('model aktif', $notice);
        }
        $this->assertStringContainsString('tidak otomatis', ModelVersionResource::TRAINING_NOTICE);
    }

    public function test_failed_jobs_stat_is_renamed_and_explained(): void
    {
        $this->admin();
        $html = Livewire::test(StatsOverview::class)->html();

        $this->assertStringNotContainsString('Entri failed jobs', $html);
        $this->assertStringContainsString('Tugas latar belakang gagal', $html);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    /** @param array<string, mixed> $payload */
    private function mockRuntime(array $payload): void
    {
        $runtime = Mockery::mock(BertRuntimeMetadata::class);
        $runtime->shouldReceive('fetch')->andReturn($payload + [
            'available' => false,
            'model_version' => null,
            'threshold' => null,
            'evaluation' => [],
        ]);
        $this->app->instance(BertRuntimeMetadata::class, $runtime);
    }
}

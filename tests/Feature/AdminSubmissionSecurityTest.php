<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\Pages\ViewSubmission;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\AdminGlossary;
use App\Filament\Support\MonitoringPresentation;
use App\Models\AdminLog;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T3.6: security and authorization assertions moved from the deleted
 * Hasil Deteksi and Processing Events screens onto Pemeriksaan.
 *
 * Nothing here is new coverage invented for the move: each test carries the
 * assertions those two resources used to own (panel access, read-only shape,
 * partial-record honesty, and the operational metadata allowlist), so deleting
 * the resources cannot delete the guarantees with them.
 */
class AdminSubmissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    public function test_submission_screens_require_panel_admin_and_stay_read_only(): void
    {
        $record = Submission::factory()->create();

        foreach (['index', 'view'] as $page) {
            $url = SubmissionResource::getUrl($page, ['record' => $record]);
            $this->get($url)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
            $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
            $this->admin();
            $response = $this->get($url)->assertOk()->assertDontSee('data-admin-form-runtime-required');
            if ($page === 'index') {
                $response->assertSee('data-admin-readonly-runtime-required');
            } else {
                $response->assertDontSee('data-admin-readonly-runtime-required');
            }
            auth()->logout();
        }

        $this->admin();
        $this->assertSame(['index', 'view'], array_keys(SubmissionResource::getPages()));
        $this->assertFalse(SubmissionResource::canCreate());
        $this->assertFalse(SubmissionResource::canEdit($record));
        $this->assertFalse(SubmissionResource::canDelete($record));
        $this->assertFalse(SubmissionResource::canDeleteAny());
        $this->get(SubmissionResource::getUrl('index').'/create')->assertNotFound();
        $this->get(SubmissionResource::getUrl('view', ['record' => $record]).'/edit')->assertNotFound();
        Livewire::test(ListSubmissions::class)
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertActionDoesNotExist('create');
        $this->assertModelExists($record);
        Http::assertNothingSent();
    }

    public function test_classifier_metadata_is_readable_from_the_submission_detail(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'completed', 'processing_stage' => 'done']);
        DetectionResult::factory()->for($submission)->create([
            'label' => 'valid', 'confidence_score' => 0.125, 'model_version' => 'indobert-hoax-v1.0.0',
            'explanation_status' => 'unavailable', 'explanation' => null,
        ]);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Prediksi model: Valid')
            ->assertSee('12,5%')
            ->assertSee('indobert-hoax-v1.0.0')
            ->assertSee('Record classifier pada submission selesai')
            ->assertSee('Penjelasan tidak tersedia.');
    }

    public function test_processing_metadata_projection_stays_allowlisted_on_the_detail_page(): void
    {
        $this->admin();
        Bus::fake();
        $secret = 'PRIVATE_EVENT_SECRET_FIXTURE';
        $metadata = [
            'cached' => false, 'translated' => true, 'result_count' => 0, 'stale_after_minutes' => 10,
            'error_reference' => '12345678-1234-1234-1234-123456789abc', 'source_language' => 'en',
            'queue' => 'inference', 'recovery_job' => 'ClassifySubmission',
            'model_version' => str_repeat($secret, 200), 'model' => ['api_key' => $secret],
            'authorization' => $secret, 'guest_access_token_hash' => $secret, 'raw_provider_response' => ['body' => $secret],
        ];
        $submission = Submission::factory()->create(['status' => 'completed', 'processing_stage' => 'done']);
        $event = $this->event($submission, [
            'metadata' => $metadata, 'service' => $secret, 'error_code' => $secret, 'duration_ms' => 0,
        ]);

        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('0 ms')
            ->assertSee('Layanan tidak dikenali')
            ->assertSee('Kode tidak dikenali')
            ->assertSee('12345678-1234-1234-1234-123456789abc')
            ->assertSee('ClassifySubmission')
            ->assertSee('nilai disembunyikan')
            ->assertDontSee($secret);

        Livewire::test(ViewSubmission::class, ['record' => $submission->id])
            ->call('$refresh')->assertDontSee($secret)->assertSee('result_count');
        Livewire::test(ListSubmissions::class)->assertDontSee($secret);

        $this->assertSame($metadata, $event->fresh()->metadata);
        $this->assertSame($secret, $event->fresh()->service);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
        $response->assertDontSee('raw_provider_response');
    }

    public function test_processing_metadata_empty_and_unknown_values_keep_honest_placeholders(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'completed', 'processing_stage' => 'done']);
        $this->event($submission, ['metadata' => null, 'duration_ms' => null, 'service' => null, 'error_code' => null]);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Metadata tidak tersedia.')
            ->assertSee('Durasi tidak tercatat')
            ->assertSee('Layanan tidak tercatat')
            ->assertSee('Kode kendala tidak tercatat');
    }

    public function test_log_detail_still_projects_long_changes_without_credentials_or_free_text(): void
    {
        $this->admin();
        $secret = 'PRIVATE_AUDIT_SECRET_FIXTURE';
        $actor = User::factory()->create(['name' => '<script id="actor-attack">literal</script>'.str_repeat(' panjang', 20)]);
        $changes = array_fill_keys((new Submission)->getFillable(), $secret);
        $changes = array_replace($changes, ['status' => 'processing', 'processing_stage' => 'classifying', 'input_type' => 'video', 'user_id' => 0, 'attempt_count' => 2, 'translation_cached' => false, 'unknown_payload' => [$secret => $secret]]);
        $record = AdminLog::factory()->create([
            'admin_id' => $actor->id, 'action' => 'update', 'target_table' => 'submissions',
            'metadata' => ['model' => Submission::class, 'changes' => $changes, 'cookie' => $secret],
        ]);

        $safe = MonitoringPresentation::logMetadata($record);
        $this->assertGreaterThan(1000, strlen($safe));
        $this->assertStringContainsString('pipeline_version', $safe);
        $this->assertStringNotContainsString($secret, $safe);
        $this->assertStringNotContainsString('unknown_payload', $safe);

        $this->get(AdminLogResource::getUrl('view', ['record' => $record]))->assertOk()
            ->assertSee($actor->name)->assertSee('pipeline_version')->assertSee('video')
            ->assertSee('nilai disembunyikan')->assertDontSee($secret)
            ->assertDontSee('<script id="actor-attack">', false);
    }

    public function test_deleted_resources_no_longer_expose_routes_or_classes(): void
    {
        $this->assertFalse(class_exists(DetectionResultResource::class));
        $this->assertFalse(class_exists(SubmissionProcessingEventResource::class));

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/detection-results')->assertNotFound();
        $this->get('/admin/submission-processing-events')->assertNotFound();
    }

    public function test_submission_detail_carries_the_stage_attempt_and_error_explanations(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'processing', 'processing_stage' => 'classifying']);
        $this->event($submission, ['stage' => 'classifying', 'outcome' => 'failed', 'service' => 'bert', 'attempt' => 2, 'duration_ms' => 1200, 'error_code' => 'timeout']);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee(AdminGlossary::explain('pipeline_stage'))
            ->assertSee(AdminGlossary::explain('attempts'))
            ->assertSee(AdminGlossary::explain('error_code'));
    }

    private function event(Submission $submission, array $data): SubmissionProcessingEvent
    {
        return SubmissionProcessingEvent::create(array_merge([
            'submission_id' => $submission->id,
            'stage' => ProcessingStage::Classifying->value,
            'outcome' => EventOutcome::Succeeded->value,
            'service' => 'bert',
            'attempt' => 1,
            'duration_ms' => 100,
        ], $data));
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}

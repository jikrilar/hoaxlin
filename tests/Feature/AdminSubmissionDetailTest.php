<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\DetectionResult;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use App\Services\Pipeline\StaleSubmissionRecovery;
use App\Services\Pipeline\SubmissionRecovery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** T3.5: action tabs on the list and a complete read-only detail page. */
class AdminSubmissionDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        config(['pipeline.stale_after_minutes' => 10]);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_list_defaults_to_all_and_offers_four_tabs_in_order(): void
    {
        $this->admin();

        $page = Livewire::test(ListSubmissions::class);
        $tabs = $page->instance()->getCachedTabs();

        $this->assertSame(['action', 'processing', 'completed', 'all'], array_keys($tabs));
        $this->assertSame(['Perlu tindakan', 'Diproses', 'Selesai', 'Semua'], array_map(fn ($tab): string => (string) $tab->getLabel(), array_values($tabs)));
        $this->assertSame('all', $page->instance()->activeTab, 'the default population stays "Semua"');
    }

    public function test_each_tab_badge_counts_its_own_population(): void
    {
        $this->admin();
        $this->failed();
        $this->failed();
        $this->stalled(ProcessingStage::Classifying);
        $this->fresh(ProcessingStage::Extracting);
        $this->completed();
        $this->completed();

        $tabs = Livewire::test(ListSubmissions::class)->instance()->getCachedTabs();

        // Failed (2) + stalled (1); the fresh in-flight row is not actionable.
        $this->assertSame(3, (int) $tabs['action']->getBadge());
        // Diproses keeps every in-flight row, including the stalled one.
        $this->assertSame(2, (int) $tabs['processing']->getBadge());
        $this->assertSame(2, (int) $tabs['completed']->getBadge());
        // 2 failed + 1 stalled + 1 fresh in-flight + 2 completed.
        $this->assertSame(6, (int) $tabs['all']->getBadge());
    }

    public function test_action_tab_contains_failed_and_stalled_rows_only(): void
    {
        $this->admin();
        $failed = $this->failed();
        $stalled = $this->stalled(ProcessingStage::Classifying);
        $fresh = $this->fresh(ProcessingStage::Extracting);
        $completed = $this->completed();

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'action')
            ->assertCanSeeTableRecords([$failed, $stalled])
            ->assertCanNotSeeTableRecords([$fresh, $completed]);
    }

    public function test_processing_tab_contains_every_in_flight_row_including_stalled(): void
    {
        $this->admin();
        $failed = $this->failed();
        $stalled = $this->stalled(ProcessingStage::Classifying);
        $fresh = $this->fresh(ProcessingStage::Extracting);
        $pending = Submission::factory()->create(['status' => 'pending', 'processing_stage' => ProcessingStage::Queued->value]);
        $completed = $this->completed();

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'processing')
            ->assertCanSeeTableRecords([$stalled, $fresh, $pending])
            ->assertCanNotSeeTableRecords([$failed, $completed]);
    }

    public function test_completed_tab_contains_only_completed_rows(): void
    {
        $this->admin();
        $failed = $this->failed();
        $processing = $this->fresh(ProcessingStage::Extracting);
        $completed = $this->completed();

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$failed, $processing]);
    }

    public function test_detail_moves_every_detection_result_field_into_the_submission_page(): void
    {
        $this->admin();
        $submission = $this->completed();
        $result = DetectionResult::factory()->for($submission)->create([
            'label' => 'hoax',
            'confidence_score' => 0.9234,
            'model_version' => 'indobert-hoax-v1.0.0',
            'inference_ms' => 143,
            'classifier_cached' => true,
            'explanation_status' => 'ready',
            'explanation' => 'Penjelasan tersimpan untuk audit.',
            'explanation_model' => 'explainer-model',
            'explanation_cached' => false,
        ]);

        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Prediksi model: Hoaks')
            ->assertSee('92,34%')
            ->assertSee('indobert-hoax-v1.0.0')
            ->assertSee('143 ms')
            ->assertSee('Cache classifier')
            ->assertSee('Cache penjelasan')
            ->assertSee('Penjelasan tersimpan untuk audit.')
            ->assertSee('explainer-model')
            ->assertSee('Record classifier pada submission selesai');

        $this->assertNotNull($result->fresh());
        $response->assertDontSee('href="javascript:', false);
    }

    public function test_detail_renders_evidence_in_rank_order_and_never_links_an_unsafe_url(): void
    {
        $this->admin();
        $submission = $this->completed();
        $this->evidence($submission, ['document_id' => 'doc-002', 'title' => 'Rujukan kedua', 'rank' => 2, 'similarity_score' => 0.71]);
        $this->evidence($submission, ['document_id' => 'doc-001', 'title' => 'Rujukan pertama', 'rank' => 1, 'similarity_score' => 0.93, 'source_url' => 'javascript:alert(1)']);
        $this->evidence($submission, ['document_id' => 'doc-003', 'title' => 'Rujukan ketiga', 'rank' => 3, 'similarity_score' => 0.42, 'source_url' => 'https://reference.example/doc-003']);

        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Rujukan pertama')
            ->assertSee('Rujukan kedua')
            ->assertSee('Rujukan ketiga')
            ->assertSeeInOrder(['Rujukan pertama', 'Rujukan kedua', 'Rujukan ketiga'])
            ->assertSee('https://reference.example/doc-003')
            ->assertDontSee('href="javascript:', false);

        // The unsafe URL is shown as text only: no anchor may point at it.
        $this->assertSame(0, substr_count($response->getContent(), 'href="javascript:alert(1)"'));
    }

    public function test_detail_shows_an_honest_empty_state_when_there_is_no_evidence(): void
    {
        $this->admin();
        $submission = $this->completed();

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Belum ada rujukan tersimpan untuk pemeriksaan ini.');
    }

    public function test_detail_timeline_lists_every_processing_event_with_only_allowlisted_metadata(): void
    {
        $this->admin();
        $submission = $this->completed();
        $first = $this->event($submission, ['stage' => 'extracting', 'outcome' => 'succeeded', 'service' => 'stored', 'attempt' => 1, 'duration_ms' => 12, 'created_at' => now()->subMinutes(3)]);
        $second = $this->event($submission, [
            'stage' => 'classifying', 'outcome' => 'failed', 'service' => 'bert', 'attempt' => 2, 'duration_ms' => 1250,
            'error_code' => 'DEPENDENCY_UNAVAILABLE', 'created_at' => now()->subMinutes(2),
            'metadata' => ['model_version' => 'indobert-hoax-v1.0.0', 'cached' => false, 'input_trace' => ['classifier_input' => 'PRIVATE_TIMELINE_INPUT'], 'secret' => 'PRIVATE_TIMELINE_SECRET'],
        ]);
        $third = $this->event($submission, ['stage' => 'done', 'outcome' => 'succeeded', 'attempt' => 2, 'created_at' => now()->subMinute()]);

        $response = $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Timeline pemrosesan')
            ->assertSee('Membaca teks dari input')
            ->assertSee('Menganalisis pola bahasa')
            ->assertSee('Berhasil')
            ->assertSee('Gagal')
            ->assertSee('1.250 ms')
            ->assertSee('DEPENDENCY_UNAVAILABLE')
            ->assertSeeInOrder(['Membaca teks dari input', 'Menganalisis pola bahasa', 'Selesai'])
            ->assertDontSee('PRIVATE_TIMELINE_INPUT')
            ->assertDontSee('PRIVATE_TIMELINE_SECRET');

        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());
        $this->assertNotNull($third->fresh());
        // The allowlist keeps only recognised keys; a free-text trace and an
        // unknown key are redacted, exactly as on the old events screen.
        $response->assertDontSee('classifier_input')
            ->assertSee('nilai disembunyikan');
    }

    public function test_detail_shows_an_honest_empty_state_when_no_event_was_recorded(): void
    {
        $this->admin();
        $submission = $this->completed();

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Belum ada catatan tahap untuk pemeriksaan ini.');
    }

    public function test_detail_keeps_translation_provenance_and_input_sections(): void
    {
        $this->admin();
        $submission = Submission::factory()->create([
            'input_type' => 'text',
            'raw_input' => 'Teks asli pemeriksaan.',
            'extracted_text' => 'Teks ekstraksi pemeriksaan.',
            'source_language' => 'en',
            'translated_text' => 'Teks terjemahan untuk IndoBERT.',
            'translation_provider' => 'openai',
            'translation_model' => 'translator-model',
            'translation_cached' => true,
            'pipeline_version' => 'pipeline-2026.11',
            'status' => 'completed',
            'processing_stage' => 'done',
            'processing_completed_at' => '2026-11-01 10:00:00',
        ]);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Terjemahan')
            ->assertSee('openai')
            ->assertSee('translator-model')
            ->assertSee('Cache terjemahan')
            ->assertSee('Teks terjemahan untuk IndoBERT.')
            ->assertSee('pipeline-2026.11')
            ->assertSee('01/11/2026 10:00')
            ->assertSee('Teks asli pemeriksaan.')
            ->assertSee('Teks ekstraksi pemeriksaan.');
    }

    public function test_user_view_link_is_offered_only_for_registered_submissions(): void
    {
        $this->admin();
        $owner = User::factory()->create();
        $registered = Submission::factory()->for($owner)->create(['status' => 'completed']);
        $guest = Submission::factory()->create(['user_id' => null, 'status' => 'completed']);

        $this->get(SubmissionResource::getUrl('view', ['record' => $registered]))->assertOk()
            ->assertSee('Buka tampilan pengguna')
            ->assertSee(route('hasil', $registered), false);

        // Admins have no guest capability, so a guest submission gets no link that would 404.
        $this->get(SubmissionResource::getUrl('view', ['record' => $guest]))->assertOk()
            ->assertDontSee('Buka tampilan pengguna')
            ->assertDontSee(route('hasil', $guest), false);
    }

    public function test_detail_never_discloses_the_guest_capability_hash(): void
    {
        $this->admin();
        $submission = Submission::factory()->create([
            'user_id' => null,
            'status' => 'completed',
            'guest_access_token_hash' => 'PRIVATE_CAPABILITY_FIXTURE',
            'media_path' => 'private/fixture.mp4',
        ]);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertDontSee('PRIVATE_CAPABILITY_FIXTURE')
            ->assertDontSee('/storage/private/fixture.mp4')
            ->assertSee('private/fixture.mp4');
    }

    public function test_detail_keeps_a_partial_classifier_record_out_of_the_result_section(): void
    {
        $this->admin();
        $submission = Submission::factory()->create(['status' => 'processing', 'processing_stage' => ProcessingStage::Explaining->value]);
        DetectionResult::factory()->for($submission)->create(['label' => 'hoax']);

        $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()
            ->assertSee('Record classifier parsial; bukan hasil final')
            ->assertDontSee('Prediksi model: Hoaks');
    }

    public function test_actionable_query_reuses_the_watchdog_threshold(): void
    {
        $this->admin();
        $stalled = $this->stalled(ProcessingStage::Classifying);
        $fresh = $this->fresh(ProcessingStage::Extracting);

        $threshold = app(StaleSubmissionRecovery::class)->staleAfterMinutes();
        $this->assertSame(10, $threshold);
        $this->assertSame(1, app(SubmissionRecovery::class)->actionableQuery()->count());
        $this->assertTrue($stalled->exists);
        $this->assertTrue($fresh->exists);
    }

    private function failed(): Submission
    {
        return Submission::factory()->create([
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Classifying->value,
            'failure_reason' => 'Layanan klasifikasi tidak tersedia.',
            'last_error_service' => 'bert',
            'last_error_code' => 'DEPENDENCY_UNAVAILABLE',
            'processing_completed_at' => now(),
        ]);
    }

    private function stalled(ProcessingStage $stage): Submission
    {
        $submission = Submission::factory()->create(['status' => 'processing', 'processing_stage' => $stage->value]);
        DB::table('submissions')->where('id', $submission->id)->update(['updated_at' => now()->subMinutes(11)]);

        return $submission->fresh();
    }

    private function fresh(ProcessingStage $stage): Submission
    {
        return Submission::factory()->create(['status' => 'processing', 'processing_stage' => $stage->value, 'updated_at' => now()->subMinute()]);
    }

    private function completed(): Submission
    {
        return Submission::factory()->create([
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
            'processing_completed_at' => now(),
        ]);
    }

    private function evidence(Submission $submission, array $data): EvidenceReference
    {
        return EvidenceReference::create(array_merge([
            'submission_id' => $submission->id,
            'document_id' => 'doc-000',
            'title' => 'Rujukan',
            'source' => 'Sumber rujukan',
            'source_url' => 'https://reference.example/doc-000',
            'published_at' => '2026-09-10',
            'similarity_score' => 0.5,
            'rank' => 1,
            'snippet' => 'Kutipan rujukan.',
            'knowledge_base_version' => 'v1.1.1',
        ], $data));
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

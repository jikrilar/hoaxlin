<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\AdminLogs\Pages\ViewAdminLog;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ListSubmissionProcessingEvents;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ViewSubmissionProcessingEvent;
use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\MonitoringPresentation;
use App\Models\AdminLog;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminMonitoringResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    #[DataProvider('resources')]
    public function test_monitoring_pages_preserve_panel_access_registered_routes_and_view_only_actions(string $resource, string $list, bool $event): void
    {
        $record = $event ? $this->event() : AdminLog::factory()->create(['action' => 'update']);
        foreach (['index', 'view'] as $page) {
            $url = $resource::getUrl($page, ['record' => $record]);
            $this->get($url)->assertRedirect('/admin/login');
            $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
            $this->admin();
            $response = $this->get($url)->assertOk()->assertSee($event ? MonitoringPresentation::EVENTS_NOTICE : MonitoringPresentation::LOGS_NOTICE);
            if ($page === 'index') {
                $response->assertSee('data-admin-readonly-runtime-required');
            } else {
                $response->assertDontSee('data-admin-readonly-runtime-required')->assertSee('data-monitoring-metadata');
            }
            auth()->logout();
        }
        $this->admin();
        $this->assertSame(['index', 'view'], array_keys($resource::getPages()));
        $this->assertFalse($resource::canCreate());
        $this->get($resource::getUrl('index').'/create')->assertNotFound();
        $url = $resource::getUrl('view', ['record' => $record]);
        $this->get($url.'/edit')->assertNotFound();
        $this->delete($url)->assertStatus(405);
        Livewire::test($list)->assertTableActionExists('view')->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('retry')->assertTableActionDoesNotExist('cancel')
            ->assertTableBulkActionDoesNotExist('delete')->assertActionDoesNotExist('clear')->assertActionDoesNotExist('create');
        $this->assertModelExists($record);
        Http::assertNothingSent();
    }

    public static function resources(): array
    {
        return ['events' => [SubmissionProcessingEventResource::class, ListSubmissionProcessingEvents::class, true], 'logs' => [AdminLogResource::class, ListAdminLogs::class, false]];
    }

    public function test_events_preserve_latest_order_stage_outcome_filters_sort_and_pagination(): void
    {
        $this->admin();
        $first = $this->event(['stage' => 'extracting', 'outcome' => 'started', 'duration_ms' => null, 'created_at' => now()->subMinutes(3)]);
        $second = $this->event(['stage' => 'classifying', 'outcome' => 'succeeded', 'duration_ms' => 0, 'created_at' => now()->subMinute()]);
        $third = $this->event(['stage' => 'classifying', 'outcome' => 'failed', 'duration_ms' => 1250, 'created_at' => now()->subMinutes(2)]);
        $page = Livewire::test(ListSubmissionProcessingEvents::class)->assertCanSeeTableRecords([$second, $third, $first], inOrder: true)
            ->assertTableColumnFormattedStateSet('stage', 'Klasifikasi BERT', $second)
            ->assertTableColumnFormattedStateSet('outcome', 'Berhasil', $second)
            ->assertTableColumnFormattedStateSet('duration_ms', '0 ms', $second)
            ->assertTableColumnFormattedStateSet('duration_ms', '1.250 ms', $third);
        $this->assertSame(ProcessingStage::options(), $page->instance()->getTable()->getFilter('stage')->getOptions());
        $this->assertSame(EventOutcome::options(), $page->instance()->getTable()->getFilter('outcome')->getOptions());
        Livewire::test(ListSubmissionProcessingEvents::class)->filterTable('stage', 'classifying')->assertCanSeeTableRecords([$second, $third])->assertCanNotSeeTableRecords([$first])
            ->filterTable('outcome', 'failed')->assertCanSeeTableRecords([$third])->assertCanNotSeeTableRecords([$first, $second]);
        Livewire::test(ListSubmissionProcessingEvents::class)->sortTable('duration_ms', 'desc')->assertCanSeeTableRecords([$third, $second, $first], inOrder: true);
        foreach (range(1, 6) as $i) {
            $this->event(['created_at' => now()->subMinutes(3 + $i)]);
        }
        $page = Livewire::test(ListSubmissionProcessingEvents::class)->set('tableRecordsPerPage', 5);
        $this->assertCount(5, $page->instance()->getTableRecords());
        $page->call('gotoPage', 2);
        $this->assertCount(4, $page->instance()->getTableRecords());
    }

    public function test_logs_preserve_latest_order_action_filter_and_nullable_target(): void
    {
        $this->admin();
        $records = collect(['create', 'update', 'delete'])->map(fn (string $action, int $i) => AdminLog::factory()->create(['action' => $action, 'target_table' => 'datasets', 'target_id' => $i ?: null, 'created_at' => now()->subMinutes(3 - $i)]));
        Livewire::test(ListAdminLogs::class)->assertCanSeeTableRecords($records->reverse(), inOrder: true);
        foreach ($records as $record) {
            Livewire::test(ListAdminLogs::class)->filterTable('action', $record->action)->assertCanSeeTableRecords([$record])->assertCanNotSeeTableRecords($records->reject(fn ($other) => $other->is($record)));
        }
        $this->get(AdminLogResource::getUrl('view', ['record' => $records[0]]))->assertOk()->assertSee('ID target tidak tercatat');
        $this->assertSame(['create', 'update', 'delete'], array_keys(Livewire::test(ListAdminLogs::class)->instance()->getTable()->getFilter('action')->getOptions()));
    }

    public function test_event_detail_uses_safe_operational_projection_without_disclosing_payload_in_html_or_hydration(): void
    {
        $this->admin();
        Bus::fake();
        $secret = 'PRIVATE_EVENT_SECRET_FIXTURE';
        $metadata = [
            'cached' => false, 'translated' => true, 'result_count' => 0, 'stale_after_minutes' => 10,
            'error_reference' => '12345678-1234-1234-1234-123456789abc', 'source_language' => 'en', 'queue' => 'inference', 'recovery_job' => 'ClassifySubmission',
            'model_version' => str_repeat($secret, 200), 'model' => ['api_key' => $secret],
            'authorization' => $secret, 'guest_access_token_hash' => $secret, 'raw_provider_response' => ['body' => $secret],
        ];
        $record = $this->event(['metadata' => $metadata, 'service' => $secret, 'error_code' => $secret, 'duration_ms' => 0]);
        $url = SubmissionProcessingEventResource::getUrl('view', ['record' => $record]);
        $this->get($url)->assertOk()->assertSee('0 ms')->assertSee('Layanan tidak dikenali')->assertSee('Kode tidak dikenali')
            ->assertSee('12345678-1234-1234-1234-123456789abc')->assertSee('ClassifySubmission')->assertSee('nilai disembunyikan')
            ->assertSee(SubmissionResource::getUrl('view', ['record' => $record->submission]))->assertDontSee($secret);
        Livewire::test(ViewSubmissionProcessingEvent::class, ['record' => $record->id])->call('$refresh')->assertDontSee($secret)->assertSee('result_count');
        Livewire::test(ListSubmissionProcessingEvents::class)->assertDontSee($secret);
        $this->assertSame($metadata, $record->fresh()->metadata);
        $this->assertSame($secret, $record->fresh()->service);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_audit_detail_projects_long_changes_without_credentials_free_text_or_unknown_keys(): void
    {
        $this->admin();
        $secret = 'PRIVATE_AUDIT_SECRET_FIXTURE';
        $actor = User::factory()->create(['name' => '<script id="actor-attack">literal</script>'.str_repeat(' panjang', 20)]);
        $changes = array_fill_keys((new Submission)->getFillable(), $secret);
        $changes = array_replace($changes, ['status' => 'processing', 'processing_stage' => 'classifying', 'input_type' => 'video', 'user_id' => 0, 'attempt_count' => 2, 'translation_cached' => false, 'unknown_payload' => [$secret => $secret]]);
        $metadata = ['model' => Submission::class, 'changes' => $changes, 'cookie' => $secret];
        $record = AdminLog::factory()->create(['admin_id' => $actor->id, 'action' => 'update', 'target_table' => 'submissions', 'metadata' => $metadata]);
        $safe = MonitoringPresentation::logMetadata($record);
        $this->assertGreaterThan(1000, strlen($safe));
        $this->assertStringContainsString('pipeline_version', $safe);
        $this->assertStringNotContainsString($secret, $safe);
        $this->assertStringNotContainsString('unknown_payload', $safe);
        $this->get(AdminLogResource::getUrl('view', ['record' => $record]))->assertOk()->assertSee($actor->name)
            ->assertSee('pipeline_version')->assertSee('video')->assertSee('nilai disembunyikan')->assertDontSee($secret)
            ->assertDontSee('<script id="actor-attack">', false);
        Livewire::test(ViewAdminLog::class, ['record' => $record->id])->call('$refresh')->assertDontSee($secret);
        Livewire::test(ListAdminLogs::class)->assertDontSee($secret)->assertDontSee('<script id="actor-attack">', false);
        $this->assertSame($metadata, $record->fresh()->metadata);
    }

    public function test_metadata_empty_unknown_and_malformed_values_have_honest_placeholders(): void
    {
        $this->admin();
        $event = $this->event(['metadata' => null, 'duration_ms' => null, 'service' => null, 'error_code' => null]);
        $this->get(SubmissionProcessingEventResource::getUrl('view', ['record' => $event]))->assertOk()->assertSee('Metadata tidak tersedia.')
            ->assertSee('Durasi tidak tercatat')->assertSee('Layanan tidak tercatat')->assertSee('Kode kendala tidak tercatat');
        $log = AdminLog::factory()->create(['metadata' => ['api_key' => 'PRIVATE_UNKNOWN_SECRET']]);
        $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()->assertSee('Tidak ada metadata operasional yang dapat ditampilkan.')->assertDontSee('PRIVATE_UNKNOWN_SECRET');
        foreach ([null, [], ['model' => ['api_key' => 'PRIVATE_UNKNOWN_SECRET'], 'changes' => 'PRIVATE_UNKNOWN_SECRET']] as $metadata) {
            $log->update(['metadata' => $metadata]);
            $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()->assertDontSee('PRIVATE_UNKNOWN_SECRET');
        }
    }

    #[DataProvider('durations')]
    public function test_duration_preserves_zero_unit_and_missing_without_fabrication(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, MonitoringPresentation::duration($value));
    }

    public static function durations(): array
    {
        return ['zero' => [0, '0 ms'], 'milliseconds' => [1250, '1.250 ms'], 'missing' => [null, null], 'negative' => [-1, null], 'invalid' => ['PRIVATE_DURATION_SECRET', null]];
    }

    private function event(array $data = []): SubmissionProcessingEvent
    {
        $record = new SubmissionProcessingEvent(array_replace([
            'submission_id' => Submission::factory()->create()->id, 'stage' => 'classifying', 'outcome' => 'succeeded',
            'service' => 'bert', 'attempt' => 1,
        ], $data));
        if (array_key_exists('created_at', $data)) {
            $record->created_at = $data['created_at'];
        }
        $record->save();

        return $record;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}

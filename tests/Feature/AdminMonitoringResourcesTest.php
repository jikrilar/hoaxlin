<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\AdminLogs\Pages\ViewAdminLog;
use App\Filament\Support\MonitoringPresentation;
use App\Models\AdminLog;
use App\Models\Submission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_log_pages_preserve_panel_access_registered_routes_and_view_only_actions(): void
    {
        $resource = AdminLogResource::class;
        $list = ListAdminLogs::class;
        $record = AdminLog::factory()->create(['action' => 'update']);
        foreach (['index', 'view'] as $page) {
            $url = $resource::getUrl($page, ['record' => $record]);
            $this->get($url)->assertRedirect('/admin/login');
            $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
            $this->admin();
            $response = $this->get($url)->assertOk()->assertSee(MonitoringPresentation::LOGS_NOTICE);
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

    public function test_logs_preserve_latest_order_action_filter_and_nullable_target(): void
    {
        $this->admin();
        $records = collect(['create', 'update', 'delete'])->map(fn (string $action, int $i) => AdminLog::factory()->create(['action' => $action, 'target_table' => 'datasets', 'target_id' => $i ?: null, 'created_at' => now()->subMinutes(3 - $i)]));
        Livewire::test(ListAdminLogs::class)->assertCanSeeTableRecords($records->reverse(), inOrder: true);
        foreach ($records as $record) {
            Livewire::test(ListAdminLogs::class)->filterTable('action', $record->action)->assertCanSeeTableRecords([$record])->assertCanNotSeeTableRecords($records->reject(fn ($other) => $other->is($record)));
        }
        $this->get(AdminLogResource::getUrl('view', ['record' => $records[0]]))->assertOk()->assertSee('ID target tidak tercatat');
        $this->assertSame(['create', 'update', 'delete', 'submission_retried', 'feedback_reviewed'], array_keys(Livewire::test(ListAdminLogs::class)->instance()->getTable()->getFilter('action')->getOptions()));
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

    private function admin(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]));
    }
}

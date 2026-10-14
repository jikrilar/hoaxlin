<?php

namespace Tests\Feature;

use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\CreateDataset;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ListDatasets;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDatasetsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    #[DataProvider('pages')]
    public function test_native_pages_preserve_panel_policy_and_curation_context(string $page): void
    {
        $record = Dataset::factory()->create();
        $url = DatasetResource::getUrl($page, ['record' => $record]);
        $this->get($url)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->admin();
        $response = $this->get($url)->assertOk()->assertSee(DatasetResource::CURATION_NOTICE);
        if (in_array($page, ['create', 'edit'], true)) {
            $response->assertSee('data-admin-form-runtime-required');
        } else {
            $response->assertDontSee('data-admin-form-runtime-required');
        }
        Http::assertNothingSent();
    }

    public static function pages(): array
    {
        return ['list' => ['index'], 'create' => ['create'], 'detail' => ['view'], 'edit' => ['edit']];
    }

    public function test_policy_also_denies_direct_native_components_for_non_admins(): void
    {
        $record = Dataset::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Livewire::test(CreateDataset::class)->assertForbidden();
        Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()])->assertForbidden();
        Livewire::test(ListDatasets::class)->assertForbidden();
        $this->assertModelExists($record);
        $this->assertDatabaseCount('datasets', 1);
    }

    public function test_detail_keeps_full_escaped_text_and_honest_nullable_metadata(): void
    {
        $this->admin();
        $record = Dataset::factory()->create([
            'text' => '<script id="attack">literal</script>'.str_repeat("\nParagraf kurasi yang cukup panjang.", 300).' UniqueTailFixture',
            'label' => 'meragukan', 'source' => '<script id="source-attack">sumber</script>', 'verified_by' => null,
        ]);
        $this->get(DatasetResource::getUrl('view', ['record' => $record]))->assertOk()
            ->assertSee($record->text)->assertSee($record->source)->assertSee('Meragukan')
            ->assertSee('bukan kelas training aktif BERT')->assertSee('Belum diverifikasi')
            ->assertDontSee('<script id="attack">', false)->assertDontSee('<script id="source-attack">', false);
        Livewire::test(ListDatasets::class)->assertDontSee($record->text)->searchTable('UniqueTailFixture')->assertCanSeeTableRecords([$record]);
        $record->update(['source' => '']);
        $this->get(DatasetResource::getUrl('view', ['record' => $record]))->assertOk()->assertSee('Sumber belum tercatat');
    }

    #[DataProvider('labels')]
    public function test_native_create_keeps_domain_labels_and_exact_text_source_boundaries(string $label, string $display): void
    {
        $this->admin();
        Livewire::test(CreateDataset::class)->fillForm(['text' => '1234567890', 'label' => $label, 'source' => str_repeat('s', 255), 'verified_by' => null])
            ->call('create')->assertHasNoFormErrors();
        $record = Dataset::sole();
        $this->assertSame($label, $record->label);
        $this->assertSame(255, mb_strlen($record->source));
        $this->assertNull($record->verified_by);
        $this->get(DatasetResource::getUrl('view', ['record' => $record]))->assertOk()->assertSee($display);
    }

    public static function labels(): array
    {
        return ['valid' => ['valid', 'Valid'], 'hoax' => ['hoax', 'Hoaks'], 'meragukan' => ['meragukan', 'Meragukan']];
    }

    public function test_validation_rejects_short_text_missing_source_and_invalid_label_without_losing_input(): void
    {
        $this->admin();
        Livewire::test(CreateDataset::class)->fillForm(['text' => '123456789', 'label' => null, 'source' => ''])->call('create')
            ->assertHasFormErrors(['text' => 'min', 'label' => 'required', 'source' => 'required'])
            ->assertSee('Teks kurasi minimal 10 karakter.')->assertSee('Masukkan sumber data kurasi.')->assertSet('data.text', '123456789');
        Livewire::test(CreateDataset::class)->fillForm(['text' => 'Teks kurasi valid untuk pengujian.', 'label' => 'unsupported', 'source' => str_repeat('s', 256)])
            ->call('create')->assertHasFormErrors(['label', 'source' => 'max']);
        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_edit_rejects_non_admin_or_missing_verifier_and_accepts_admin_or_null(): void
    {
        $admin = $this->admin();
        $regular = User::factory()->create(['is_admin' => false]);
        $record = Dataset::factory()->create(['verified_by' => null]);
        foreach ([$regular->id, 999999] as $invalidId) {
            Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()])->fillForm(['verified_by' => $invalidId])->call('save')->assertHasFormErrors(['verified_by']);
            $this->assertNull($record->fresh()->verified_by);
        }
        Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()])->fillForm(['verified_by' => $admin->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($admin->id, $record->fresh()->verified_by);
        Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()])->fillForm(['verified_by' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->fresh()->verified_by);
    }

    public function test_stale_admin_option_is_revalidated_before_save(): void
    {
        $this->admin();
        $formerAdmin = User::factory()->create(['is_admin' => true]);
        $record = Dataset::factory()->create(['verified_by' => null]);
        $page = Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()]);
        $formerAdmin->update(['is_admin' => false]);
        $page->fillForm(['verified_by' => $formerAdmin->id])->call('save')->assertHasFormErrors(['verified_by']);
        $this->assertNull($record->fresh()->verified_by);
    }

    public function test_search_filters_sort_and_pagination_keep_existing_query_populations(): void
    {
        $admin = $this->admin();
        $first = Dataset::factory()->create(['text' => 'Teks pertama yang dapat ditemukan.', 'label' => 'valid', 'source' => 'Alpha source', 'verified_by' => $admin->id]);
        $second = Dataset::factory()->create(['label' => 'hoax', 'source' => 'Beta source', 'verified_by' => null]);
        $third = Dataset::factory()->create(['label' => 'meragukan', 'source' => 'Gamma source', 'verified_by' => null]);
        Livewire::test(ListDatasets::class)->searchTable('pertama')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second, $third]);
        Livewire::test(ListDatasets::class)->searchTable('Beta source')->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first, $third]);
        Livewire::test(ListDatasets::class)->filterTable('label', 'meragukan')->assertCanSeeTableRecords([$third])->assertCanNotSeeTableRecords([$first, $second]);
        Livewire::test(ListDatasets::class)->filterTable('verified_by', $admin->id)->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second, $third]);
        Dataset::factory()->count(12)->create();
        $page = Livewire::test(ListDatasets::class)->set('tableRecordsPerPage', 5)->sortTable('source');
        $this->assertCount(5, $page->instance()->getTableRecords());
        $page->assertCanSeeTableRecords(Dataset::orderBy('source')->limit(5)->get(), inOrder: true)->call('gotoPage', 2)
            ->assertCanSeeTableRecords(Dataset::orderBy('source')->skip(5)->limit(5)->get(), inOrder: true);
    }

    public function test_bulk_confirmation_and_cancel_preserve_records_then_delete_only_selection(): void
    {
        $this->admin();
        $records = Dataset::factory()->count(3)->create();
        $selection = $records->take(2);
        $deletedIds = [];
        Event::listen('eloquent.deleted: '.Dataset::class, function (Dataset $record) use (&$deletedIds): void {
            $deletedIds[] = $record->id;
        });
        $page = Livewire::test(ListDatasets::class)->mountTableBulkAction('delete', $selection)->assertTableBulkActionMounted('delete');
        $action = $page->instance()->getMountedAction();
        $this->assertTrue($action->isConfirmationRequired());
        $this->assertStringStartsWith('Anda memilih 2 data kurasi.', $action->getModalDescription());
        $page->call('unmountAction');
        $this->assertDatabaseCount('datasets', 3);
        $page->callTableBulkAction('delete', $selection);
        $this->assertEqualsCanonicalizing($selection->modelKeys(), $deletedIds);
        $this->assertModelExists($records[2]);
        $this->assertDatabaseCount('datasets', 1);
    }

    public function test_native_mutations_keep_model_callbacks_without_training_or_provider_calls(): void
    {
        $this->admin();
        Bus::fake();
        Process::fake();
        $config = config('services.bert');
        $events = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        foreach (array_keys($events) as $event) {
            Event::listen('eloquent.'.$event.': '.Dataset::class, function () use (&$events, $event): void {
                $events[$event]++;
            });
        }
        Livewire::test(CreateDataset::class)->fillForm(['text' => 'Teks kurasi yang cukup panjang.', 'label' => 'valid', 'source' => 'Sumber fixture'])->call('create')->assertHasNoFormErrors();
        $record = Dataset::sole();
        $page = Livewire::test(EditDataset::class, ['record' => $record->getRouteKey()]);
        $page->fillForm(['label' => 'hoax'])->call('save')->assertHasNoFormErrors();
        $page->mountAction('delete');
        $this->assertModelExists($record);
        $page->callMountedAction();
        $this->assertModelMissing($record);
        $this->assertSame(['created' => 1, 'updated' => 1, 'deleted' => 1], $events);
        Bus::assertNothingDispatched();
        Process::assertNothingRan();
        Http::assertNothingSent();
        $this->assertSame($config, config('services.bert'));
    }

    public function test_existing_audit_observer_still_records_model_crud_on_admin_paths(): void
    {
        $admin = $this->admin();
        $request = app('request');
        app()->instance('request', Request::create('/admin/datasets'));
        try {
            $record = Dataset::factory()->create();
            $record->update(['source' => 'Sumber diperbarui']);
            $record->delete();
            $this->assertSame(['create', 'update', 'delete'], AdminLog::forTarget('datasets', $record->id)->orderBy('id')->pluck('action')->all());
            $this->assertSame([$admin->id], AdminLog::pluck('admin_id')->unique()->values()->all());
        } finally {
            app()->instance('request', $request);
        }
    }

    private function admin(): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}

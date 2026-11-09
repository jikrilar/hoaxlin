<?php

namespace Tests\Feature;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Filament\Resources\ModelVersions\Pages\CreateModelVersion;
use App\Filament\Resources\ModelVersions\Pages\ListModelVersions;
use App\Filament\Resources\ModelVersions\Pages\ViewModelVersion;
use App\Filament\Widgets\ModelVersionWidget;
use App\Jobs\StartBertTrainingJob;
use App\Models\Dataset;
use App\Models\ModelVersion;
use App\Models\User;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminModelVersionsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        Storage::fake('datasets');
        config([
            'services.bert.url' => 'http://bert.test',
            'services.bert.internal_token' => 'secret-bert-token',
            'services.bert.max_train_csv_bytes' => 26214400,
        ]);
    }

    #[DataProvider('pages')]
    public function test_native_pages_enforce_admin_access_and_immutable_edit_delete_boundaries(string $page): void
    {
        $record = ModelVersion::factory()->completed()->create();
        $url = ModelVersionResource::getUrl($page, ['record' => $record]);

        $this->get($url)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();

        $this->admin();
        $response = $this->get($url)->assertOk()->assertSee(ModelVersionResource::TRAINING_NOTICE);

        if ($page === 'create') {
            $response->assertSee('data-admin-form-runtime-required');
        } else {
            $response->assertDontSee('data-admin-form-runtime-required');
        }

        Http::assertNothingSent();
    }

    public static function pages(): array
    {
        return [
            'list' => ['index'],
            'create' => ['create'],
            'detail' => ['view'],
        ];
    }

    public function test_direct_livewire_pages_forbid_non_admin_and_disable_edit_and_delete(): void
    {
        $record = ModelVersion::factory()->completed()->create();
        $regular = User::factory()->create(['is_admin' => false]);

        $this->actingAs($regular);
        Livewire::test(ListModelVersions::class)->assertForbidden();
        Livewire::test(CreateModelVersion::class)->assertForbidden();
        Livewire::test(ViewModelVersion::class, ['record' => $record->getRouteKey()])->assertForbidden();

        $this->admin();
        $this->assertSame(['index', 'create', 'view'], array_keys(ModelVersionResource::getPages()));
        $this->assertFalse(ModelVersionResource::canEdit($record));
        $this->assertFalse(ModelVersionResource::canDelete($record));
        $this->assertFalse(ModelVersionResource::canDeleteAny());
        $this->get(ModelVersionResource::getUrl('view', ['record' => $record]).'/edit')->assertNotFound();

        Livewire::test(ListModelVersions::class)
            ->assertActionExists('create')
            ->assertActionExists('activateBaseline')
            ->assertTableActionExists('view')
            ->assertTableActionExists('activate')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
    }

    public function test_policy_allows_activation_only_for_admin_on_completed_inactive_versions(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $regular = User::factory()->create(['is_admin' => false]);

        $pending = ModelVersion::factory()->create(['status' => ModelVersion::STATUS_PENDING]);
        $training = ModelVersion::factory()->training()->create();
        $failed = ModelVersion::factory()->failed()->create();
        $completedInactive = ModelVersion::factory()->completed()->create(['is_active' => false]);
        $completedActive = ModelVersion::factory()->completed()->active()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('activate', $completedInactive));
        $this->assertFalse(Gate::forUser($admin)->allows('activate', $completedActive));
        $this->assertFalse(Gate::forUser($admin)->allows('activate', $pending));
        $this->assertFalse(Gate::forUser($admin)->allows('activate', $training));
        $this->assertFalse(Gate::forUser($admin)->allows('activate', $failed));
        $this->assertFalse(Gate::forUser($regular)->allows('activate', $completedInactive));

        $this->assertFalse(Gate::forUser($admin)->allows('update', $completedInactive));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $completedInactive));
        $this->assertFalse(Gate::forUser($admin)->allows('deleteAny', ModelVersion::class));
    }

    public function test_create_model_version_validates_contract_stores_csv_calculates_rows_and_dispatches_job(): void
    {
        $this->admin();
        Queue::fake();

        $csv = "\xEF\xBB\xBFtext,label\n"
            ."\"Kementerian Kesehatan merilis jadwal imunisasi nasional\",valid\n"
            ."\"Beredar pesan berantai kuota gratis 500GB\",hoaks\n"
            ."\"Fakta resmi Badan Pusat Statistik\",fakta\n";

        $file = UploadedFile::fake()->createWithContent('batch-november.csv', $csv);

        Livewire::test(CreateModelVersion::class)
            ->fillForm([
                'version_name' => '  v1.1.0  ',
                'dataset_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $version = ModelVersion::sole();
        $this->assertSame('v1.1.0', $version->version_name);
        $this->assertSame(3, $version->total_rows);
        $this->assertSame(ModelVersion::STATUS_PENDING, $version->status);
        $this->assertFalse($version->is_active);
        $this->assertStringStartsWith('batches/', (string) $version->dataset_path);
        $this->assertStringEndsWith('.csv', (string) $version->dataset_path);
        Storage::disk('datasets')->assertExists((string) $version->dataset_path);

        Queue::assertPushedOn('default', StartBertTrainingJob::class, function (StartBertTrainingJob $job) use ($version): bool {
            return $job->modelVersionId === $version->id;
        });

        $this->assertSame(0, Dataset::count());
        Http::assertNothingSent();
    }

    #[DataProvider('invalidCreateInputs')]
    public function test_create_rejects_invalid_version_names_and_invalid_csv_files_without_persisting(
        string $versionName,
        string $filename,
        string $content,
        string $errorField,
    ): void {
        $this->admin();
        Bus::fake();
        ModelVersion::factory()->create(['version_name' => 'v1.9.0']);

        $file = UploadedFile::fake()->createWithContent($filename, $content);

        Livewire::test(CreateModelVersion::class)
            ->fillForm([
                'version_name' => $versionName,
                'dataset_path' => $file,
            ])
            ->call('create')
            ->assertHasFormErrors([$errorField]);

        $this->assertSame(1, ModelVersion::count());
        $this->assertSame([], Storage::disk('datasets')->allFiles('batches'));
        Bus::assertNothingDispatched();
    }

    public static function invalidCreateInputs(): array
    {
        $validCsv = "text,label\nInformasi resmi dari instansi terkait,valid\n";

        return [
            'baseline lowercase' => ['v1.0.0', 'batch.csv', $validCsv, 'version_name'],
            'baseline uppercase' => ['V1.0.0', 'batch.csv', $validCsv, 'version_name'],
            'reserved dir' => ['indobert-hoax', 'batch.csv', $validCsv, 'version_name'],
            'path traversal' => ['v1..2', 'batch.csv', $validCsv, 'version_name'],
            'duplicate version' => ['v1.9.0', 'batch.csv', $validCsv, 'version_name'],
            'invalid chars' => ['v1 1!', 'batch.csv', $validCsv, 'version_name'],
            'non-csv extension' => ['v1.2.0', 'batch.txt', $validCsv, 'dataset_path'],
            'meragukan label' => ['v1.2.1', 'batch.csv', "text,label\nKlaim belum pasti,meragukan\n", 'dataset_path'],
            'missing header' => ['v1.2.2', 'batch.csv', "title,category\nBerita,valid\n", 'dataset_path'],
            'header only' => ['v1.2.3', 'batch.csv', "text,label\n\n", 'dataset_path'],
        ];
    }

    public function test_detail_and_table_escape_html_format_metrics_and_toggle_polling_dynamically(): void
    {
        $this->admin();

        $completed = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.5.0',
            'dataset_path' => 'batches/secret-upload-name.csv',
            'total_rows' => 1250,
            'accuracy' => 0.9345,
            'f1_score' => 0.921,
            'training_duration' => 135,
            'is_active' => false,
        ]);

        $failed = ModelVersion::factory()->failed()->create([
            'version_name' => 'v1.5.1',
            'error_message' => '<script id="err-xss">alert(1)</script>Gagal memproses CSV',
        ]);

        $this->get(ModelVersionResource::getUrl('view', ['record' => $completed]))
            ->assertOk()
            ->assertSee('v1.5.0')
            ->assertSee('Selesai')
            ->assertSee('Tidak aktif')
            ->assertSee('secret-upload-name.csv')
            ->assertDontSee('batches/secret-upload-name.csv')
            ->assertSee('1.250 baris')
            ->assertSee('93.45%')
            ->assertSee('92.10%')
            ->assertSee('135 detik')
            ->assertDontSee('wire:poll.5s');

        $this->get(ModelVersionResource::getUrl('view', ['record' => $failed]))
            ->assertOk()
            ->assertSee('Gagal memproses CSV')
            ->assertDontSee('<script id="err-xss">', false)
            ->assertDontSee('wire:poll.5s');

        $listWhenSettled = Livewire::test(ListModelVersions::class)
            ->assertTableColumnFormattedStateSet('accuracy', '93.45%', $completed)
            ->assertTableColumnFormattedStateSet('f1_score', '92.10%', $completed)
            ->assertTableColumnFormattedStateSet('training_duration', '135 detik', $completed)
            ->assertTableColumnFormattedStateSet('status', 'Selesai', $completed)
            ->assertTableColumnFormattedStateSet('is_active', 'Tidak aktif', $completed);
        $this->assertNull($listWhenSettled->instance()->getTable()->getPollingInterval());

        $training = ModelVersion::factory()->training()->create(['version_name' => 'v1.6.0']);

        $listWhenTraining = Livewire::test(ListModelVersions::class);
        $this->assertSame('5s', $listWhenTraining->instance()->getTable()->getPollingInterval());

        $this->get(ModelVersionResource::getUrl('view', ['record' => $training]))
            ->assertOk()
            ->assertSee('Sedang dilatih')
            ->assertSee('wire:poll.5s', false);

        $viewComponent = Livewire::test(ViewModelVersion::class, ['record' => $training->getRouteKey()])
            ->assertActionHidden('activate');

        $training->forceFill([
            'status' => ModelVersion::STATUS_COMPLETED,
            'accuracy' => 0.91,
            'f1_score' => 0.90,
            'baseline_accuracy' => 0.86,
            'baseline_f1_score' => 0.85,
            'training_duration' => 88,
        ])->save();

        $viewComponent->call('$refresh')
            ->assertSee('Selesai')
            ->assertSee('91.00%')
            ->assertSee('+5.00% (91.00% vs baseline 86.00%)')
            ->assertSee('+5.00% (90.00% vs baseline 85.00%)')
            ->assertActionVisible('activate')
            ->assertDontSee('wire:poll.5s');
    }

    public function test_sample_incremental_training_csv_exists_and_can_be_submitted_via_admin(): void
    {
        Storage::fake('datasets');
        $this->admin();

        $samplePath = base_path('datasets/samples/incremental-training-sample.csv');
        $this->assertFileExists($samplePath);

        Http::fake([
            'http://bert.test/train' => Http::response([
                'request_id' => 'req-sample-1',
                'status' => 'accepted',
                'version_id' => 1,
                'version_name' => 'v1.1.0-sample',
                'message' => 'Accepted',
            ], 202),
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'incremental-training-sample.csv',
            (string) file_get_contents($samplePath),
        );

        Livewire::test(CreateModelVersion::class)
            ->fillForm([
                'version_name' => 'v1.1.0-sample',
                'dataset_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Pelatihan versi model dimasukkan ke antrean.');

        $version = ModelVersion::where('version_name', 'v1.1.0-sample')->firstOrFail();
        $this->assertSame(24, $version->total_rows);
        $this->assertSame(ModelVersion::STATUS_TRAINING, $version->status);
    }

    public function test_activate_model_and_activate_baseline_actions_handle_remote_errors_and_success(): void
    {
        $this->admin();
        $first = ModelVersion::factory()->completed()->active()->create(['version_name' => 'v1.1.0']);
        $second = ModelVersion::factory()->completed()->create(['version_name' => 'v1.2.0', 'is_active' => false]);

        $attempt = 0;
        Http::fake([
            'http://bert.test/activate-model' => function (ClientRequest $request) use (&$attempt) {
                $attempt++;

                if ($attempt === 1) {
                    return Http::response([
                        'error' => ['code' => 'model_version_not_found', 'message' => 'Artefak v1.2.0 tidak ditemukan'],
                    ], 404);
                }

                return Http::response([
                    'request_id' => "act-{$attempt}",
                    'status' => 'ok',
                    'model_version' => $request['version_name'],
                    'model_status' => 'ready',
                ], 200);
            },
        ]);

        Livewire::test(ListModelVersions::class)
            ->callTableAction('activate', $second)
            ->assertNotified('Gagal mengaktifkan versi model');

        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);

        Livewire::test(ViewModelVersion::class, ['record' => $second->getRouteKey()])
            ->callAction('activate')
            ->assertNotified('Model v1.2.0 diaktifkan')
            ->assertActionHidden('activate');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);

        Livewire::test(ListModelVersions::class)
            ->callAction('activateBaseline')
            ->assertNotified('Baseline v1.0.0 diaktifkan');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
    }

    public function test_model_version_widget_still_reads_runtime_version_endpoint_instead_of_model_versions_table(): void
    {
        $this->admin();
        ModelVersion::factory()->completed()->active()->create(['version_name' => 'v9.1.0']);

        $mock = $this->mock(BertRuntimeMetadata::class);
        $mock->shouldReceive('fetch')->once()->andReturn([
            'available' => true,
            'model_status' => 'ready',
            'model_version' => 'v1.0.0',
            'threshold' => 0.99,
            'temperature' => 1.0,
            'labels' => ['valid', 'hoax'],
            'evaluation' => [],
        ]);

        Livewire::test(ModelVersionWidget::class)
            ->assertSee('v1.0.0')
            ->assertDontSee('v9.1.0');
    }

    private function admin(): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}

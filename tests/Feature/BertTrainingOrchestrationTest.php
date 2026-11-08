<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Jobs\StartBertTrainingJob;
use App\Models\ModelVersion;
use App\Services\Bert\BertTrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BertTrainingOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('datasets');
        Http::preventStrayRequests();
        config([
            'services.bert.url' => 'http://bert.test',
            'services.bert.internal_token' => 'secret-bert-token',
            'services.bert.callback_url' => 'http://app:8000/api/internal/bert/callback',
        ]);
    }

    public function test_start_training_streams_multipart_csv_and_sends_callback_url(): void
    {
        $csv = "text,label\nBerita resmi BMKG tentang cuaca,valid\n";
        Storage::disk('datasets')->put('batches/train-batch.csv', $csv);

        $version = ModelVersion::factory()->create([
            'version_name' => 'v1.1.0',
            'dataset_path' => 'batches/train-batch.csv',
            'total_rows' => 1,
            'status' => ModelVersion::STATUS_PENDING,
        ]);

        $capturedAuth = null;
        $capturedMultipart = false;
        $capturedBody = '';
        Http::fake([
            'http://bert.test/train' => function (ClientRequest $request) use (&$capturedAuth, &$capturedMultipart, &$capturedBody) {
                $capturedAuth = $request->header('Authorization')[0] ?? null;
                $capturedMultipart = $request->isMultipart();
                $capturedBody = $request->body();

                return Http::response([
                    'request_id' => 'req-1',
                    'status' => 'accepted',
                    'version_id' => 1,
                    'version_name' => 'v1.1.0',
                    'message' => 'Fine-tuning job accepted',
                ], 202);
            },
        ]);

        $job = (new StartBertTrainingJob($version->id))->withFakeQueueInteractions();
        $job->handle(app(BertTrainingService::class));

        $job->assertNotFailed()->assertNotReleased();
        $this->assertSame(ModelVersion::STATUS_TRAINING, $version->fresh()->status);

        $this->assertSame('Bearer secret-bert-token', $capturedAuth);
        $this->assertTrue($capturedMultipart);
        $this->assertStringContainsString('name="csv_file"', $capturedBody);
        $this->assertStringContainsString('filename="train-batch.csv"', $capturedBody);
        $this->assertStringContainsString('Berita resmi BMKG tentang cuaca', $capturedBody);
        $this->assertStringContainsString((string) $version->id, $capturedBody);
        $this->assertStringContainsString('v1.1.0', $capturedBody);
        $this->assertStringContainsString('http://app:8000/api/internal/bert/callback', $capturedBody);
    }

    public function test_start_training_falls_back_to_named_callback_route_when_callback_url_is_blank(): void
    {
        config(['services.bert.callback_url' => '']);
        Storage::disk('datasets')->put('batches/train-batch.csv', "text,label\nInformasi resmi,valid\n");

        $version = ModelVersion::factory()->create([
            'version_name' => 'v1.1.1',
            'dataset_path' => 'batches/train-batch.csv',
        ]);

        $capturedBody = '';
        Http::fake([
            'http://bert.test/train' => function (ClientRequest $request) use (&$capturedBody) {
                $capturedBody = $request->body();

                return Http::response(['status' => 'accepted'], 202);
            },
        ]);

        app(BertTrainingService::class)->startTraining($version);

        $this->assertStringContainsString(route('internal.bert.callback'), $capturedBody);
    }

    public function test_job_reverts_training_to_pending_and_releases_on_transient_errors(): void
    {
        Storage::disk('datasets')->put('batches/retry.csv', "text,label\nInformasi valid,valid\n");
        $version = ModelVersion::factory()->create([
            'version_name' => 'v1.2.0',
            'dataset_path' => 'batches/retry.csv',
            'status' => ModelVersion::STATUS_PENDING,
        ]);

        Http::fake([
            'http://bert.test/train' => Http::response([
                'error' => [
                    'code' => 'training_in_progress',
                    'message' => 'Another fine-tuning job is already in progress',
                ],
            ], 409, ['Retry-After' => '45']),
        ]);

        $job = (new StartBertTrainingJob($version->id))->withFakeQueueInteractions();
        $job->handle(app(BertTrainingService::class));

        $job->assertReleased(45)->assertNotFailed();
        $this->assertSame(ModelVersion::STATUS_PENDING, $version->fresh()->status);

        Http::fake(fn () => throw new ConnectionException('connection refused'));
        $secondAttempt = (new StartBertTrainingJob($version->id))->withFakeQueueInteractions();
        $secondAttempt->handle(app(BertTrainingService::class));

        $secondAttempt->assertReleased(15)->assertNotFailed();
        $this->assertSame(ModelVersion::STATUS_PENDING, $version->fresh()->status);
    }

    public function test_job_marks_version_failed_on_permanent_409_version_already_exists(): void
    {
        Storage::disk('datasets')->put('batches/dup.csv', "text,label\nInformasi valid,valid\n");
        $version = ModelVersion::factory()->create([
            'version_name' => 'v1.2.1',
            'dataset_path' => 'batches/dup.csv',
            'status' => ModelVersion::STATUS_PENDING,
        ]);

        Http::fake([
            'http://bert.test/train' => Http::response([
                'error' => [
                    'code' => 'version_already_exists',
                    'message' => "Model version 'v1.2.1' already exists",
                ],
            ], 409),
        ]);

        $job = (new StartBertTrainingJob($version->id))->withFakeQueueInteractions();
        $job->handle(app(BertTrainingService::class));

        $job->assertFailedWith(AiServiceException::class)->assertNotReleased();
        $version->refresh();
        $this->assertSame(ModelVersion::STATUS_FAILED, $version->status);
        $this->assertStringContainsString('already exists', (string) $version->error_message);
    }

    public function test_job_marks_version_failed_without_http_when_csv_is_missing(): void
    {
        $missingCsvVersion = ModelVersion::factory()->create([
            'version_name' => 'v1.2.2',
            'dataset_path' => 'batches/missing.csv',
            'status' => ModelVersion::STATUS_PENDING,
        ]);

        $missingJob = (new StartBertTrainingJob($missingCsvVersion->id))->withFakeQueueInteractions();
        $missingJob->handle(app(BertTrainingService::class));

        $missingJob->assertFailedWith(AiServiceException::class)->assertNotReleased();
        $this->assertSame(ModelVersion::STATUS_FAILED, $missingCsvVersion->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_job_is_idempotent_for_non_pending_records_and_failed_hook_preserves_completed(): void
    {
        $completed = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.3.0',
            'accuracy' => 0.94,
            'f1_score' => 0.93,
            'error_message' => null,
        ]);

        $job = (new StartBertTrainingJob($completed->id))->withFakeQueueInteractions();
        $job->handle(app(BertTrainingService::class));
        $job->failed(new RuntimeException('Queue worker killed late'));

        Http::assertNothingSent();
        $completed->refresh();
        $this->assertSame(ModelVersion::STATUS_COMPLETED, $completed->status);
        $this->assertNull($completed->error_message);

        $training = ModelVersion::factory()->training()->create();
        $trainingJob = (new StartBertTrainingJob($training->id))->withFakeQueueInteractions();
        $trainingJob->failed(new RuntimeException('Worker timeout'));

        $this->assertSame(ModelVersion::STATUS_FAILED, $training->fresh()->status);
        $this->assertSame('Gagal memulai pelatihan model pada antrean.', $training->fresh()->error_message);
    }

    public function test_activate_model_version_requires_bert_confirmation_and_isolates_failures(): void
    {
        $current = ModelVersion::factory()->completed()->active()->create(['version_name' => 'v1.1.0']);
        $target = ModelVersion::factory()->completed()->create(['version_name' => 'v1.2.0', 'is_active' => false]);
        $pending = ModelVersion::factory()->create(['version_name' => 'v1.3.0', 'status' => ModelVersion::STATUS_PENDING]);

        try {
            app(BertTrainingService::class)->activateModelVersion($pending);
            $this->fail('Expected non-completed version activation to fail.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }
        Http::assertNothingSent();

        $attempt = 0;
        Http::fake([
            'http://bert.test/activate-model' => function (ClientRequest $request) use (&$attempt) {
                $attempt++;
                $this->assertSame('v1.2.0', $request['version_name']);
                $this->assertSame('Bearer secret-bert-token', $request->header('Authorization')[0] ?? null);

                if ($attempt === 1) {
                    return Http::response([
                        'error' => ['code' => 'model_version_not_found', 'message' => 'Artifact directory missing'],
                    ], 404);
                }

                return Http::response([
                    'request_id' => 'act-1',
                    'status' => 'ok',
                    'model_version' => 'v1.2.0',
                    'model_status' => 'ready',
                ], 200);
            },
        ]);

        try {
            app(BertTrainingService::class)->activateModelVersion($target);
            $this->fail('Expected remote 404 to abort activation.');
        } catch (AiServiceException $exception) {
            $this->assertSame(404, $exception->statusCode);
        }

        $this->assertTrue($current->fresh()->is_active);
        $this->assertFalse($target->fresh()->is_active);

        $activated = app(BertTrainingService::class)->activateModelVersion($target);

        $this->assertTrue($activated->is_active);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertFalse($current->fresh()->is_active);
    }

    public function test_activate_baseline_isolates_remote_failure_and_deactivates_custom_versions_on_success(): void
    {
        $custom = ModelVersion::factory()->completed()->active()->create(['version_name' => 'v1.4.0']);

        $attempt = 0;
        Http::fake([
            'http://bert.test/activate-model' => function (ClientRequest $request) use (&$attempt) {
                $attempt++;
                $this->assertSame('v1.0.0', $request['version_name']);

                if ($attempt === 1) {
                    return Http::response(['error' => ['message' => 'BERT busy']], 503);
                }

                return Http::response([
                    'request_id' => 'act-base',
                    'status' => 'ok',
                    'model_version' => 'v1.0.0',
                    'model_status' => 'ready',
                ], 200);
            },
        ]);

        try {
            app(BertTrainingService::class)->activateBaseline();
            $this->fail('Expected baseline activation failure to throw.');
        } catch (AiServiceException $exception) {
            $this->assertTrue($exception->retryable);
        }

        $this->assertTrue($custom->fresh()->is_active);

        app(BertTrainingService::class)->activateBaseline();

        $this->assertFalse($custom->fresh()->is_active);
        $this->assertNull(ModelVersion::currentActive());
    }
}

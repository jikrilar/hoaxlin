<?php

namespace Tests\Feature;

use App\Filament\Widgets\ModelVersionWidget;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ModelStatisticsClaimsTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_metadata_is_the_active_model_source(): void
    {
        Http::fake([
            'http://bert.test/version' => Http::response([
                'model_version' => 'v9.9.9',
                'model_status' => 'ready',
                'model_labels' => ['valid', 'hoax'],
                'threshold' => 0.91,
                'temperature' => 0.87,
                'evaluation_model_version' => 'v9.9.9',
                'evaluation_accuracy' => 0.88,
                'evaluation_macro_f1' => 0.86,
                'evaluation_sample_count' => 120,
                'evaluation_dataset_name' => 'fixture-corpus',
                'evaluation_dataset_version' => 'v2',
                'evaluation_split' => 'held-out test',
                'exported_at' => '2026-09-01T00:00:00+00:00',
            ]),
        ]);
        config(['services.bert.url' => 'http://bert.test']);

        $metadata = app(BertRuntimeMetadata::class)->fetch();

        $this->assertTrue($metadata['available']);
        $this->assertSame('v9.9.9', $metadata['model_version']);
        $this->assertSame(0.91, $metadata['threshold']);
        $this->assertSame(0.88, $metadata['evaluation']['accuracy']);
        $this->assertSame('fixture-corpus', $metadata['evaluation']['dataset_name']);
    }

    public function test_runtime_unavailable_returns_n_a_without_throwing(): void
    {
        Http::fake([
            'http://bert.test/version' => Http::response(['model_status' => 'failed'], 503),
        ]);
        config(['services.bert.url' => 'http://bert.test']);

        $metadata = app(BertRuntimeMetadata::class)->fetch();

        $this->assertFalse($metadata['available']);
        $this->assertNull($metadata['model_version']);
        $this->assertNull($metadata['threshold']);
        $this->assertNull($metadata['evaluation']['accuracy']);
    }

    public function test_runtime_timeout_returns_n_a_without_throwing(): void
    {
        Http::fake(fn () => throw new ConnectionException('BERT metadata timeout'));
        config(['services.bert.url' => 'http://bert.test']);

        $metadata = app(BertRuntimeMetadata::class)->fetch();

        $this->assertFalse($metadata['available']);
        $this->assertNull($metadata['model_version']);
        $this->assertNull($metadata['threshold']);
        $this->assertNull($metadata['evaluation']['accuracy']);
    }

    public function test_malformed_runtime_metadata_is_unavailable(): void
    {
        Http::fake([
            'http://bert.test/version' => Http::response('not-json', 200),
        ]);
        config(['services.bert.url' => 'http://bert.test']);

        $metadata = app(BertRuntimeMetadata::class)->fetch();

        $this->assertFalse($metadata['available']);
        $this->assertNull($metadata['model_version']);
        $this->assertNull($metadata['threshold']);
    }

    public function test_mismatched_evaluation_version_is_not_displayed(): void
    {
        Http::fake([
            'http://bert.test/version' => Http::response([
                'model_version' => 'v2.0.0',
                'model_status' => 'ready',
                'threshold' => 0.9,
                'evaluation_model_version' => 'v1.0.0',
                'evaluation_accuracy' => 1.0,
                'evaluation_sample_count' => 781,
            ]),
        ]);
        config(['services.bert.url' => 'http://bert.test']);

        $metadata = app(BertRuntimeMetadata::class)->fetch();

        $this->assertTrue($metadata['available']);
        $this->assertSame('v2.0.0', $metadata['model_version']);
        $this->assertNull($metadata['evaluation']['accuracy']);
        $this->assertNull($metadata['evaluation']['sample_count']);
    }

    public function test_homepage_uses_runtime_evaluation_and_defined_latency(): void
    {
        $this->mockRuntime([
            'available' => true,
            'model_status' => 'ready',
            'model_version' => 'v9.9.9',
            'threshold' => 0.91,
            'temperature' => 0.87,
            'labels' => ['valid', 'hoax'],
            'evaluation' => [
                'model_version' => 'v9.9.9',
                'accuracy' => 0.88,
                'macro_f1' => 0.86,
                'sample_count' => 120,
                'dataset_name' => 'fixture-corpus',
                'dataset_version' => 'v2',
                'split' => 'held-out test',
                'exported_at' => '2026-09-01T00:00:00+00:00',
            ],
        ]);

        foreach ([40, 42, 44] as $inferenceMs) {
            $submission = Submission::create([
                'input_type' => 'text',
                'raw_input' => 'Teks selesai untuk telemetry.',
                'status' => 'completed',
                'processing_completed_at' => now(),
            ]);
            DetectionResult::create([
                'submission_id' => $submission->id,
                'label' => 'valid',
                'confidence_score' => 0.91,
                'model_version' => 'v9.9.9',
                'inference_ms' => $inferenceMs,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('v9.9.9')
            ->assertSee('88.00%')
            ->assertSee('held-out test')
            ->assertSee('n=120')
            ->assertSee('fixture-corpus v2')
            ->assertSee('export 2026-09-01T00:00:00+00:00')
            ->assertSee('42.0 ms')
            ->assertDontSee('95%+')
            ->assertDontSee('15s');
    }

    public function test_homepage_and_widget_render_n_a_when_runtime_is_unavailable(): void
    {
        $this->mockRuntime([
            'available' => false,
            'model_status' => 'unavailable',
            'model_version' => null,
            'threshold' => null,
            'temperature' => null,
            'labels' => null,
            'evaluation' => [
                'model_version' => null,
                'accuracy' => null,
                'macro_f1' => null,
                'sample_count' => null,
                'dataset_name' => null,
                'dataset_version' => null,
                'split' => null,
                'exported_at' => null,
            ],
        ]);

        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('n/a');

        $stats = $this->widgetStats();
        $this->assertSame('n/a', $stats[0]->getValue());
        $this->assertSame('n/a', $stats[1]->getValue());
        $this->assertSame('n/a', $stats[2]->getValue());
    }

    public function test_latency_is_n_a_when_active_model_has_too_few_samples(): void
    {
        $this->mockRuntime([
            'available' => true,
            'model_status' => 'ready',
            'model_version' => 'v9.9.9',
            'threshold' => 0.91,
            'temperature' => 0.87,
            'labels' => ['valid', 'hoax'],
            'evaluation' => [
                'model_version' => null,
                'accuracy' => null,
                'macro_f1' => null,
                'sample_count' => null,
                'dataset_name' => null,
                'dataset_version' => null,
                'split' => null,
                'exported_at' => null,
            ],
        ]);
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Satu sampel saja.',
            'status' => 'completed',
        ]);
        DetectionResult::create([
            'submission_id' => $submission->id,
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'v9.9.9',
            'inference_ms' => 42,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()->assertSee('Median BERT inference')->assertSee('n/a');
    }

    public function test_widget_does_not_call_most_common_historical_version_active(): void
    {
        $this->mockRuntime([
            'available' => true,
            'model_status' => 'ready',
            'model_version' => 'v9.9.9',
            'threshold' => 0.77,
            'temperature' => 0.87,
            'labels' => ['valid', 'hoax'],
            'evaluation' => [
                'model_version' => null,
                'accuracy' => null,
                'macro_f1' => null,
                'sample_count' => null,
                'dataset_name' => null,
                'dataset_version' => null,
                'split' => null,
                'exported_at' => null,
            ],
        ]);

        $old = DetectionResult::factory()->create(['model_version' => 'v1.0.0']);
        DetectionResult::factory()->create(['model_version' => 'v1.0.0']);
        DetectionResult::factory()->create(['model_version' => 'v9.9.9']);

        $stats = $this->widgetStats();

        $this->assertSame('v9.9.9', $stats[0]->getValue());
        $this->assertSame('0.7700', $stats[1]->getValue());
        $this->assertNotSame($old->model_version, $stats[0]->getValue());
        $this->assertStringContainsString('v1.0.0', (string) $stats[3]->getDescription());
    }

    private function mockRuntime(array $metadata): void
    {
        $mock = Mockery::mock(BertRuntimeMetadata::class);
        $mock->shouldReceive('fetch')->andReturn($metadata);
        $this->app->instance(BertRuntimeMetadata::class, $mock);
    }

    /** @return list<Stat> */
    private function widgetStats(): array
    {
        $method = new ReflectionMethod(ModelVersionWidget::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke(new ModelVersionWidget);
    }
}

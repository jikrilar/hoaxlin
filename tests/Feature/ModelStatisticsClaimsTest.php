<?php

namespace Tests\Feature;

use App\Filament\Widgets\ModelVersionWidget;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use App\Services\Bert\BertRuntimeMetadata;
use DOMDocument;
use DOMXPath;
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

    public function test_public_statistics_preserve_different_populations_without_publishing_private_records(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        config(['app.timezone' => 'Asia/Jakarta']);
        Http::preventStrayRequests();
        $owner = User::factory()->create(['email' => 'private-owner@example.test']);
        foreach ([['2024-01-01', 'completed', 'hoax'], ['2026-09-02', 'pending', null], ['2026-09-03', 'processing', 'valid'], ['2026-09-04', 'failed', 'meragukan'], ['2026-09-05', 'completed', 'hoax'], ['2026-09-06', 'completed', 'valid']] as [$date, $status, $label]) {
            $submission = Submission::factory()->for($owner)->create(['status' => $status, 'created_at' => $date.' 12:00:00', 'raw_input' => 'PRIVATE_STATISTICS_INPUT']);
            if ($label !== null) {
                DetectionResult::factory()->for($submission)->create(['label' => $label, 'explanation' => 'PRIVATE_STATISTICS_EXPLANATION']);
            }
        }
        $response = $this->get(route('statistik'))->assertOk()->assertViewIs('statistik')
            ->assertSee('Statistik pemeriksaan')->assertSee('5 submission')->assertSee('5 hasil dengan label tersimpan')
            ->assertSee('dari semua status')->assertSee('tanpa batas periode 12 bulan dan tanpa filter status')
            ->assertSee('bukan prevalensi hoaks di masyarakat atau akurasi model')->assertSee('menggabungkan versi model')
            ->assertSee('Informasi belum terverifikasi oleh sumber terpercaya')->assertSee('Ini bukan bukti bahwa informasi benar atau palsu.')
            ->assertDontSee('PRIVATE_STATISTICS_INPUT')->assertDontSee('PRIVATE_STATISTICS_EXPLANATION')->assertDontSee('private-owner@example.test')
            ->assertDontSee('95%+')->assertDontSee('cdn.jsdelivr.net');
        $this->assertSame(5, $response->viewData('trend')->sum('total'));
        $this->assertEquals(['valid' => 2, 'hoax' => 2, 'meragukan' => 1], $response->viewData('byTopic'));
        $dom = $this->statisticsDom($response->getContent());
        $this->assertSame(12, $dom->query('//table[@id="statistics-trend-table"]/tbody/tr')->length);
        $row = $dom->query('//tr[@data-statistics-month="2026-09"]/*');
        $this->assertSame(['September 2026', '5', '1', '2'], array_map(fn ($cell) => trim($cell->textContent), iterator_to_array($row)));
        $this->assertSame('2', $dom->evaluate('string(//tr[@data-statistics-label="hoax"]/td)'));
        $this->assertSame(1, $dom->query('//main')->length);
        $this->assertSame(1, $dom->query('//main//h1')->length);
        $this->assertSame(1, $dom->query('//figure[@hidden]//canvas[@role="img"]')->length);
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, '<canvas'), strpos($html, 'id="statistics-trend-table"'));
        $chart = json_decode($dom->evaluate('string(//canvas/@data-statistics-trend)'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['label' => 'September 2026', 'total' => 5, 'hoax' => 1, 'valid' => 2], $chart[11]);
        Http::assertNothingSent();
    }

    public function test_empty_statistics_keep_twelve_zero_months_and_native_tables_without_a_chart(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $response = $this->get(route('statistik'))->assertOk()->assertSee('Belum ada submission dalam periode 12 bulan ini.')
            ->assertSee('Belum ada hasil dengan label tersimpan.')->assertSee('0 submission');
        $dom = $this->statisticsDom($response->getContent());
        $this->assertSame(36, $dom->query('//table[@id="statistics-trend-table"]/tbody/tr/td[text()="0"]')->length);
        $this->assertSame(3, $dom->query('//table[@id="statistics-label-table"]/tbody/tr/td[text()="0"]')->length);
        $this->assertSame(0, $dom->query('//canvas')->length);
        $this->assertSame(2, $dom->query('//table/caption')->length);
        $this->assertSame(0, $dom->query('//main//*[@style]')->length);
    }

    public function test_historical_labels_do_not_turn_an_empty_recent_period_into_an_empty_distribution(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $submission = Submission::factory()->create(['created_at' => '2020-01-01 00:00:00', 'status' => 'completed']);
        DetectionResult::factory()->for($submission)->create(['label' => 'meragukan']);
        $this->get(route('statistik'))->assertOk()->assertSee('0 submission')->assertSee('1 hasil dengan label tersimpan')
            ->assertSee('Belum ada submission dalam periode 12 bulan ini.')->assertDontSee('Belum ada hasil dengan label tersimpan.');
    }

    private function statisticsDom(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function test_information_pages_do_not_turn_runtime_or_corpus_metrics_into_marketing_claims(): void
    {
        $runtime = Mockery::mock(BertRuntimeMetadata::class);
        $runtime->shouldNotReceive('fetch');
        $this->app->instance(BertRuntimeMetadata::class, $runtime);
        Http::preventStrayRequests();

        foreach (['cara-kerja', 'tentang'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertDontSee('95%+')->assertDontSee('100% akurat')->assertDontSee('15s')
                ->assertDontSee('Evaluasi model aktif')->assertDontSee('Jenis Input Didukung')
                ->assertDontSee('akurasi universal')->assertDontSee('Notifikasi Hasil')
                ->assertSee('prediksi model, bukan verifikasi kebenaran yang definitif');
        }

        Http::assertNothingSent();
    }

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

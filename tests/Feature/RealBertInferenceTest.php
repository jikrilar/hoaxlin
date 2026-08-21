<?php

namespace Tests\Feature;

use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Jobs\GenerateSubmissionExplanation;
use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use App\Services\Extraction\TextInputExtractor;
use App\Services\Extraction\TextExtractorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves the real Laravel ↔ FastAPI ↔ IndoBERT path (C5).
 *
 * Unlike AiPipelineTest which uses FakeClassifier, this test hits the real
 * FastAPI when BERT_SERVICE_URL is reachable. When the service is not running
 * the tests are skipped so CI without the model still passes.
 *
 * Run with the model wired (C4):
 *   $env:BERT_MODEL_PATH="C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0"
 *   .\.venv\Scripts\python.exe -m uvicorn app.main:app --port 8001
 *   php artisan test --filter=RealBertInference
 */
class RealBertInferenceTest extends TestCase
{
    use RefreshDatabase;

    private function isBertServiceAvailable(): bool
    {
        $url = config('services.bert.url', 'http://127.0.0.1:8001');
        $token = config('services.bert.internal_token');
        if (! $token) {
            return false;
        }

        try {
            $response = Http::baseUrl($url)
                ->timeout(3)
                ->withToken($token)
                ->get('/health/ready');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_real_bert_service_is_reachable_and_ready(): void
    {
        if (! $this->isBertServiceAvailable()) {
            $this->markTestSkipped('BERT service not reachable — start FastAPI with BERT_MODEL_PATH set (C4).');
        }

        $url = config('services.bert.url');
        $token = config('services.bert.internal_token');

        $response = Http::baseUrl($url)->withToken($token)->get('/version');

        $this->assertTrue($response->successful(), 'Version endpoint should return 200');
        $json = $response->json();

        $this->assertSame('ready', $json['model_status']);
        $this->assertSame(['valid', 'hoax'], $json['model_labels']);
        $this->assertEqualsWithDelta(0.99, $json['threshold'], 0.01);
        $this->assertNotEmpty($json['model_version']);
    }

    public function test_real_classify_persists_label_confidence_and_model_version(): void
    {
        if (! $this->isBertServiceAvailable()) {
            $this->markTestSkipped('BERT service not reachable.');
        }

        // Use the real resolver + classifier (no fakes)
        $this->app->instance(
            TextExtractorResolver::class,
            new TextExtractorResolver([new TextInputExtractor])
        );
        // Ensure threshold matches the exported model (0.99), not the default 0.65
        config(['services.bert.confidence_threshold' => 0.99]);

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Beredar unggahan di media sosial yang mengklaim bansos Rp 50 juta untuk semua warga tanpa syarat. Informasi ini perlu diverifikasi kebenarannya.',
            'status' => 'pending',
        ]);

        // Drive the full pipeline synchronously (no Queue::fake)
        ProcessSubmission::dispatchSync($submission);

        $submission->refresh()->load('detectionResult');

        $this->assertSame('completed', $submission->status);
        $this->assertNotNull($submission->detectionResult, 'DetectionResult should be persisted');
        $this->assertContains($submission->detectionResult->label, ['hoax', 'valid', 'meragukan']);
        $this->assertGreaterThanOrEqual(0.0, $submission->detectionResult->confidence_score);
        $this->assertLessThanOrEqual(1.0, $submission->detectionResult->confidence_score);
        $this->assertNotEmpty($submission->detectionResult->model_version);
        $this->assertSame('v1.0.0', $submission->detectionResult->model_version);
        $this->assertNotNull($submission->detectionResult->raw_scores);
        $this->assertArrayHasKey('valid', $submission->detectionResult->raw_scores);
        $this->assertArrayHasKey('hoax', $submission->detectionResult->raw_scores);
    }

    public function test_real_classify_applies_meragukan_threshold_on_low_confidence(): void
    {
        if (! $this->isBertServiceAvailable()) {
            $this->markTestSkipped('BERT service not reachable.');
        }

        $this->app->instance(
            TextExtractorResolver::class,
            new TextExtractorResolver([new TextInputExtractor])
        );
        config(['services.bert.confidence_threshold' => 0.99]);

        // Short ambiguous text — model is expected to return meragukan (confidence < 0.99)
        // via FastAPI threshold, and Laravel should preserve it.
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Halo dunia hello world',
            'status' => 'pending',
        ]);

        ExtractSubmissionText::dispatchSync($submission->id);
        ClassifySubmission::dispatchSync($submission->id);

        $submission->refresh()->load('detectionResult');

        $this->assertNotNull($submission->detectionResult);
        // With threshold 0.99, low-confidence predictions become meragukan
        // We assert the contract: label is valid/hoax/meragukan and confidence < threshold implies meragukan
        if ($submission->detectionResult->confidence_score < 0.99) {
            $this->assertSame('meragukan', $submission->detectionResult->label);
        }
        $this->assertNotEmpty($submission->detectionResult->model_version);
    }

    public function test_real_pipeline_persists_via_queue_worker(): void
    {
        if (! $this->isBertServiceAvailable()) {
            $this->markTestSkipped('BERT service not reachable.');
        }

        $this->app->instance(
            TextExtractorResolver::class,
            new TextExtractorResolver([new TextInputExtractor])
        );
        config(['services.bert.confidence_threshold' => 0.99]);

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Viral video yang memperlihatkan Presiden mengumumkan lockdown total selama 30 hari di seluruh Indonesia.',
            'status' => 'pending',
        ]);

        // Use the full async path: dispatch ProcessSubmission and run the queue
        ProcessSubmission::dispatch($submission);

        // Process the queue synchronously for this test (database queue, run once)
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertExitCode(0);
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'inference'])->assertExitCode(0);
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'explanation'])->assertExitCode(0);

        $submission->refresh()->load('detectionResult');

        // At least classification should have run; explanation may still be pending if OpenAI not configured
        $this->assertNotNull($submission->detectionResult);
        $this->assertContains($submission->detectionResult->label, ['hoax', 'valid', 'meragukan']);
    }
}

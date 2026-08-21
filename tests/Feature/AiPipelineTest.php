<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\Enums\DetectionLabel;
use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Jobs\GenerateSubmissionExplanation;
use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Extraction\TextInputExtractor;
use App\Services\Fakes\FakeClassifier;
use App\Services\Fakes\FakeExplainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_submission_drives_full_pipeline_to_completion(): void
    {
        $classifier = (new FakeClassifier)->willReturn(new Classification(
            DetectionLabel::Valid,
            0.87,
            'indobert-test-v1',
            ['valid' => 0.87, 'hoax' => 0.08, 'meragukan' => 0.05],
            9,
        ));
        $explainer = (new FakeExplainer)->willReturn(Explanation::ready('Hasil pemeriksaan lengkap.', 'openai-test'));
        $this->app->instance(Classifier::class, $classifier);
        $this->app->instance(Explainer::class, $explainer);
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Sebuah klaim yang perlu diperiksa kebenarannya berdasarkan sumber resmi.',
            'status' => 'pending',
        ]);

        ProcessSubmission::dispatchSync($submission);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('done', $submission->processing_stage);
        $this->assertNotNull($submission->content_hash);
        $this->assertSame('valid', $submission->detectionResult->label);
        $this->assertSame('Hasil pemeriksaan lengkap.', $submission->detectionResult->explanation);
        $this->assertSame('ready', $submission->detectionResult->explanation_status);
        $this->assertSame('indobert-test-v1', $submission->detectionResult->model_version);
    }

    public function test_text_submission_runs_through_pipeline_and_persists_result(): void
    {
        $classifier = (new FakeClassifier)->willReturn(new Classification(
            DetectionLabel::Hoax,
            0.94,
            'indobert-test-v1',
            ['valid' => 0.03, 'hoax' => 0.94, 'meragukan' => 0.03],
            12,
        ));
        $explainer = (new FakeExplainer)->willReturn(Explanation::ready('Narasi hasil pengujian.', 'openai-test'));
        $this->app->instance(Classifier::class, $classifier);
        $this->app->instance(Explainer::class, $explainer);
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Pesan berantai ini mengandung klaim tanpa sumber resmi yang perlu diperiksa lebih lanjut.',
            'status' => 'pending',
        ]);

        ExtractSubmissionText::dispatchSync($submission->id);
        ClassifySubmission::dispatchSync($submission->id);
        GenerateSubmissionExplanation::dispatchSync($submission->id);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('done', $submission->processing_stage);
        $this->assertNotNull($submission->content_hash);
        $this->assertSame('hoax', $submission->detectionResult->label);
        $this->assertSame('Narasi hasil pengujian.', $submission->detectionResult->explanation);
        $this->assertSame('ready', $submission->detectionResult->explanation_status);
        $this->assertGreaterThanOrEqual(7, $submission->processingEvents()->count());
    }

    public function test_unavailable_explanation_degrades_without_failing_classification(): void
    {
        $this->app->instance(Classifier::class, new FakeClassifier);
        $this->app->instance(Explainer::class, (new FakeExplainer)->willDegrade('Penjelasan tidak tersedia.'));
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'Informasi ini belum memiliki sumber yang cukup sehingga perlu dilakukan pemeriksaan lanjutan.',
            'status' => 'pending',
        ]);

        ExtractSubmissionText::dispatchSync($submission->id);
        ClassifySubmission::dispatchSync($submission->id);
        GenerateSubmissionExplanation::dispatchSync($submission->id);

        $submission->refresh()->load('detectionResult');
        $this->assertSame('completed', $submission->status);
        $this->assertSame('unavailable', $submission->detectionResult->explanation_status);
        $this->assertSame('Penjelasan tidak tersedia.', $submission->detectionResult->explanation);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationProvenanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_translated_pdf_contains_user_facing_provenance_only(): void
    {
        $submission = $this->completedSubmission([
            'source_language' => 'en',
            'translated_text' => 'Berita dalam Bahasa Indonesia.',
            'translation_provider' => 'openai',
            'translation_model' => 'gpt-4o-mini',
            'translation_input_tokens' => 123,
            'translation_output_tokens' => 45,
            'translation_estimated_cost_usd' => 0.012345,
        ]);

        $html = view('hasil-pdf', [
            'submission' => $submission->load('detectionResult'),
            'result' => $submission->detectionResult,
        ])->render();

        $this->assertStringContainsString('Bahasa Sumber', $html);
        $this->assertStringContainsString('en', $html);
        $this->assertStringContainsString('Provider Terjemahan', $html);
        $this->assertStringContainsString('openai', $html);
        $this->assertStringContainsString('Model Terjemahan', $html);
        $this->assertStringContainsString('gpt-4o-mini', $html);
        $this->assertStringNotContainsString('translation_input_tokens', $html);
        $this->assertStringNotContainsString('translation_output_tokens', $html);
        $this->assertStringNotContainsString('translation_estimated_cost_usd', $html);
        $this->assertStringNotContainsString('123', $html);
        $this->assertStringNotContainsString('0.012345', $html);

        $this->actingAs($submission->user)
            ->get(route('hasil.pdf', $submission))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_untranslated_pdf_does_not_claim_provider_or_model(): void
    {
        $submission = $this->completedSubmission(['source_language' => 'id']);
        $html = view('hasil-pdf', [
            'submission' => $submission->load('detectionResult'),
            'result' => $submission->detectionResult,
        ])->render();

        $this->assertStringContainsString('Bahasa Sumber', $html);
        $this->assertStringContainsString('>id<', $html);
        $this->assertStringContainsString('Tidak diterjemahkan', $html);
        $this->assertStringNotContainsString('openai', $html);
        $this->assertStringNotContainsString('gpt-4o-mini', $html);
    }

    /** @param array<string, mixed> $attributes */
    private function completedSubmission(array $attributes = []): Submission
    {
        $user = User::factory()->create();
        $submission = Submission::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => 'completed',
            'processing_stage' => 'done',
        ], $attributes));

        DetectionResult::factory()->create([
            'submission_id' => $submission->id,
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'indobert-p2-1-test',
            'explanation' => 'Penjelasan final.',
            'explanation_status' => 'ready',
        ]);

        return $submission->fresh(['user', 'detectionResult']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ExplanationStatus;
use App\Enums\ProcessingStage;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** T2.7: result summary, next steps, evidence similarity and clipboard summary. */
class ResultSummaryPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_shows_badge_description_score_and_next_steps_without_claiming_truth(): void
    {
        [$user, $submission] = $this->completedSubmission('hoax', 0.91);

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee('Prediksi model: Hoaks')
            ->assertSee('Pola teks ini mirip dengan hoaks. Cek di sumber terpercaya sebelum membagikannya.')
            ->assertSee('data-confidence-badge', false)
            ->assertSee('Skor Keyakinan Model')
            ->assertSee('91%')
            ->assertSee('Langkah selanjutnya')
            ->assertSee('Periksa sumber asli informasi ini')
            ->assertSee('Jangan bagikan sebelum menemukan sumber yang dapat dipercaya.')
            // No invented severity band and no external fact-check links.
            ->assertDontSee('Rendah')
            ->assertDontSee('Sedang')
            ->assertDontSee('Tinggi')
            ->assertDontSee('rel="noopener noreferrer">Fact')
            ->assertDontSee('cek fakta');
    }

    public function test_abstention_keeps_one_disclaimer_and_points_to_official_sources(): void
    {
        [$user, $submission] = $this->completedSubmission('meragukan', 0.5);

        $html = $this->actingAs($user)->get(route('hasil', $submission))->assertOk()->getContent();
        $summary = $this->summaryFragment($html);

        // The abstention caveat ("bukan berarti informasi benar atau salah") appears once,
        // inside the abstention description, and no second copy repeats it.
        $this->assertSame(1, substr_count($summary, 'Ini bukan bukti bahwa informasi benar atau palsu.'), 'the abstention caveat is not duplicated');
        $this->assertStringContainsString('Model belum cukup yakin', $summary);
        $this->assertStringContainsString('Cari konfirmasi dari sumber resmi', $summary);
        $this->assertStringContainsString('warning', $summary);
    }

    public function test_evidence_cards_label_similarity_as_relevance_not_confidence_and_offer_a_source_button(): void
    {
        [$user, $submission] = $this->completedSubmission('valid', 0.7);
        EvidenceReference::create([
            'submission_id' => $submission->id,
            'document_id' => 'doc-sim',
            'title' => 'Rujukan dengan skor kemiripan',
            'source' => 'Sumber uji',
            'source_url' => 'https://reference.example/doc-sim',
            'published_at' => '2026-09-10',
            'similarity_score' => 0.81234567,
            'rank' => 1,
            'snippet' => 'Kutipan uji.',
        ]);

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee('Kemiripan dengan teks Anda')
            ->assertSee('81%')
            ->assertDontSee('81% keyakinan')
            ->assertSee('Buka sumber')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_copy_summary_payload_contains_label_description_and_date_but_no_id_or_url(): void
    {
        [$user, $submission] = $this->completedSubmission('valid', 0.7);

        $html = $this->actingAs($user)->get(route('hasil', $submission))->assertOk()->getContent();

        $this->assertStringContainsString('data-copy-summary', $html);
        preg_match('/<template[^>]*data-summary-source[^>]*>(.*?)<\/template>/s', $html, $template);
        $this->assertNotEmpty($template[1] ?? null, 'summary source text is server-rendered for no-JS/PDF parity');
        $text = trim(strip_tags(html_entity_decode($template[1])));
        $this->assertStringContainsString('Prediksi model: Valid', $text);
        $this->assertStringContainsString('Pola teks ini mirip dengan informasi yang valid.', $text);
        $this->assertStringContainsString('Dikirim: '.$submission->created_at->format('d M Y'), $text);
        $this->assertStringContainsString('bukan verifikasi kebenaran', $text);
        // No sharing capability or link: the ID/URL must not leak into a pasted summary.
        // (Dates such as "12 Nov 2026" contain digits, so only structural markers are asserted.)
        $this->assertDoesNotMatchRegularExpression('/\bid\s*[:#]/iu', $text);
        $this->assertStringNotContainsString('http', mb_strtolower($text));
        $this->assertStringNotContainsString('/hasil', $text);
        $this->assertStringNotContainsString(route('hasil', $submission->id), $text);
        $this->assertSame(0, preg_match('/\b\d{2,6}\b.*\/hasil/iu', $text));
    }

    public function test_feedback_comment_stays_hidden_until_a_choice_is_made(): void
    {
        [$user, $submission] = $this->completedSubmission('valid', 0.7);

        $this->actingAs($user)->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('data-feedback-comment', false)
            ->assertSee('Apakah prediksi model sesuai menurut Anda?');
    }

    /** @return array{0: User, 1: Submission} */
    private function completedSubmission(string $label, float $confidence): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Informasi untuk halaman hasil. ', 4),
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
        ]);
        $submission->detectionResult()->create([
            'label' => $label,
            'confidence_score' => $confidence,
            'model_version' => 'v1.0.0',
            'raw_scores' => ['valid' => 0.1, 'hoax' => 0.9],
            'inference_ms' => 10,
            'explanation_status' => ExplanationStatus::Ready->value,
            'explanation' => 'Penjelasan uji.',
        ]);

        return [$user, $submission];
    }

    private function summaryFragment(string $html): string
    {
        // Slice from the summary landmark up to the next section heading.
        $start = strpos($html, 'data-result-final');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="next-steps-heading"', $start);

        return substr($html, $start, ($end === false ? $start + 4000 : $end) - $start);
    }
}

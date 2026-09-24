<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Models\DetectionResult;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultEvidencePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_page_renders_evidence_in_rank_order_with_provenance_and_model_confidence(): void
    {
        [$user, $submission] = $this->completedSubmission(confidence: 0.63);
        $second = $this->evidence($submission, [
            'document_id' => 'doc-002',
            'title' => 'Rujukan peringkat dua',
            'source_url' => 'https://reference.example/doc-002',
            'published_at' => '2026-09-10',
            'similarity_score' => 0.99,
            'rank' => 2,
            'snippet' => 'Kutipan peringkat dua.',
        ]);
        $first = $this->evidence($submission, [
            'document_id' => 'doc-001',
            'title' => 'Rujukan peringkat satu',
            'source' => 'Sumber resmi pertama',
            'source_url' => 'https://reference.example/doc-001',
            'published_at' => null,
            'similarity_score' => 0.41,
            'rank' => 1,
            'snippet' => 'Kutipan peringkat satu dengan <script>alert(1)</script>.',
        ]);

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee('Status Verifikasi')
            ->assertSee('Skor Keyakinan Model')
            ->assertSee('63%')
            ->assertDontSee('99%')
            ->assertSee('Bukti/Rujukan Terkait')
            ->assertSee($first->title)
            ->assertSee($first->source)
            ->assertSee($first->source_url)
            ->assertSee('Tanggal publikasi tidak tersedia')
            ->assertSee('Kutipan peringkat satu dengan &lt;script&gt;alert(1)&lt;/script&gt;.', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Rujukan peringkat dua')
            ->assertSee('10 Sep 2026');

        $body = $response->getContent();
        $firstPosition = strpos($body, 'Rujukan peringkat satu');
        $secondPosition = strpos($body, 'Rujukan peringkat dua');
        $this->assertIsInt($firstPosition);
        $this->assertIsInt($secondPosition);
        $this->assertLessThan($secondPosition, $firstPosition);
        $this->assertMatchesRegularExpression(
            '/<a href="https:\/\/reference\.example\/doc-001" target="_blank" rel="noopener noreferrer">https:\/\/reference\.example\/doc-001<\/a>/',
            $body,
        );
    }

    public function test_empty_evidence_shows_honest_state_and_keeps_classifier_result_and_explanation(): void
    {
        [$user, $submission] = $this->completedSubmission(
            confidence: 0.76,
            explanation: 'Penjelasan classifier tetap tersedia tanpa rujukan.',
        );

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee('Tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini.')
            ->assertSee('Valid')
            ->assertSee('76%')
            ->assertSee('Penjelasan classifier tetap tersedia tanpa rujukan.')
            ->assertDontSee('Sumber Contoh')
            ->assertDontSee('https://example.invalid');

        $this->assertDatabaseHas('detection_results', [
            'submission_id' => $submission->id,
            'label' => 'valid',
            'confidence_score' => '0.7600',
        ]);
    }

    public function test_meragukan_label_is_only_reworded_for_presentation(): void
    {
        [$user, $submission, $result] = $this->completedSubmission(
            label: 'meragukan',
            confidence: 0.85,
        );
        $displayLabel = 'Informasi belum terverifikasi oleh sumber terpercaya';

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee($displayLabel)
            ->assertSee('Ini bukan bukti bahwa informasi benar atau palsu.');
        $this->assertStringContainsString('role="status" aria-label="'.$displayLabel.'"', $response->getContent());

        $this->assertSame('meragukan', $result->fresh()->label);
        $this->assertSame('0.8500', $result->fresh()->confidence_score);
    }

    public function test_untrusted_source_url_is_never_rendered_as_a_link(): void
    {
        [$user, $submission] = $this->completedSubmission();
        $this->evidence($submission, [
            'source_url' => 'javascript:alert(1)',
            'rank' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('hasil', $submission));

        $response->assertOk()
            ->assertSee('Tautan sumber tidak tersedia.')
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_result_and_history_pages_only_render_evidence_for_the_authorized_submission(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create();
        [, $ownedSubmission] = $this->completedSubmission(user: $owner);
        [, $otherSubmission] = $this->completedSubmission(user: $otherUser);
        $this->evidence($ownedSubmission, ['title' => 'Rujukan milik submission ini']);
        $this->evidence($otherSubmission, ['title' => 'Rujukan rahasia submission lain']);

        $this->actingAs($owner)
            ->get(route('hasil', $ownedSubmission))
            ->assertOk()
            ->assertSee('Rujukan milik submission ini')
            ->assertDontSee('Rujukan rahasia submission lain');

        $this->get(route('riwayat.show', $ownedSubmission))
            ->assertOk()
            ->assertSee('Rujukan milik submission ini')
            ->assertDontSee('Rujukan rahasia submission lain');

        $forbidden = $this->actingAs($otherUser)->get(route('hasil', $ownedSubmission));
        $forbidden->assertForbidden();
        $this->assertStringNotContainsString('Rujukan milik submission ini', $forbidden->getContent());
    }

    /** @return array{User, Submission, DetectionResult} */
    private function completedSubmission(
        string $label = 'valid',
        float $confidence = 0.82,
        string $explanation = 'Penjelasan netral untuk hasil pengujian.',
        ?User $user = null,
    ): array {
        $user ??= User::factory()->create();
        $submission = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
        ]);
        $result = DetectionResult::factory()->create([
            'submission_id' => $submission->id,
            'label' => $label,
            'confidence_score' => $confidence,
            'model_version' => 'indobert-test-v1',
            'explanation' => $explanation,
            'explanation_status' => 'ready',
        ]);

        return [$user, $submission, $result];
    }

    /** @param array<string, mixed> $overrides */
    private function evidence(Submission $submission, array $overrides = []): EvidenceReference
    {
        return EvidenceReference::create(array_merge([
            'submission_id' => $submission->id,
            'document_id' => 'doc-default',
            'title' => 'Judul rujukan bawaan',
            'source' => 'Sumber tepercaya',
            'source_url' => 'https://reference.example/default',
            'published_at' => '2026-09-01',
            'similarity_score' => 0.70,
            'rank' => 1,
            'snippet' => 'Kutipan dari rujukan tersimpan.',
            'knowledge_base_version' => 'knowledge-base-v1',
        ], $overrides));
    }
}

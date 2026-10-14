<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\EvidenceReference;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmissionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_paginated_and_only_contains_owners_submissions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createSubmissions($user, 16);
        $foreignSubmission = Submission::create([
            'user_id' => $otherUser->id,
            'input_type' => 'text',
            'raw_input' => 'Rahasia pengguna lain yang tidak boleh terlihat.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('riwayat'));

        $response->assertOk()
            ->assertViewHas('submissions', fn ($submissions): bool => $submissions->count() === 15 && $submissions->total() === 16)
            ->assertDontSee($foreignSubmission->raw_input);
    }

    public function test_history_can_search_and_filter_submissions(): void
    {
        $user = User::factory()->create();
        $matching = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'url',
            'source_url' => 'https://example.com/berita-khusus',
            'status' => 'completed',
        ]);
        Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => 'Konten lain yang tidak sesuai dengan pencarian.',
            'status' => 'pending',
        ]);
        DetectionResult::create([
            'submission_id' => $matching->id,
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'test',
        ]);

        $response = $this->actingAs($user)->get(route('riwayat', [
            'search' => 'berita-khusus',
            'input_type' => 'url',
            'status' => 'completed',
            'label' => 'valid',
        ]));

        $response->assertOk()
            ->assertViewHas('submissions', fn ($submissions): bool => $submissions->total() === 1)
            ->assertSee('https://example.com/berita-khusus');
    }

    public function test_pagination_keeps_filters_and_summary_still_counts_all_owned_labels(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 16) as $index) {
            $submission = Submission::factory()->for($user)->create(['input_type' => 'text', 'raw_input' => 'Informasi cocok '.$index, 'status' => 'completed']);
            DetectionResult::factory()->for($submission)->create(['label' => 'valid', 'confidence_score' => 0.8]);
        }
        $excluded = Submission::factory()->for($user)->create(['input_type' => 'image', 'status' => 'completed']);
        DetectionResult::factory()->for($excluded)->create(['label' => 'hoax']);
        $other = Submission::factory()->for(User::factory()->create())->create(['status' => 'completed']);
        DetectionResult::factory()->for($other)->create(['label' => 'hoax']);
        $filters = ['search' => 'Informasi cocok', 'label' => 'valid', 'input_type' => 'text', 'status' => 'completed', 'sort' => 'oldest'];
        $this->actingAs($user)->get(route('riwayat', $filters + ['page' => 2]))
            ->assertOk()->assertViewHas('submissions', fn ($items): bool => $items->count() === 1 && $items->total() === 16 && $items->perPage() === 15 && $items->currentPage() === 2)
            ->assertViewHas('stats', ['valid' => 16, 'hoax' => 1, 'meragukan' => 0])
            ->assertSee('Ringkasan seluruh riwayat')->assertSee('Angka ini tidak mengikuti filter atau halaman aktif.')
            ->assertSee('Unduh seluruh riwayat CSV')->assertSee('name="label"', false)
            ->assertDontSee('name="page"', false);
        $this->get(route('riwayat', $filters))->assertViewHas('submissions', function ($items) use ($filters): bool {
            parse_str(parse_url($items->nextPageUrl(), PHP_URL_QUERY), $query);

            return $query == $filters + ['page' => '2'];
        });
    }

    public function test_sorting_uses_existing_date_and_confidence_order(): void
    {
        $user = User::factory()->create();
        $older = Submission::factory()->for($user)->create(['status' => 'completed', 'created_at' => now()->subDay()]);
        $newer = Submission::factory()->for($user)->create(['status' => 'completed', 'created_at' => now()]);
        DetectionResult::factory()->for($older)->create(['confidence_score' => 0.95]);
        DetectionResult::factory()->for($newer)->create(['confidence_score' => 0.5]);
        $this->actingAs($user)->get(route('riwayat', ['sort' => 'newest']))
            ->assertViewHas('submissions', fn ($items): bool => $items->first()->id === $newer->id);
        $this->get(route('riwayat', ['sort' => 'oldest']))
            ->assertViewHas('submissions', fn ($items): bool => $items->first()->id === $older->id);
        $this->get(route('riwayat', ['sort' => 'confidence']))
            ->assertViewHas('submissions', fn ($items): bool => $items->first()->id === $older->id);
    }

    public function test_video_filter_only_matches_uploaded_video_and_video_url_stays_invalid(): void
    {
        $user = User::factory()->create();
        $upload = Submission::factory()->for($user)->create(['input_type' => 'video']);
        Submission::factory()->for($user)->create(['input_type' => 'video_url']);
        $this->actingAs($user)->get(route('riwayat', ['input_type' => 'video']))
            ->assertOk()->assertViewHas('submissions', fn ($items): bool => $items->total() === 1 && $items->first()->id === $upload->id)
            ->assertSee('Video unggahan tidak mencakup tautan video.');
        $this->from(route('riwayat'))->get(route('riwayat', ['input_type' => 'video_url']))->assertSessionHasErrors('input_type');
    }

    public function test_invalid_filter_recovers_with_context_and_safe_old_input(): void
    {
        $user = User::factory()->create();
        $search = '<script id="attack">literal</script>'.str_repeat('a', 101);
        $this->actingAs($user)->from(route('riwayat'))->get(route('riwayat', ['search' => $search, 'label' => 'invalid']))
            ->assertRedirect(route('riwayat'))->assertSessionHasErrors(['search', 'label']);
        $this->get(route('riwayat'))->assertOk()->assertSee('Filter belum diterapkan')
            ->assertSee('href="#history-search"', false)->assertSee('href="#history-label"', false)
            ->assertDontSee('<script id="attack">', false);
        $this->from(route('riwayat'))->get(route('riwayat', ['search' => ['bad']]))->assertSessionHasErrors('search');
    }

    public function test_empty_history_differs_from_no_filter_matches_and_nonfinal_labels_are_unavailable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('riwayat'))->assertOk()->assertSee('Belum ada riwayat pemeriksaan');
        $submission = Submission::factory()->for($user)->create(['status' => 'processing', 'raw_input' => 'Informasi sedang diproses.']);
        DetectionResult::factory()->for($submission)->create(['label' => 'valid', 'confidence_score' => 0.91]);
        $this->get(route('riwayat'))->assertOk()->assertSee('Belum tersedia')->assertDontSee('91%');
        $this->get(route('riwayat', ['search' => 'missing-term']))->assertOk()->assertSee('Tidak ada pemeriksaan yang sesuai filter');
    }

    public function test_history_requires_a_verified_account(): void
    {
        $this->get(route('riwayat'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('riwayat'))->assertRedirect(route('verification.notice'));
    }

    public function test_history_detail_is_available_to_owner(): void
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => 'Isi lengkap submission milik pengguna untuk detail.',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('riwayat.show', $submission))
            ->assertOk()
            ->assertSee($submission->raw_input);
    }

    public function test_history_detail_cannot_be_viewed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $owner->id,
            'input_type' => 'text',
            'raw_input' => 'Isi privat milik pengguna lain.',
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser)->get(route('riwayat.show', $submission))->assertNotFound();
    }

    #[DataProvider('detailStates')]
    public function test_history_detail_and_direct_result_share_state_rendering_but_keep_their_back_context(string $status, bool $hasResult): void
    {
        $owner = User::factory()->create();
        $submission = Submission::factory()->for($owner)->create([
            'status' => $status, 'processing_stage' => $status === 'pending' ? 'queued' : 'explaining',
            'raw_input' => 'Input pemilik <script id="attack">literal</script>',
            'last_error_code' => $status === 'failed' ? 'PRIVATE_FAILURE_CODE' : null,
        ]);
        if ($hasResult) {
            DetectionResult::factory()->for($submission)->create(['label' => 'meragukan', 'confidence_score' => 0.63, 'explanation_status' => 'unavailable', 'explanation' => 'Stale private explanation']);
            EvidenceReference::create(['submission_id' => $submission->id, 'document_id' => 'own-reference', 'rank' => 1, 'title' => 'Referensi milik pemilik', 'source' => 'Fixture', 'source_url' => 'https://reference.example.test/fixture', 'similarity_score' => 0.8, 'snippet' => 'Kutipan fixture']);
        }
        $this->actingAs($owner);
        foreach (['riwayat.show' => 'Kembali ke riwayat', 'hasil' => 'Kembali ke beranda'] as $route => $back) {
            $response = $this->get(route($route, $submission))->assertOk()->assertViewIs('hasil')
                ->assertSee($back)->assertDontSee('<script id="attack">', false)
                ->assertDontSee('PRIVATE_FAILURE_CODE')->assertDontSee('Stale private explanation');
            $response->assertViewHas('submission', fn ($item): bool => $item->id === $submission->id);
            if ($status === 'completed' && $hasResult) {
                $response->assertSee('Informasi belum terverifikasi oleh sumber terpercaya')->assertSee('63%')
                    ->assertSee('Referensi milik pemilik')->assertSee('id="feedback-form"', false)
                    ->assertSee(route('hasil.pdf', $submission), false);
            } else {
                $response->assertDontSee('data-result-final', false)->assertDontSee('Referensi milik pemilik')
                    ->assertDontSee('id="feedback-form"', false)->assertDontSee('Unduh PDF');
                if ($status === 'completed') {
                    $response->assertSee('Hasil final tidak tersedia');
                } else {
                    $response->assertSee('data-progress-root', false)->assertSee(route('hasil.status', $submission), false)
                        ->assertSee($status === 'failed' ? 'Pemrosesan Gagal' : ($status === 'pending' ? 'Menunggu pemrosesan' : 'Sedang Menganalisis'));
                }
            }
        }
        // A direct refresh must retain the history route and its refresh URL.
        $this->get(route('riwayat.show', $submission))->assertOk()->assertSee('Kembali ke riwayat');
    }

    public static function detailStates(): array
    {
        return [['pending', true], ['processing', true], ['failed', true], ['completed', false], ['completed', true]];
    }

    public function test_foreign_and_missing_history_detail_are_404_even_for_an_admin_without_data_leaks(): void
    {
        $owner = User::factory()->create();
        $submission = Submission::factory()->for($owner)->create(['status' => 'completed', 'raw_input' => 'PRIVATE_OTHER_INPUT']);
        DetectionResult::factory()->for($submission)->create(['explanation_status' => 'ready', 'explanation' => 'PRIVATE_OTHER_EXPLANATION']);
        EvidenceReference::create(['submission_id' => $submission->id, 'document_id' => 'private-reference', 'rank' => 1, 'title' => 'PRIVATE_OTHER_EVIDENCE', 'source' => 'Fixture', 'source_url' => 'https://reference.example.test/fixture', 'similarity_score' => 0.8, 'snippet' => 'Private quote']);
        foreach ([User::factory()->create(), User::factory()->create(['is_admin' => true])] as $viewer) {
            $this->actingAs($viewer);
            foreach ([$submission->id, $submission->id + 9999] as $id) {
                $this->get(route('riwayat.show', $id))->assertNotFound()
                    ->assertDontSee('PRIVATE_OTHER_INPUT')->assertDontSee('PRIVATE_OTHER_EXPLANATION')
                    ->assertDontSee('PRIVATE_OTHER_EVIDENCE')->assertDontSee('data-progress-root', false);
            }
        }
        $this->actingAs($owner)->get(route('riwayat.show', $submission->id + 9999))->assertNotFound();
    }

    public function test_history_detail_only_uses_its_own_ordered_evidence_and_the_current_users_feedback(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $submission = Submission::factory()->for($owner)->create(['status' => 'completed']);
        $foreign = Submission::factory()->for($other)->create(['status' => 'completed']);
        DetectionResult::factory()->for($submission)->create(['explanation_status' => 'ready']);
        foreach ([[2, 'doc-b'], [1, 'doc-z'], [1, 'doc-a']] as [$rank, $id]) {
            EvidenceReference::create(['submission_id' => $submission->id, 'document_id' => $id, 'rank' => $rank, 'title' => 'Own '.$id, 'source' => 'Fixture', 'source_url' => 'https://reference.example.test/fixture', 'similarity_score' => 0.8, 'snippet' => 'Own quote']);
        }
        EvidenceReference::create(['submission_id' => $foreign->id, 'document_id' => 'foreign', 'rank' => 1, 'title' => 'PRIVATE_FOREIGN_REFERENCE', 'source' => 'Fixture', 'source_url' => 'https://reference.example.test/fixture', 'similarity_score' => 0.8, 'snippet' => 'Private quote']);
        $feedback = Feedback::factory()->create(['submission_id' => $submission->id, 'user_id' => $owner->id, 'comment' => 'My feedback']);
        Feedback::factory()->create(['submission_id' => $submission->id, 'user_id' => $other->id, 'comment' => 'PRIVATE_FOREIGN_FEEDBACK']);
        $this->actingAs($owner)->get(route('riwayat.show', $submission))->assertOk()->assertViewIs('hasil')
            ->assertViewHas('submission', fn ($item): bool => $item->evidenceReferences->pluck('document_id')->all() === ['doc-a', 'doc-z', 'doc-b'])
            ->assertViewHas('feedback', fn ($item): bool => $item->id === $feedback->id)
            ->assertSeeInOrder(['Own doc-a', 'Own doc-z', 'Own doc-b'])->assertSee('My feedback')
            ->assertDontSee('PRIVATE_FOREIGN_REFERENCE')->assertDontSee('PRIVATE_FOREIGN_FEEDBACK')
            ->assertDontSee('id="feedback-form"', false);
    }

    private function createSubmissions(User $user, int $count): void
    {
        foreach (range(1, $count) as $index) {
            Submission::create([
                'user_id' => $user->id,
                'input_type' => 'text',
                'raw_input' => "Submission riwayat nomor {$index} dengan teks yang cukup.",
                'status' => 'pending',
            ]);
        }
    }
}

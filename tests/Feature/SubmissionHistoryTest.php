<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

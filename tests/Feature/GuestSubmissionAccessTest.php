<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestSubmissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_guest_capability_allows_result_status_and_media_access(): void
    {
        Storage::fake('local');
        [$submission, $token] = $this->guestSubmission('submissions/legacy/guest.txt');
        Storage::disk('local')->put($submission->media_path, 'guest media');
        $this->createResult($submission);

        $this->withSession([SubmissionAccess::sessionKey($submission) => $token]);

        $this->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('wire:poll.2s.visible', false)
            ->assertDontSee('class="result-badge', false);
        $this->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJson([
                'is_completed' => false,
                'is_failed' => false,
                'label' => null,
                'confidence_score' => null,
            ]);
        $mediaResponse = $this->get(route('hasil.media', $submission));
        $this->assertContains($mediaResponse->getStatusCode(), [200, 302]);
    }

    public function test_guest_result_endpoints_reject_access_without_capability(): void
    {
        [$submission] = $this->guestSubmission('submissions/legacy/guest.txt');
        $this->createResult($submission);

        $this->get(route('hasil', $submission))->assertNotFound();
        $this->getJson(route('hasil.status', $submission))->assertNotFound();
        $this->get(route('hasil.pdf', $submission))->assertNotFound();
        $this->get(route('hasil.media', $submission))->assertNotFound();
    }

    public function test_wrong_guest_capability_is_rejected(): void
    {
        [$submission] = $this->guestSubmission();

        $this->withSession([
            SubmissionAccess::sessionKey($submission) => bin2hex(random_bytes(32)),
        ])->get(route('hasil', $submission))->assertNotFound();
    }

    public function test_capability_for_submission_a_cannot_open_submission_b(): void
    {
        [$submissionA, $tokenA] = $this->guestSubmission();
        [$submissionB] = $this->guestSubmission();

        $this->withSession([
            SubmissionAccess::sessionKey($submissionB) => $tokenA,
        ])->get(route('hasil', $submissionB))->assertNotFound();

        $this->withSession([
            SubmissionAccess::sessionKey($submissionA) => $tokenA,
        ])->get(route('hasil', $submissionA))->assertOk();
    }

    public function test_guest_capability_cannot_open_an_authenticated_users_submission(): void
    {
        [$guestSubmission, $guestToken] = $this->guestSubmission();
        $ownedSubmission = Submission::create([
            'user_id' => User::factory()->create()->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita milik user login. ', 3),
            'status' => 'pending',
        ]);

        $this->withSession([
            SubmissionAccess::sessionKey($guestSubmission) => $guestToken,
            SubmissionAccess::sessionKey($ownedSubmission) => $guestToken,
        ])->get(route('hasil', $ownedSubmission))->assertForbidden();
    }

    public function test_authenticated_submission_ownership_still_applies(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $owner->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita privat milik owner. ', 3),
            'status' => 'pending',
        ]);

        $this->actingAs($owner)->get(route('hasil', $submission))->assertOk();
        $this->actingAs($other)->get(route('hasil', $submission))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('hasil', $submission))->assertForbidden();
    }

    public function test_guest_only_sees_text_input_while_authenticated_user_sees_supported_inputs(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="tab-teks"', false)
            ->assertDontSee('id="tab-gambar"', false)
            ->assertDontSee('id="tab-video"', false)
            ->assertDontSee('id="tab-url"', false);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('id="tab-gambar"', false)
            ->assertSee('id="tab-video"', false)
            ->assertSee('id="tab-url"', false);
    }

    /** @return array{Submission, string} */
    private function guestSubmission(?string $mediaPath = null): array
    {
        $token = bin2hex(random_bytes(32));
        $submission = Submission::create([
            'guest_access_token_hash' => hash('sha256', $token),
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita guest yang bersifat privat. ', 3),
            'media_path' => $mediaPath,
            'status' => 'processing',
        ]);

        return [$submission, $token];
    }

    private function createResult(Submission $submission): void
    {
        $submission->detectionResult()->create([
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'test',
            'explanation' => 'Penjelasan hasil untuk pengujian akses guest.',
            'explanation_status' => 'ready',
        ]);
    }
}

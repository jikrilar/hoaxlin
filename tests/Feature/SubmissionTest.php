<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(route('home'));
    }

    public function test_guest_can_submit_text_and_receives_a_session_bound_capability(): void
    {
        Queue::fake();
        $text = str_repeat('Berita guest yang akan diperiksa. ', 3);

        $response = $this->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'text',
            'raw_input' => $text,
        ]));

        $submission = Submission::sole();
        $sessionKey = SubmissionAccess::sessionKey($submission);
        $token = session($sessionKey);

        $response->assertRedirect(route('hasil', $submission));
        $this->assertNull($submission->user_id);
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $submission->guest_access_token_hash);
        $this->assertStringNotContainsString($token, (string) $response->headers->get('Location'));
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_text_submission_is_stored_and_queued(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $text = str_repeat('Berita yang akan diperiksa. ', 3);

        $response = $this->actingAs($user)->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'text',
            'raw_input' => $text,
        ]));

        $submission = Submission::sole();
        $response->assertRedirect(route('hasil', $submission));
        $this->assertSame($user->id, $submission->user_id);
        $this->assertNull($submission->guest_access_token_hash);
        $this->assertSame('text', $submission->input_type);
        $this->assertSame(trim($text), $submission->raw_input);
        $this->assertSame('pending', $submission->status);
        $this->assertNull($submission->detectionResult);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job): bool => $job->submission->is($submission));
    }

    public function test_image_submission_is_stored_privately_and_queued(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'image',
            'media_file' => UploadedFile::fake()->image('berita.jpg'),
        ]))->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('image', $submission->input_type);
        Storage::disk('local')->assertExists($submission->media_path);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_video_file_submission_is_stored_privately_and_queued(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('berita.mp4', 1024, 'video/mp4'),
        ]))->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('video', $submission->input_type);
        Storage::disk('local')->assertExists($submission->media_path);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_article_url_submission_is_stored_and_queued(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'url',
            'source_url' => 'https://example.com/berita',
        ]))->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('url', $submission->input_type);
        $this->assertSame('https://example.com/berita', $submission->source_url);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_video_url_is_normalized_to_video_submission(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video_url',
            'source_url' => 'https://example.com/video/1.mp4',
        ]))->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('video', $submission->input_type);
        $this->assertSame('https://example.com/video/1.mp4', $submission->source_url);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_guest_cannot_submit_article_url(): void
    {
        $this->assertGuestSubmissionTypeIsForbidden('url', [
            'source_url' => 'https://example.com/berita',
        ]);
    }

    public function test_guest_cannot_submit_image(): void
    {
        Storage::fake('local');

        $this->assertGuestSubmissionTypeIsForbidden('image', [
            'media_file' => UploadedFile::fake()->image('berita.jpg'),
        ]);
    }

    public function test_guest_cannot_submit_video(): void
    {
        Storage::fake('local');

        $this->assertGuestSubmissionTypeIsForbidden('video', [
            'media_file' => UploadedFile::fake()->create('berita.mp4', 1024, 'video/mp4'),
        ]);
    }

    public function test_guest_cannot_submit_video_url(): void
    {
        $this->assertGuestSubmissionTypeIsForbidden('video_url', [
            'source_url' => 'https://example.com/video.mp4',
        ]);
    }

    public function test_submission_validation_rejects_invalid_payloads(): void
    {
        Queue::fake();

        $this->from(route('home'))->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'text',
            'raw_input' => 'Terlalu pendek',
        ]))->assertRedirect(route('home'))
            ->assertSessionHasErrors(['raw_input' => 'Teks minimal 50 karakter.'])
            ->assertSessionHasInput('raw_input', 'Terlalu pendek');

        $this->get(route('home'))->assertOk()
            ->assertSee('Teks minimal 50 karakter.')
            ->assertSee('>Terlalu pendek</textarea>', false)
            ->assertSee('id="submission-errors" role="alert"', false);

        $this->assertDatabaseEmpty('submissions');
        Queue::assertNothingPushed();
    }

    public function test_non_string_old_input_does_not_break_the_validation_page(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['text' => 'raw_input', 'url' => 'source_url'] as $inputType => $field) {
            $this->get(route('home'))->assertOk();

            $this->post(route('deteksi'), [
                'input_type' => $inputType,
                $field => ['invalid'],
                'captcha_answer' => session('submission_captcha.answer'),
            ])->assertRedirect(route('home'))->assertSessionHasErrors($field);

            $this->get(route('home'))->assertOk()
                ->assertSee('id="submission-errors" role="alert"', false)
                ->assertDontSee('validation.');
        }

        $this->assertDatabaseEmpty('submissions');
    }

    public function test_url_validation_restores_the_correct_tab_and_non_file_input(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['url' => 'https://example.com/berita', 'video_url' => 'https://example.com/video.mp4'] as $inputType => $sourceUrl) {
            $this->get(route('home'))->assertOk();
            $wrongAnswer = (int) session('submission_captcha.answer') + 1;

            $this->post(route('deteksi'), [
                'input_type' => $inputType,
                'source_url' => $sourceUrl,
                'captcha_answer' => $wrongAnswer,
            ])->assertRedirect(route('home'))
                ->assertSessionHasErrors(['captcha_answer' => 'Jawaban CAPTCHA salah.'])
                ->assertSessionHasInput('input_type', $inputType)
                ->assertSessionHasInput('source_url', $sourceUrl);

            $page = $this->get(route('home'))->assertOk()
                ->assertSee('Jawaban CAPTCHA salah.')
                ->assertSee('value="'.$sourceUrl.'"', false);

            if ($inputType === 'url') {
                $page->assertSee('id="tab-url" class="input-tab active" role="tab" aria-selected="true"', false)
                    ->assertSee('id="panel-url" class="tab-content active"', false);
            } else {
                $page->assertSee('id="tab-video" class="input-tab active" role="tab" aria-selected="true"', false)
                    ->assertSee('id="panel-video" class="tab-content active"', false)
                    ->assertSee('id="video-input-type" value="video_url"', false);
                $this->assertMatchesRegularExpression('/id="video-tab-url"[^>]*aria-pressed="true"/', $page->getContent());
            }
        }

        $this->assertDatabaseEmpty('submissions');
    }

    public function test_upload_validation_restores_tab_and_asks_for_file_again(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        foreach (['image', 'video'] as $inputType) {
            $this->get(route('home'))->assertOk();
            $file = $inputType === 'image'
                ? UploadedFile::fake()->image('berita.jpg')
                : UploadedFile::fake()->create('berita.mp4', 1024, 'video/mp4');

            $this->post(route('deteksi'), [
                'input_type' => $inputType,
                'media_file' => $file,
                'captcha_answer' => (int) session('submission_captcha.answer') + 1,
            ])->assertRedirect(route('home'))
                ->assertSessionHasErrors(['captcha_answer' => 'Jawaban CAPTCHA salah.']);

            $this->assertArrayNotHasKey('media_file', session('_old_input', []));

            $page = $this->get(route('home'))->assertOk()
                ->assertSee('File unggahan perlu dipilih ulang.')
                ->assertSee('Jawaban CAPTCHA salah.');

            if ($inputType === 'image') {
                $page->assertSee('id="tab-gambar" class="input-tab active"', false);
            } else {
                $page->assertSee('id="tab-video" class="input-tab active"', false)
                    ->assertSee('id="video-input-type" value="video"', false);
            }
        }

        $this->assertDatabaseEmpty('submissions');
    }

    /** @param array<string, mixed> $payload */
    private function assertGuestSubmissionTypeIsForbidden(string $inputType, array $payload): void
    {
        Queue::fake();

        $this->post(route('deteksi'), ['input_type' => $inputType, ...$payload])
            ->assertForbidden();

        $this->assertDatabaseEmpty('submissions');
        Queue::assertNothingPushed();
    }

    /** @param array<string, mixed> $payload */
    private function withCaptcha(array $payload): array
    {
        return [...$payload, 'captcha_answer' => (int) session('submission_captcha.answer')];
    }
}

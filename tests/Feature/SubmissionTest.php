<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_submission_is_stored_and_queued(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $text = str_repeat('Berita yang akan diperiksa. ', 3);

        $response = $this->actingAs($user)->post(route('deteksi'), [
            'input_type' => 'text',
            'raw_input' => $text,
        ]);

        $submission = Submission::sole();
        $response->assertRedirect(route('hasil', $submission));
        $this->assertSame($user->id, $submission->user_id);
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

        $this->post(route('deteksi'), [
            'input_type' => 'image',
            'media_file' => UploadedFile::fake()->image('berita.jpg'),
        ])->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('image', $submission->input_type);
        Storage::disk('local')->assertExists($submission->media_path);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_video_file_submission_is_stored_privately_and_queued(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->post(route('deteksi'), [
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('berita.mp4', 1024, 'video/mp4'),
        ])->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('video', $submission->input_type);
        Storage::disk('local')->assertExists($submission->media_path);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_article_url_submission_is_stored_and_queued(): void
    {
        Queue::fake();

        $this->post(route('deteksi'), [
            'input_type' => 'url',
            'source_url' => 'https://example.com/berita',
        ])->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('url', $submission->input_type);
        $this->assertSame('https://example.com/berita', $submission->source_url);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_video_url_is_normalized_to_video_submission(): void
    {
        Queue::fake();

        $this->post(route('deteksi'), [
            'input_type' => 'video_url',
            'source_url' => 'https://example.com/video/1',
        ])->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('video', $submission->input_type);
        $this->assertSame('https://example.com/video/1', $submission->source_url);
        Queue::assertPushed(ProcessSubmission::class);
    }

    public function test_submission_validation_rejects_invalid_payloads(): void
    {
        Queue::fake();

        $this->from(route('home'))->post(route('deteksi'), [
            'input_type' => 'text',
            'raw_input' => 'Terlalu pendek',
        ])->assertRedirect(route('home'))->assertSessionHasErrors('raw_input');

        $this->assertDatabaseEmpty('submissions');
        Queue::assertNothingPushed();
    }
}

<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.media_disk', 'local');
        config()->set('data_retention.media_hours', 24);
        $this->travelTo('2026-09-18 12:00:00');
    }

    public function test_prune_removes_old_completed_and_failed_media_but_preserves_history(): void
    {
        $completed = $this->terminalSubmission('completed', 'completed.jpg', now()->subHours(25));
        $failed = $this->terminalSubmission('failed', 'failed.mp4', now()->subHours(25));
        $result = DetectionResult::factory()->for($completed)->create();

        $this->assertSame(0, Artisan::call('media:prune'));

        Storage::disk('local')->assertMissing('submissions/completed.jpg');
        Storage::disk('local')->assertMissing('submissions/failed.mp4');
        $this->assertNull($completed->fresh()->media_path);
        $this->assertNull($failed->fresh()->media_path);
        $this->assertSame('Teks hasil ekstraksi tetap disimpan.', $completed->fresh()->extracted_text);
        $this->assertModelExists($completed);
        $this->assertModelExists($failed);
        $this->assertModelExists($result);
    }

    public function test_prune_keeps_recent_terminal_and_processing_media(): void
    {
        $recent = $this->terminalSubmission('completed', 'recent.jpg', now()->subHours(23));
        $processing = Submission::factory()->create([
            'input_type' => 'video',
            'media_path' => 'submissions/processing.mp4',
            'status' => 'processing',
            'processing_started_at' => now()->subHours(48),
            'processing_completed_at' => null,
        ]);
        Storage::disk('local')->put('submissions/processing.mp4', 'processing media');

        $this->assertSame(0, Artisan::call('media:prune'));

        Storage::disk('local')->assertExists('submissions/recent.jpg');
        Storage::disk('local')->assertExists('submissions/processing.mp4');
        $this->assertSame('submissions/recent.jpg', $recent->fresh()->media_path);
        $this->assertSame('submissions/processing.mp4', $processing->fresh()->media_path);
    }

    public function test_prune_is_idempotent_and_missing_physical_file_is_safe(): void
    {
        $submission = Submission::factory()->create([
            'input_type' => 'image',
            'media_path' => 'submissions/already-missing.jpg',
            'status' => 'failed',
            'processing_completed_at' => now()->subHours(25),
        ]);

        $this->assertSame(0, Artisan::call('media:prune'));
        $this->assertNull($submission->fresh()->media_path);
        $this->assertSame(0, Artisan::call('media:prune'));
        $this->assertModelExists($submission);
    }

    private function terminalSubmission(string $status, string $filename, \DateTimeInterface $completedAt): Submission
    {
        $path = 'submissions/'.$filename;
        Storage::disk('local')->put($path, 'original media');

        return Submission::factory()->create([
            'input_type' => str_ends_with($filename, '.mp4') ? 'video' : 'image',
            'extracted_text' => 'Teks hasil ekstraksi tetap disimpan.',
            'media_path' => $path,
            'status' => $status,
            'processing_completed_at' => $completedAt,
        ]);
    }
}

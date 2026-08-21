<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_own_guest_submission_status(): void
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk status. ', 10),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ]);

        $response = $this->getJson(route('hasil.status', $submission->id));

        $response->assertOk()
            ->assertJson([
                'id' => $submission->id,
                'status' => 'processing',
                'processing_stage' => 'classifying',
                'progress' => 60,
                'has_result' => false,
            ]);
    }

    public function test_owner_can_view_status_and_guest_cannot_view_owned(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $owner->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita milik owner. ', 10),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Extracting->value,
        ]);

        $this->actingAs($owner)
            ->getJson(route('hasil.status', $submission->id))
            ->assertOk()
            ->assertJson(['progress' => 25]);

        $this->actingAs($other)
            ->getJson(route('hasil.status', $submission->id))
            ->assertForbidden();
    }

    public function test_status_returns_100_when_completed(): void
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Selesai. ', 10),
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
        ]);

        // Create a fake result so has_result is true
        $submission->detectionResult()->create([
            'label' => 'hoax',
            'confidence_score' => 0.95,
            'model_version' => 'v1.0.0',
            'raw_scores' => ['valid' => 0.05, 'hoax' => 0.95],
            'inference_ms' => 10,
        ]);

        $this->getJson(route('hasil.status', $submission->id))
            ->assertOk()
            ->assertJson(['progress' => 100, 'is_completed' => true, 'has_result' => true]);
    }

    public function test_livewire_component_shows_progress_and_polls(): void
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Polling. ', 10),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ]);

        $component = Livewire::test(\App\Livewire\SubmissionProgress::class, ['submission' => $submission]);

        $component->assertSee('60%')
            ->assertSee('Klasifikasi BERT')
            ->assertSee('Sedang Menganalisis');

        // Simulate stage change and refresh
        $submission->update(['processing_stage' => ProcessingStage::Explaining->value]);
        $component->call('refreshProgress')
            ->assertSee('85%')
            ->assertSee('Penyusunan penjelasan');
    }

    public function test_livewire_shows_failed_state(): void
    {
        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => str_repeat('Gagal. ', 10),
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Classifying->value,
            'failure_reason' => 'Model timeout',
        ]);

        Livewire::test(\App\Livewire\SubmissionProgress::class, ['submission' => $submission])
            ->assertSee('Gagal')
            ->assertSee('Pemrosesan Gagal');
    }
}

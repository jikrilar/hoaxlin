<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Livewire\SubmissionProgress;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_own_guest_submission_status(): void
    {
        $token = bin2hex(random_bytes(32));
        $submission = Submission::create([
            'guest_access_token_hash' => hash('sha256', $token),
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk status. ', 10),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ]);

        $response = $this->withSession([
            SubmissionAccess::sessionKey($submission) => $token,
        ])->getJson(route('hasil.status', $submission->id));

        $response->assertOk()
            ->assertJson([
                'id' => $submission->id,
                'status' => 'processing',
                'processing_stage' => 'classifying',
                'progress' => 60,
                'is_completed' => false,
                'is_failed' => false,
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
        $token = bin2hex(random_bytes(32));
        $submission = Submission::create([
            'guest_access_token_hash' => hash('sha256', $token),
            'input_type' => 'text',
            'raw_input' => str_repeat('Selesai. ', 10),
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
        ]);

        // A completed submission exposes its persisted result as final.
        $submission->detectionResult()->create([
            'label' => 'hoax',
            'confidence_score' => 0.95,
            'model_version' => 'v1.0.0',
            'raw_scores' => ['valid' => 0.05, 'hoax' => 0.95],
            'inference_ms' => 10,
        ]);

        $this->withSession([
            SubmissionAccess::sessionKey($submission) => $token,
        ])->getJson(route('hasil.status', $submission->id))
            ->assertOk()
            ->assertJson(['progress' => 100, 'is_completed' => true, 'is_failed' => false])
            ->assertJsonMissingPath('has_result');
    }

    public function test_livewire_component_shows_progress_and_polls(): void
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Polling. ', 10),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ]);

        $this->actingAs($user);
        $component = Livewire::test(SubmissionProgress::class, ['submission' => $submission]);

        $component->assertSee('60%')
            ->assertSee('Klasifikasi BERT')
            ->assertSee('Sedang Menganalisis');

        $submission->update(['processing_stage' => ProcessingStage::Retrieving->value]);
        $component->call('refreshProgress')
            ->assertSee('75%')
            ->assertSee('Pencarian bukti');

        // Simulate stage change and refresh
        $submission->update(['processing_stage' => ProcessingStage::Explaining->value]);
        $component->call('refreshProgress')
            ->assertSee('85%')
            ->assertSee('Penyusunan penjelasan');
    }

    public function test_livewire_shows_failed_state(): void
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Gagal. ', 10),
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Classifying->value,
            'failure_reason' => 'Model timeout',
        ]);

        $this->actingAs($user);
        Livewire::test(SubmissionProgress::class, ['submission' => $submission])
            ->assertSee('Gagal')
            ->assertSee('Pemrosesan Gagal');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinalResultStateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('inFlightStages')]
    public function test_pending_and_processing_stages_remain_non_terminal(
        string $status,
        ProcessingStage $stage,
        int $expectedProgress,
    ): void {
        [$user, $submission] = $this->ownedSubmission($status, $stage);

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJson([
                'status' => $status,
                'processing_stage' => $stage->value,
                'progress' => $expectedProgress,
                'is_completed' => false,
                'is_failed' => false,
                'label' => null,
                'confidence_score' => null,
            ])
            ->assertJsonMissingPath('has_result');

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('wire:poll.2s.visible', false)
            ->assertDontSee('class="result-badge', false);
    }

    public function test_partial_result_while_explaining_keeps_polling_and_is_not_rendered_as_final(): void
    {
        [$user, $submission] = $this->ownedSubmission('processing', ProcessingStage::Explaining);
        $this->createResult($submission, explanationStatus: 'pending');

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJson([
                'progress' => 85,
                'is_completed' => false,
                'is_failed' => false,
                'label' => null,
                'confidence_score' => null,
            ]);

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Sedang Menganalisis')
            ->assertSee('wire:poll.2s.visible', false)
            ->assertDontSee('class="result-badge', false)
            ->assertDontSee('Berdasarkan analisis mendalam');

        $this->actingAs($user)
            ->get(route('hasil.pdf', $submission))
            ->assertNotFound();
    }

    public function test_completed_submission_with_result_is_rendered_as_final_and_stops_polling(): void
    {
        [$user, $submission] = $this->ownedSubmission('completed', ProcessingStage::Done);
        $this->createResult($submission, 'ready', 'Penjelasan final dari pipeline.');

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJson([
                'progress' => 100,
                'is_completed' => true,
                'is_failed' => false,
                'label' => 'valid',
            ]);

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Hasil Deteksi')
            ->assertSee('Penjelasan final dari pipeline.')
            ->assertDontSee('wire:poll.2s.visible', false)
            ->assertDontSee('let interval = setInterval', false);
    }

    public function test_failed_submission_with_partial_result_is_failed_not_final_and_stops_polling(): void
    {
        [$user, $submission] = $this->ownedSubmission('failed', ProcessingStage::Explaining);
        $submission->update(['failure_reason' => 'Explanation failed']);
        $this->createResult($submission, explanationStatus: 'pending');

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJson([
                'is_completed' => false,
                'is_failed' => true,
                'label' => null,
                'confidence_score' => null,
            ]);

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Pemrosesan Gagal')
            ->assertDontSee('class="result-badge', false)
            ->assertDontSee('wire:poll.2s.visible', false)
            ->assertDontSee('let interval = setInterval', false);
    }

    public function test_completed_result_with_pending_explanation_does_not_invent_a_final_narrative(): void
    {
        [$user, $submission] = $this->ownedSubmission('completed', ProcessingStage::Done);
        $this->createResult($submission, explanationStatus: 'pending');

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Hasil Deteksi')
            ->assertSee('Penjelasan Belum Tersedia')
            ->assertSee('Penjelasan AI belum tersedia untuk hasil ini.')
            ->assertDontSee('Berdasarkan analisis mendalam');
    }

    /** @return array<string, array{string, ProcessingStage, int}> */
    public static function inFlightStages(): array
    {
        return [
            'pending' => ['pending', ProcessingStage::Queued, 0],
            'extracting' => ['processing', ProcessingStage::Extracting, 25],
            'translating' => ['processing', ProcessingStage::Translating, 45],
            'classifying' => ['processing', ProcessingStage::Classifying, 60],
            'explaining' => ['processing', ProcessingStage::Explaining, 85],
        ];
    }

    /** @return array{User, Submission} */
    private function ownedSubmission(string $status, ProcessingStage $stage): array
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk pengujian final state. ', 3),
            'status' => $status,
            'processing_stage' => $stage->value,
        ]);

        return [$user, $submission];
    }

    private function createResult(
        Submission $submission,
        string $explanationStatus,
        ?string $explanation = null,
    ): void {
        $submission->detectionResult()->create([
            'label' => 'valid',
            'confidence_score' => 0.91,
            'model_version' => 'test',
            'explanation' => $explanation,
            'explanation_status' => $explanationStatus,
        ]);
    }
}

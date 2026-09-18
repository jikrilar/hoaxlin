<?php

namespace Tests\Feature;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
        ])->assertRedirect(route('verification.notice'));

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_user_can_delete_account_and_all_owned_history_without_touching_other_data(): void
    {
        Storage::fake('local');
        config()->set('filesystems.media_disk', 'local');

        $user = User::factory()->create(['password' => 'password']);
        $otherUser = User::factory()->create();
        $mediaPath = 'submissions/images/user-media.jpg';
        $otherMediaPath = 'submissions/images/other-media.jpg';
        Storage::disk('local')->put($mediaPath, 'user media');
        Storage::disk('local')->put($otherMediaPath, 'other media');

        $submission = Submission::factory()->for($user)->create([
            'input_type' => 'image',
            'media_path' => $mediaPath,
            'status' => 'completed',
            'processing_completed_at' => now(),
        ]);
        $otherSubmission = Submission::factory()->for($otherUser)->create([
            'input_type' => 'image',
            'media_path' => $otherMediaPath,
            'status' => 'completed',
            'processing_completed_at' => now(),
        ]);
        $result = DetectionResult::factory()->for($submission)->create();
        $feedback = Feedback::factory()->create([
            'submission_id' => $submission->id,
            'user_id' => $user->id,
        ]);
        $feedbackOnOtherSubmission = Feedback::factory()->create([
            'submission_id' => $otherSubmission->id,
            'user_id' => $user->id,
        ]);
        $otherFeedbackOnSubmission = Feedback::factory()->create([
            'submission_id' => $submission->id,
            'user_id' => $otherUser->id,
        ]);
        $event = SubmissionProcessingEvent::create([
            'submission_id' => $submission->id,
            'stage' => ProcessingStage::Done,
            'outcome' => EventOutcome::Succeeded,
            'attempt' => 1,
        ]);
        $otherResult = DetectionResult::factory()->for($otherSubmission)->create();
        $dataset = Dataset::factory()->create(['verified_by' => $user->id]);
        AdminLog::factory()->create([
            'admin_id' => $user->id,
            'target_table' => 'submissions',
            'target_id' => $submission->id,
        ]);
        AdminLog::factory()->create([
            'admin_id' => $otherUser->id,
            'target_table' => 'users',
            'target_id' => $user->id,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'hashed-reset-token',
            'created_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertModelMissing($submission);
        $this->assertModelMissing($result);
        $this->assertModelMissing($feedback);
        $this->assertModelMissing($feedbackOnOtherSubmission);
        $this->assertModelMissing($otherFeedbackOnSubmission);
        $this->assertModelMissing($event);
        Storage::disk('local')->assertMissing($mediaPath);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('admin_logs', ['admin_id' => $user->id]);
        $this->assertDatabaseMissing('admin_logs', [
            'target_table' => 'users',
            'target_id' => $user->id,
        ]);

        $this->assertModelExists($otherUser);
        $this->assertModelExists($otherSubmission);
        $this->assertModelExists($otherResult);
        Storage::disk('local')->assertExists($otherMediaPath);
        $this->assertModelExists($dataset);
        $this->assertNull($dataset->fresh()->verified_by);
    }
}

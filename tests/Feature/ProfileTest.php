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
        $this->get(route('verification.notice'))->assertOk()->assertSee('Tautan verifikasi baru telah dikirim.');
    }

    public function test_unchanged_verified_email_keeps_verification_and_does_not_resend(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;
        $this->actingAs($user)->from(route('profile'))->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => $user->email])
            ->assertRedirect(route('profile'))->assertSessionHas('success', 'Profil berhasil diperbarui.');
        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
        Notification::assertNothingSent();
    }

    public function test_unverified_identity_save_returns_to_verification_with_a_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => $user->email])
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_identity_errors_use_the_profile_bag_and_recover_safe_input(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('profile'))->patch(route('profile.update'), ['name' => 'Nama <script id="attack">literal</script>', 'email' => 'invalid'])
            ->assertSessionHasErrors(['email'], null, 'profile');
        $this->get(route('profile'))->assertOk()
            ->assertSee('id="profile-errors"', false)->assertSee('id="email-error"', false)
            ->assertSee('Nama &lt;script id=&quot;attack&quot;&gt;literal&lt;/script&gt;', false)
            ->assertDontSee('<script id="attack">', false)
            ->assertDontSee('id="password-errors"', false)->assertDontSee('id="delete-errors"', false);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_password_errors_stay_out_of_deletion_and_never_restore_secrets(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $this->actingAs($user)->from(route('profile'))->put(route('password.update'), [
            'current_password' => 'secret-marker', 'password' => 'secret-marker', 'password_confirmation' => 'different-marker',
        ])->assertSessionHasErrors(['current_password', 'password'], null, 'passwordUpdate')
            ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->get(route('profile'))->assertOk()->assertSee('id="password-errors"', false)
            ->assertSee('id="current_password-error"', false)->assertDontSee('id="delete-errors"', false)
            ->assertDontSee('data-dialog-auto-open', false)->assertDontSee('secret-marker');
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_wrong_delete_password_recovers_confirmation_without_deleting_or_logging_out(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $this->actingAs($user)->from(route('profile'))->delete(route('profile.destroy'), ['password' => 'secret-marker'])
            ->assertSessionHasErrors(['password'], null, 'accountDeletion')->assertSessionMissing('_old_input.password');
        $this->get(route('profile'))->assertOk()->assertSee('id="delete-errors"', false)
            ->assertSee('id="confirm-password-error"', false)->assertSee('data-dialog-auto-open', false)
            ->assertDontSee('id="password-errors"', false)->assertDontSee('secret-marker');
        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
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

        $user = User::factory()->create([
            'password' => 'password',
            'is_admin' => true,
        ]);
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

<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_prediction_as_correct_without_comment(): void
    {
        Carbon::setTestNow('2026-07-31 10:30:00');
        [$user, $submission] = $this->submissionWithResult();

        $this->actingAs($user)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '1',
        ])->assertSessionHas('success');

        $feedback = Feedback::sole();
        $this->assertTrue($feedback->is_correct);
        $this->assertNull($feedback->comment);
        $this->assertSame('2026-07-31 10:30:00', $feedback->created_at->format('Y-m-d H:i:s'));
    }

    public function test_owner_can_mark_prediction_as_incorrect_with_optional_comment(): void
    {
        [$user, $submission] = $this->submissionWithResult();

        $this->actingAs($user)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '0',
            'comment' => 'Prediksi tidak sesuai dengan sumber resmi.',
        ])->assertSessionHas('success');

        $feedback = Feedback::sole();
        $this->assertFalse($feedback->is_correct);
        $this->assertSame('Prediksi tidak sesuai dengan sumber resmi.', $feedback->comment);
    }

    public function test_user_cannot_submit_feedback_for_another_users_submission(): void
    {
        [, $submission] = $this->submissionWithResult();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '1',
        ])->assertNotFound();

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_feedback_requires_an_existing_prediction(): void
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => 'Submission belum memiliki hasil prediksi.',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '1',
        ])->assertNotFound();

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_user_cannot_submit_feedback_twice_for_same_prediction(): void
    {
        [$user, $submission] = $this->submissionWithResult();
        Feedback::create([
            'submission_id' => $submission->id,
            'user_id' => $user->id,
            'is_correct' => true,
        ]);

        $this->actingAs($user)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '0',
        ])->assertSessionHasErrors('submission_id');

        $this->assertSame(1, Feedback::count());
    }

    public function test_feedback_validates_boolean_choice_and_comment_length(): void
    {
        [$user, $submission] = $this->submissionWithResult();

        $this->actingAs($user)->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => 'invalid',
            'comment' => str_repeat('a', 1001),
        ])->assertSessionHasErrors(['is_correct', 'comment']);

        $this->assertDatabaseEmpty('feedback');
    }

    public function test_verified_owner_has_a_native_feedback_form_and_can_submit_a_thousand_character_comment(): void
    {
        [$user, $submission] = $this->submissionWithResult();
        $this->actingAs($user)->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('id="feedback-form"', false)
            ->assertSee('type="radio" name="is_correct" value="0" required', false)
            ->assertSee('maxlength="1000"', false)
            ->assertSee('Unduh seluruh riwayat CSV');

        $this->post(route('feedback'), [
            'submission_id' => $submission->id,
            'is_correct' => '0',
            'comment' => str_repeat('a', 1000),
        ])->assertSessionHas('success');

        $this->assertSame(str_repeat('a', 1000), Feedback::sole()->comment);
        $this->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Umpan balik sudah dikirim')
            ->assertSee('Penilaian Anda: Tidak sesuai.')
            ->assertDontSee('id="feedback-form"', false);
    }

    public function test_unverified_owner_gets_verification_link_without_feedback_or_verified_export_actions(): void
    {
        [$user, $submission] = $this->submissionWithResult();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Verifikasi email akun Anda')
            ->assertSee(route('verification.notice'), false)
            ->assertSee(route('hasil.pdf', $submission), false)
            ->assertDontSee('id="feedback-form"', false)
            ->assertDontSee(route('riwayat.csv'), false);

        $this->post(route('feedback'), ['submission_id' => $submission->id, 'is_correct' => '1'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseEmpty('feedback');
    }

    public function test_sent_feedback_escapes_comment_and_duplicate_error_remains_readable(): void
    {
        [$user, $submission] = $this->submissionWithResult();
        Feedback::create(['submission_id' => $submission->id, 'user_id' => $user->id, 'is_correct' => true, 'comment' => '<script id="attack">literal</script>']);

        $this->actingAs($user)->from(route('hasil', $submission))
            ->post(route('feedback'), ['submission_id' => $submission->id, 'is_correct' => '0'])
            ->assertSessionHasErrors('submission_id');

        $this->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee('Kamu sudah memberikan umpan balik untuk hasil ini.')
            ->assertSee('Umpan balik sudah dikirim')
            ->assertSee('&lt;script id=&quot;attack&quot;&gt;literal&lt;/script&gt;', false)
            ->assertDontSee('<script id="attack">', false)
            ->assertDontSee('id="feedback-form"', false);
        $this->assertSame(1, Feedback::count());
    }

    /**
     * @return array{User, Submission}
     */
    private function submissionWithResult(): array
    {
        $user = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_input' => 'Submission dengan hasil prediksi untuk umpan balik.',
            'status' => 'completed',
        ]);
        DetectionResult::create([
            'submission_id' => $submission->id,
            'label' => 'valid',
            'confidence_score' => 0.9,
            'model_version' => 'test',
        ]);

        return [$user, $submission];
    }
}

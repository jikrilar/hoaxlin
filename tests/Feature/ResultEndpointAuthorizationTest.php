<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultEndpointAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_owner_can_access_result_status_pdf_and_media(): void
    {
        Storage::fake('local');
        [$submission] = $this->registeredSubmission(media: true);
        Storage::disk('local')->put($submission->media_path, 'media');

        $this->actingAs($submission->user)->get(route('hasil', $submission))->assertOk();
        $this->actingAs($submission->user)->getJson(route('hasil.status', $submission))->assertOk();
        $this->actingAs($submission->user)->get(route('hasil.pdf', $submission))->assertOk();
        $this->actingAs($submission->user)->get(route('hasil.media', $submission))->assertRedirect();
    }

    public function test_authenticated_non_owner_and_guest_are_denied_for_registered_result_endpoints(): void
    {
        [$submission] = $this->registeredSubmission(media: true);
        $other = User::factory()->create();
        $routes = [
            fn () => route('hasil', $submission),
            fn () => route('hasil.status', $submission),
            fn () => route('hasil.pdf', $submission),
            fn () => route('hasil.media', $submission),
        ];

        foreach ($routes as $route) {
            $this->actingAs($other)->get($route())->assertForbidden();
            $this->get($route())->assertForbidden();
        }
    }

    public function test_admin_can_access_registered_submission_but_not_other_users_csv_rows(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        [$owned] = $this->registeredSubmission(media: false, user: $admin);
        [$foreign] = $this->registeredSubmission(media: true);
        Storage::disk('local')->put($foreign->media_path, 'media');

        $this->actingAs($admin)->get(route('hasil', $foreign))->assertOk();
        $this->actingAs($admin)->getJson(route('hasil.status', $foreign))->assertOk();
        $this->actingAs($admin)->get(route('hasil.pdf', $foreign))->assertOk();
        $this->actingAs($admin)->get(route('hasil.media', $foreign))->assertRedirect();

        $csv = $this->actingAs($admin)->get(route('riwayat.csv'))
            ->assertOk()
            ->streamedContent();
        $rows = collect($this->parseCsv($csv));
        $rows->shift();
        $rows = $rows->keyBy(fn (array $row): int => (int) $row[0]);

        $this->assertTrue($rows->has($owned->id));
        $this->assertFalse($rows->has($foreign->id));
    }

    public function test_verified_owner_can_export_only_own_history(): void
    {
        $owner = User::factory()->create();
        [$owned] = $this->registeredSubmission(media: false, user: $owner);
        [$foreign] = $this->registeredSubmission(media: false);

        $csv = $this->actingAs($owner)->get(route('riwayat.csv'))
            ->assertOk()
            ->streamedContent();
        $rows = collect($this->parseCsv($csv));
        $rows->shift();
        $rows = $rows->keyBy(fn (array $row): int => (int) $row[0]);

        $this->assertTrue($rows->has($owned->id));
        $this->assertFalse($rows->has($foreign->id));
    }

    public function test_guest_capability_allows_guest_result_status_pdf_and_media(): void
    {
        Storage::fake('local');
        [$submission, $token] = $this->guestSubmission(media: true);
        Storage::disk('local')->put($submission->media_path, 'media');

        $this->withSession([SubmissionAccess::sessionKey($submission) => $token]);
        $this->get(route('hasil', $submission))->assertOk();
        $this->getJson(route('hasil.status', $submission))->assertOk();
        $this->get(route('hasil.pdf', $submission))->assertOk();
        $this->get(route('hasil.media', $submission))->assertRedirect();
    }

    public function test_guest_submission_requires_matching_capability_for_every_result_endpoint(): void
    {
        [$submission, $token] = $this->guestSubmission(media: true);
        $routes = [
            fn () => route('hasil', $submission),
            fn () => route('hasil.status', $submission),
            fn () => route('hasil.pdf', $submission),
            fn () => route('hasil.media', $submission),
        ];

        foreach ($routes as $route) {
            $this->get($route())->assertNotFound();
            $this->withSession([
                SubmissionAccess::sessionKey($submission) => bin2hex(random_bytes(32)),
            ])->get($route())->assertNotFound();
        }

        $this->actingAs(User::factory()->create())
            ->get(route('hasil', $submission))
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->withSession([SubmissionAccess::sessionKey($submission) => $token])
            ->get(route('hasil', $submission))
            ->assertOk();
    }

    public function test_guest_capability_cannot_cross_submissions_and_admin_has_no_guest_bypass(): void
    {
        [$first, $token] = $this->guestSubmission();
        [$second] = $this->guestSubmission();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withSession([
            SubmissionAccess::sessionKey($second) => $token,
        ])->get(route('hasil', $second))->assertNotFound();

        $this->actingAs($admin)->get(route('hasil', $first))->assertNotFound();
    }

    public function test_status_denial_does_not_disclose_result_or_failure_fields(): void
    {
        [$submission] = $this->registeredSubmission(media: false);
        $response = $this->getJson(route('hasil.status', $submission));

        $response->assertForbidden();
        $this->assertStringNotContainsString('classifying', $response->getContent());
        $this->assertStringNotContainsString('confidence_score', $response->getContent());
        $this->assertStringNotContainsString('failure_reason', $response->getContent());
    }

    public function test_media_missing_is_checked_after_authorization(): void
    {
        [$submission] = $this->registeredSubmission(media: false);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('hasil.media', $submission))->assertForbidden();
        $this->actingAs($submission->user)->get(route('hasil.media', $submission))->assertNotFound();
    }

    public function test_pdf_requires_completed_submission_and_final_detection_result(): void
    {
        $user = User::factory()->create();
        foreach (['processing', 'failed'] as $status) {
            $submission = Submission::factory()->create([
                'user_id' => $user->id,
                'status' => $status,
                'processing_stage' => 'explaining',
            ]);
            DetectionResult::factory()->create(['submission_id' => $submission->id]);

            $this->actingAs($user)->get(route('hasil.pdf', $submission))->assertNotFound();
        }

        $withoutResult = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'processing_stage' => 'done',
        ]);

        $this->actingAs($user)->get(route('hasil.pdf', $withoutResult))->assertNotFound();
    }

    public function test_csv_requires_verified_authenticated_user_and_keeps_admin_personal_scope(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->get(route('riwayat.csv'))
            ->assertRedirect(route('verification.notice'));

        $this->app['auth']->logout();
        $this->get(route('riwayat.csv'))->assertRedirect(route('login'));

        $admin = User::factory()->create(['is_admin' => true]);
        [$owned] = $this->registeredSubmission(media: false, user: $admin);
        [$foreign] = $this->registeredSubmission(media: false);
        $csv = $this->actingAs($admin)->get(route('riwayat.csv'))->streamedContent();
        $rows = collect($this->parseCsv($csv));
        $rows->shift();
        $rows = $rows->keyBy(fn (array $row): int => (int) $row[0]);

        $this->assertTrue($rows->has($owned->id));
        $this->assertFalse($rows->has($foreign->id));
    }

    /** @return array{Submission, User} */
    private function registeredSubmission(bool $media, ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $submission = Submission::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'processing_stage' => 'done',
            'media_path' => $media ? 'submissions/test/media.bin' : null,
        ]);
        DetectionResult::factory()->create([
            'submission_id' => $submission->id,
            'explanation_status' => 'ready',
        ]);

        return [$submission->fresh(['user', 'detectionResult']), $user];
    }

    /** @return array{Submission, string} */
    private function guestSubmission(bool $media = false): array
    {
        $token = bin2hex(random_bytes(32));
        $submission = Submission::factory()->create([
            'user_id' => null,
            'guest_access_token_hash' => hash('sha256', $token),
            'status' => 'completed',
            'processing_stage' => 'done',
            'media_path' => $media ? 'submissions/guest/media.bin' : null,
        ]);
        DetectionResult::factory()->create([
            'submission_id' => $submission->id,
            'explanation_status' => 'ready',
        ]);

        return [$submission->fresh(['detectionResult']), $token];
    }

    /** @return list<list<string|null>> */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}

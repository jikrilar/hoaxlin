<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\Submission;
use App\Models\User;
use App\Observers\FilamentAuditObserver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilamentAuditObserverTest extends TestCase
{
    use RefreshDatabase;

    private const REDACTED = '[nilai disembunyikan]';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_writer_persists_only_allowlisted_user_changes_and_redacts_credentials_and_free_text(): void
    {
        $target = User::factory()->unverified()->create();
        $admin = $this->adminFor('/admin/users');
        AdminLog::query()->delete();

        $secret = 'PRIVATE_AUDIT_SECRET_'.Str::random(16);
        $token = 'PRIVATE_REMEMBER_TOKEN_'.Str::random(24);
        $target->forceFill([
            'name' => 'Nama '.$secret,
            'email' => 'audit-'.Str::lower(Str::random(8)).'@example.test',
            'password' => $secret,
            'remember_token' => $token,
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();

        $hash = $target->getRawOriginal('password');
        $log = AdminLog::forTarget('users', $target->id)->latest('id')->firstOrFail();
        $metadata = $log->metadata;
        $changes = $metadata['changes'];

        $this->assertSame(User::class, $metadata['model']);
        $this->assertTrue($changes['is_admin']);
        $this->assertArrayHasKey('email_verified_at', $changes);
        $this->assertSame(self::REDACTED, $changes['name']);
        $this->assertSame(self::REDACTED, $changes['email']);
        $this->assertSame(self::REDACTED, $changes['password']);
        $this->assertSame(self::REDACTED, $changes['remember_token']);
        $this->assertStringNotContainsString($secret, json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($token, json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($hash, json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('update', $log->action);
        $this->assertSame('users', $log->target_table);
        $this->assertSame($target->id, $log->target_id);
        $this->assertSame(1, AdminLog::count());
        $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()
            ->assertSee(self::REDACTED)->assertDontSee($secret)->assertDontSee($token)->assertDontSee($hash);
    }

    public function test_writer_ignores_unknown_and_nested_dirty_attributes(): void
    {
        $target = User::factory()->create();
        $this->adminFor('/admin/users');
        AdminLog::query()->delete();

        $secret = 'PRIVATE_NESTED_AUDIT_SECRET_'.Str::random(16);
        $target->setAttribute('profile_payload', ['api_token' => $secret]);
        $target->setAttribute('unexpected_text', $secret);
        app(FilamentAuditObserver::class)->updated($target);

        $log = AdminLog::forTarget('users', $target->id)->latest('id')->firstOrFail();
        $metadata = $log->metadata;

        $this->assertArrayNotHasKey('profile_payload', $metadata['changes'] ?? []);
        $this->assertArrayNotHasKey('unexpected_text', $metadata['changes'] ?? []);
        $this->assertStringNotContainsString($secret, json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertSame(1, AdminLog::count());
    }

    public function test_writer_keeps_safe_dataset_label_and_verifier_but_redacts_curation_text(): void
    {
        $dataset = Dataset::factory()->create(['label' => 'valid']);
        $admin = $this->adminFor('/admin/datasets');
        AdminLog::query()->delete();

        $secret = 'PRIVATE_DATASET_SECRET_'.Str::random(16);
        $dataset->forceFill([
            'text' => 'Teks kurasi '.$secret,
            'label' => 'hoax',
            'source' => 'Sumber '.$secret,
            'verified_by' => $admin->id,
        ])->save();

        $log = AdminLog::forTarget('datasets', $dataset->id)->latest('id')->firstOrFail();
        $changes = $log->metadata['changes'];

        $this->assertSame('hoax', $changes['label']);
        $this->assertSame($admin->id, $changes['verified_by']);
        $this->assertSame(self::REDACTED, $changes['text']);
        $this->assertSame(self::REDACTED, $changes['source']);
        $this->assertStringNotContainsString($secret, json_encode($log->metadata, JSON_THROW_ON_ERROR));
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('update', $log->action);
        $this->assertSame('datasets', $log->target_table);
        $this->assertSame($dataset->id, $log->target_id);
    }

    public function test_writer_redacts_submission_content_and_credentials_but_keeps_operational_state(): void
    {
        $submission = Submission::factory()->create();
        $this->adminFor('/admin/submissions');
        AdminLog::query()->delete();

        $secret = 'PRIVATE_SUBMISSION_SECRET_'.Str::random(16);
        $guestHash = hash('sha256', 'guest '.$secret);
        $contentHash = hash('sha256', 'content '.$secret);
        $submission->forceFill([
            'input_type' => 'url',
            'source_url' => 'https://example.test/'.$secret,
            'raw_input' => 'Teks masukan '.$secret,
            'extracted_text' => 'Teks ekstraksi '.$secret,
            'translated_text' => 'Teks terjemahan '.$secret,
            'guest_access_token_hash' => $guestHash,
            'content_hash' => $contentHash,
            'status' => 'processing',
            'processing_stage' => 'classifying',
            'failure_reason' => 'Kegagalan '.$secret,
            'pipeline_version' => 'indobert-hoax-v1.0.0',
        ])->save();

        $log = AdminLog::forTarget('submissions', $submission->id)->latest('id')->firstOrFail();
        $changes = $log->metadata['changes'];

        $this->assertSame('url', $changes['input_type']);
        $this->assertSame('processing', $changes['status']);
        $this->assertSame('classifying', $changes['processing_stage']);
        $this->assertSame('indobert-hoax-v1.0.0', $changes['pipeline_version']);
        foreach (['source_url', 'raw_input', 'extracted_text', 'translated_text', 'guest_access_token_hash', 'content_hash', 'failure_reason'] as $field) {
            $this->assertSame(self::REDACTED, $changes[$field], $field);
        }
        $encoded = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($secret, $encoded);
        $this->assertStringNotContainsString($guestHash, $encoded);
        $this->assertStringNotContainsString($contentHash, $encoded);
    }

    private function adminFor(string $path): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);
        app()->instance('request', Request::create($path));

        return $admin;
    }
}

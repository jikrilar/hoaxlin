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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_creation_redacts_hidden_attributes_before_persistence_and_preserves_audit_identity(): void
    {
        $admin = $this->adminFor('/admin/users');
        AdminLog::query()->delete();
        $secret = 'PRIVATE_CREATE_SECRET_'.Str::random(16);
        $target = User::factory()->create([
            'name' => $secret,
            'email' => 'private-create@example.test',
            'password' => $secret,
            'remember_token' => $secret,
            'is_admin' => false,
        ]);

        $log = AdminLog::query()->sole();
        $changes = $log->metadata['changes'];
        $this->assertSame('create', $log->action);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('users', $log->target_table);
        $this->assertSame($target->id, $log->target_id);
        $this->assertSame($target->id, $changes['id']);
        $this->assertSame($target->getRawOriginal('created_at'), $changes['created_at']);
        $this->assertFalse($changes['is_admin']);
        foreach (['name', 'email', 'password', 'remember_token'] as $field) {
            $this->assertSame(self::REDACTED, $changes[$field]);
        }
        // Inspect stored JSON, not only an accessor or the UI projection.
        $stored = (string) AdminLog::query()->toBase()->value('metadata');
        $this->assertStringNotContainsString($secret, $stored);
        $this->assertStringNotContainsString($target->getRawOriginal('password'), $stored);
        $this->assertSame($secret, $target->fresh()->name);
        $this->assertSame($secret, $target->fresh()->remember_token);
    }

    public function test_deletion_records_action_and_target_without_copying_the_record_or_previous_changes(): void
    {
        $target = User::factory()->create(['name' => 'PRIVATE_DELETE_SECRET']);
        $this->adminFor('/admin/users');
        $target->update(['name' => 'PRIVATE_OLD_UPDATE_SECRET']);
        AdminLog::query()->delete();

        $target->delete();

        $log = AdminLog::query()->sole();
        $this->assertSame('delete', $log->action);
        $this->assertSame($target->id, $log->target_id);
        $this->assertSame(['model' => User::class, 'changes' => null], $log->metadata);
    }

    public function test_update_retains_only_current_changes_and_does_not_mutate_the_target(): void
    {
        $target = User::factory()->create();
        $this->adminFor('/admin/users');
        $target->update(['email' => 'previous-private@example.test']);
        AdminLog::query()->delete();

        $target->update(['is_admin' => true]);

        $changes = AdminLog::query()->sole()->metadata['changes'];
        $this->assertTrue($changes['is_admin']);
        $this->assertArrayNotHasKey('email', $changes);
        $this->assertSame('previous-private@example.test', $target->fresh()->email);
    }

    public function test_operational_allowlist_rejects_malformed_values_and_does_not_accept_nested_payloads(): void
    {
        $target = Submission::factory()->create();
        $this->adminFor('/admin/submissions');
        AdminLog::query()->delete();
        $secret = 'PRIVATE_MALFORMED_SECRET';
        // Exercise the observer boundary without persisting invalid domain data.
        $target->setRawAttributes(array_replace($target->getAttributes(), [
            'status' => $secret, 'input_type' => [$secret], 'processing_stage' => $secret,
            'attempt_count' => $secret, 'translation_cached' => $secret,
            'processing_started_at' => $secret."\0", 'pipeline_version' => $secret,
            'translation_model' => $secret, 'media_path' => $secret,
            'translation_input_tokens' => 0, 'translation_output_tokens' => 12,
            'user_id' => null, 'translation_provider' => [$secret],
            'raw_input' => [$secret], 'unknown_payload' => [$secret],
        ]));

        app(FilamentAuditObserver::class)->updated($target);

        $changes = AdminLog::query()->sole()->metadata['changes'];
        foreach (['status', 'input_type', 'processing_stage', 'attempt_count', 'translation_cached', 'processing_started_at', 'pipeline_version', 'translation_model', 'media_path', 'translation_provider', 'raw_input'] as $field) {
            $this->assertSame(self::REDACTED, $changes[$field], $field);
        }
        $this->assertSame(0, $changes['translation_input_tokens']);
        $this->assertSame(12, $changes['translation_output_tokens']);
        $this->assertNull($changes['user_id']);
        $this->assertArrayNotHasKey('unknown_payload', $changes);
        $this->assertStringNotContainsString($secret, (string) AdminLog::query()->toBase()->value('metadata'));
    }

    public function test_submission_preserves_valid_operational_metadata_without_copying_translation_or_media_payloads(): void
    {
        $target = Submission::factory()->create();
        $this->adminFor('/admin/submissions');
        AdminLog::query()->delete();
        $target->forceFill([
            'source_language' => 'en', 'translation_cached' => false,
            'translation_input_tokens' => 17, 'translation_output_tokens' => 0,
            'attempt_count' => 3, 'processing_started_at' => '2026-11-02 10:00:00',
            'processing_completed_at' => null, 'pipeline_version' => 'indobert-hoax-v1.0.0',
            'translation_model' => 'PRIVATE_MODEL_METADATA', 'media_path' => 'private/submissions/PRIVATE_PATH',
        ])->save();

        $changes = AdminLog::query()->sole()->metadata['changes'];
        foreach ([
            'source_language' => 'en', 'translation_cached' => false,
            'translation_input_tokens' => 17, 'translation_output_tokens' => 0,
            'attempt_count' => 3, 'processing_started_at' => '2026-11-02 10:00:00',
            'pipeline_version' => 'indobert-hoax-v1.0.0',
            'translation_model' => self::REDACTED, 'media_path' => self::REDACTED,
        ] as $field => $expected) {
            $this->assertSame($expected, $changes[$field], $field);
        }
        $this->assertSame('private/submissions/PRIVATE_PATH', $target->fresh()->media_path);
    }

    public function test_historical_raw_credentials_are_not_exposed_by_admin_detail_and_are_not_rewritten(): void
    {
        $admin = $this->adminFor('/admin/admin-logs');
        $secret = 'PRIVATE_HISTORICAL_FIXTURE';
        $metadata = ['model' => User::class, 'changes' => [
            'name' => $secret, 'email' => $secret, 'password' => $secret,
            'remember_token' => $secret, 'is_admin' => true, 'unexpected' => ['token' => $secret],
        ]];
        $log = AdminLog::factory()->create([
            'admin_id' => $admin->id, 'target_table' => 'users', 'metadata' => $metadata,
        ]);

        $this->get(AdminLogResource::getUrl('view', ['record' => $log]))->assertOk()
            ->assertSee(self::REDACTED)->assertDontSee($secret)->assertDontSee('unexpected');

        $this->assertSame($metadata, $log->fresh()->metadata);
    }

    #[DataProvider('nonAdminContexts')]
    public function test_non_admin_contexts_do_not_write_audit_logs(?bool $admin, string $path): void
    {
        $target = User::factory()->create();
        if ($admin !== null) {
            $this->actingAs(User::factory()->create(['is_admin' => $admin]));
        }
        app()->instance('request', Request::create($path));
        AdminLog::query()->delete();

        $target->update(['name' => 'Not an admin panel operation']);

        $this->assertSame(0, AdminLog::count());
    }

    public static function nonAdminContexts(): array
    {
        return [
            'guest' => [null, '/admin/users'],
            'regular user' => [false, '/admin/users'],
            'admin outside panel' => [true, '/profile'],
        ];
    }

    private function adminFor(string $path): User
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);
        app()->instance('request', Request::create($path));

        return $admin;
    }
}

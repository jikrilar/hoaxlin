<?php

namespace Tests\Feature;

use App\Models\ModelVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BertCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'internal-bert-callback-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.bert.internal_token' => self::TOKEN]);
    }

    public function test_returns_503_when_internal_token_is_not_configured(): void
    {
        config(['services.bert.internal_token' => null]);

        $this->postJson(route('internal.bert.callback'), [
            'version_id' => 1,
            'status' => ModelVersion::STATUS_COMPLETED,
            'accuracy' => 0.9,
            'f1_score' => 0.89,
            'training_duration' => 12,
        ])->assertStatus(503);
    }

    public function test_requires_valid_bearer_token(): void
    {
        $version = ModelVersion::factory()->training()->create();
        $payload = [
            'version_id' => $version->id,
            'status' => ModelVersion::STATUS_COMPLETED,
            'accuracy' => 0.91,
            'f1_score' => 0.90,
            'training_duration' => 30,
        ];

        $this->postJson(route('internal.bert.callback'), $payload)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');

        $this->withToken('wrong-token')
            ->postJson(route('internal.bert.callback'), $payload)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');

        $this->assertSame(ModelVersion::STATUS_TRAINING, $version->fresh()->status);
    }

    public function test_validates_payload_and_returns_404_when_model_version_is_missing(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => 1,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 1.2,
                'f1_score' => 0.9,
                'training_duration' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accuracy']);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => 1,
                'status' => 'training',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => 999999,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 0.91,
                'f1_score' => 0.90,
                'training_duration' => 10,
            ])
            ->assertNotFound();
    }

    public function test_completed_callback_updates_metrics_without_auto_activating(): void
    {
        $activeBaseline = ModelVersion::factory()->completed()->active()->create([
            'version_name' => 'v1.0.0',
        ]);
        $version = ModelVersion::factory()->training()->create([
            'version_name' => 'v1.1.0',
            'error_message' => 'stale notice',
            'is_active' => false,
        ]);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $version->id,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 0.94123456,
                'f1_score' => 0.93123456,
                'training_duration' => 185,
                'error_message' => null,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'version_id' => $version->id,
                'model_status' => ModelVersion::STATUS_COMPLETED,
            ]);

        $version->refresh();
        $this->assertSame(ModelVersion::STATUS_COMPLETED, $version->status);
        $this->assertEqualsWithDelta(0.941235, $version->accuracy, 0.000001);
        $this->assertEqualsWithDelta(0.931235, $version->f1_score, 0.000001);
        $this->assertSame(185, $version->training_duration);
        $this->assertNull($version->error_message);
        $this->assertFalse($version->is_active);
        $this->assertTrue($activeBaseline->fresh()->is_active);
    }

    public function test_failed_callback_clears_metrics_even_when_zero_sent_and_stores_truncated_error(): void
    {
        $version = ModelVersion::factory()->training()->create([
            'accuracy' => 0.5,
            'f1_score' => 0.5,
            'is_active' => false,
        ]);

        $longError = str_repeat('E', 2500);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $version->id,
                'status' => ModelVersion::STATUS_FAILED,
                'accuracy' => 0.0,
                'f1_score' => 0.0,
                'training_duration' => 14,
                'error_message' => $longError,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'version_id' => $version->id,
                'model_status' => ModelVersion::STATUS_FAILED,
            ]);

        $version->refresh();
        $this->assertSame(ModelVersion::STATUS_FAILED, $version->status);
        $this->assertNull($version->accuracy);
        $this->assertNull($version->f1_score);
        $this->assertSame(14, $version->training_duration);
        $this->assertSame(2000, mb_strlen((string) $version->error_message));
        $this->assertFalse($version->is_active);
    }

    public function test_callback_is_idempotent_for_same_terminal_status_and_conflicts_on_different_terminal_status(): void
    {
        $completed = ModelVersion::factory()->completed()->create([
            'accuracy' => 0.95,
            'f1_score' => 0.94,
            'training_duration' => 120,
        ]);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $completed->id,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 0.75,
                'f1_score' => 0.74,
                'training_duration' => 999,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'idempotent' => true,
                'version_id' => $completed->id,
                'model_status' => ModelVersion::STATUS_COMPLETED,
            ]);

        $completed->refresh();
        $this->assertEqualsWithDelta(0.95, $completed->accuracy, 0.0001);
        $this->assertSame(120, $completed->training_duration);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $completed->id,
                'status' => ModelVersion::STATUS_FAILED,
                'error_message' => 'Late failure',
            ])
            ->assertStatus(409)
            ->assertJson([
                'status' => 'conflict',
                'model_status' => ModelVersion::STATUS_COMPLETED,
            ]);

        $this->assertSame(ModelVersion::STATUS_COMPLETED, $completed->fresh()->status);

        $failed = ModelVersion::factory()->failed()->create(['error_message' => 'Original failure']);
        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $failed->id,
                'status' => ModelVersion::STATUS_FAILED,
                'error_message' => 'Duplicate failure',
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'idempotent' => true,
            ]);

        $this->withToken(self::TOKEN)
            ->postJson(route('internal.bert.callback'), [
                'version_id' => $failed->id,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 0.9,
                'f1_score' => 0.9,
                'training_duration' => 10,
            ])
            ->assertStatus(409);
    }
}

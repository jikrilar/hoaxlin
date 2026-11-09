<?php

namespace Tests\Feature;

use App\Models\ModelVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_version_persists_expected_attributes_and_defaults(): void
    {
        $pending = ModelVersion::create([
            'version_name' => 'v1.0',
        ]);

        $this->assertSame('v1.0', $pending->version_name);
        $this->assertNull($pending->dataset_path);
        $this->assertSame(0, $pending->total_rows);
        $this->assertSame(ModelVersion::STATUS_PENDING, $pending->status);
        $this->assertNull($pending->accuracy);
        $this->assertNull($pending->f1_score);
        $this->assertNull($pending->training_duration);
        $this->assertNull($pending->error_message);
        $this->assertFalse($pending->is_active);
        $this->assertTrue($pending->isPending());

        $completed = ModelVersion::create([
            'version_name' => 'v1.1',
            'dataset_path' => 'batch-v1.1.csv',
            'total_rows' => 320,
            'status' => ModelVersion::STATUS_COMPLETED,
            'accuracy' => 0.955,
            'f1_score' => 0.951,
            'training_duration' => 240,
            'error_message' => null,
            'is_active' => true,
        ]);

        $completed->refresh();
        $this->assertSame('batch-v1.1.csv', $completed->dataset_path);
        $this->assertSame(320, $completed->total_rows);
        $this->assertTrue($completed->isCompleted());
        $this->assertEqualsWithDelta(0.955, $completed->accuracy, 0.0001);
        $this->assertEqualsWithDelta(0.951, $completed->f1_score, 0.0001);
        $this->assertSame(240, $completed->training_duration);
        $this->assertTrue($completed->is_active);
    }

    public function test_version_name_must_be_unique(): void
    {
        ModelVersion::factory()->create(['version_name' => 'v1.0']);

        $this->expectException(QueryException::class);

        ModelVersion::factory()->create(['version_name' => 'v1.0']);
    }

    public function test_activate_and_activate_version_ensure_only_one_version_is_active_at_a_time(): void
    {
        $v10 = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.0',
            'is_active' => true,
        ]);
        $v11 = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.1',
            'is_active' => false,
        ]);
        $v12 = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.2',
            'is_active' => false,
        ]);

        $this->assertSame(1, ModelVersion::active()->count());
        $this->assertTrue($v10->fresh()->is_active);
        $this->assertSame($v10->id, ModelVersion::currentActive()?->id);

        $v11->activate();

        $this->assertSame(1, ModelVersion::active()->count());
        $this->assertFalse($v10->fresh()->is_active);
        $this->assertTrue($v11->fresh()->is_active);
        $this->assertFalse($v12->fresh()->is_active);
        $this->assertSame($v11->id, ModelVersion::currentActive()?->id);

        ModelVersion::activateVersion($v12->id);

        $this->assertSame(1, ModelVersion::active()->count());
        $this->assertFalse($v10->fresh()->is_active);
        $this->assertFalse($v11->fresh()->is_active);
        $this->assertTrue($v12->fresh()->is_active);
        $this->assertSame($v12->id, ModelVersion::currentActive()?->id);
    }

    public function test_saving_a_record_as_active_deactivates_previously_active_versions_safely(): void
    {
        $v10 = ModelVersion::factory()->active()->create(['version_name' => 'v1.0']);
        $v11 = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.1',
            'is_active' => true,
        ]);

        $this->assertFalse($v10->fresh()->is_active);
        $this->assertTrue($v11->fresh()->is_active);
        $this->assertSame(1, ModelVersion::active()->count());

        // Updating an unrelated attribute on a stale in-memory instance must not steal active state.
        $v10->update(['training_duration' => 300]);
        $this->assertFalse($v10->fresh()->is_active);
        $this->assertTrue($v11->fresh()->is_active);

        $v12 = ModelVersion::factory()->completed()->create([
            'version_name' => 'v1.2',
            'is_active' => false,
        ]);

        $this->assertTrue($v11->fresh()->is_active);
        $this->assertFalse($v12->fresh()->is_active);

        // Explicitly re-activating even on a stale in-memory instance must make v1.0 the sole active version.
        $v10->update(['is_active' => true]);

        $this->assertTrue($v10->fresh()->is_active);
        $this->assertFalse($v11->fresh()->is_active);
        $this->assertFalse($v12->fresh()->is_active);
        $this->assertSame(1, ModelVersion::active()->count());
    }

    public function test_docker_compose_configures_persistent_model_and_shared_dataset_volumes_for_bert(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $matched = preg_match(
            '/^  bert:\R(?<service>.*?)(?=^  rag:\R)/ms',
            $compose,
            $matches,
        );

        $this->assertSame(1, $matched);
        $service = $matches['service'];
        $this->assertStringContainsString('ai_models:/app/models', $service);
        $this->assertStringContainsString('training_datasets:/app/datasets:ro', $service);
        $this->assertMatchesRegularExpression('/^volumes:\R(?:  .*\R)*  ai_models:/m', $compose);
        $this->assertMatchesRegularExpression('/^volumes:\R(?:  .*\R)*  training_datasets:/m', $compose);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\ModelVersion;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ModelVersionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_complete_related_sample_data_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = $this->seededCounts();

        $this->assertSame(6, $counts['users']);
        $this->assertSame(12, $counts['datasets']);
        $this->assertSame(1, $counts['model_versions']);
        $this->assertSame(10, $counts['submissions']);
        $this->assertSame(7, $counts['detection_results']);
        $this->assertSame(6, $counts['feedback']);
        $this->assertSame(4, $counts['admin_logs']);

        $baseline = ModelVersion::where('version_name', 'v1.0.0')->firstOrFail();
        $this->assertTrue($baseline->isCompleted());
        $this->assertTrue($baseline->is_active);
        $this->assertSame(ModelVersionSeeder::BASELINE_TOTAL_ROWS, $baseline->total_rows);
        $this->assertEqualsWithDelta(1.0, $baseline->accuracy, 0.0001);
        $this->assertEqualsWithDelta(1.0, $baseline->f1_score, 0.0001);

        $this->assertSame(0, Submission::whereNotNull('user_id')->whereDoesntHave('user')->count());
        $this->assertSame(0, DetectionResult::whereDoesntHave('submission')->count());
        $this->assertSame(0, Feedback::whereDoesntHave('submission')->orWhereDoesntHave('user')->count());
        $this->assertSame(0, Dataset::whereNotNull('verified_by')->whereDoesntHave('verifier')->count());
        $this->assertSame(0, Dataset::whereHas('verifier', fn ($query) => $query->where('is_admin', false))->count());
        $this->assertSame(0, AdminLog::whereDoesntHave('admin')->count());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, $this->seededCounts());
    }

    /**
     * @return array<string, int>
     */
    private function seededCounts(): array
    {
        return [
            'users' => User::count(),
            'datasets' => Dataset::count(),
            'model_versions' => ModelVersion::count(),
            'submissions' => Submission::count(),
            'detection_results' => DetectionResult::count(),
            'feedback' => Feedback::count(),
            'admin_logs' => AdminLog::count(),
        ];
    }

    public function test_model_version_seeder_does_not_steal_active_flag_when_incremental_version_is_active(): void
    {
        $incremental = ModelVersion::factory()->completed()->active()->create([
            'version_name' => 'v1.1.0',
        ]);

        $this->seed(ModelVersionSeeder::class);

        $baseline = ModelVersion::where('version_name', 'v1.0.0')->firstOrFail();
        $this->assertFalse($baseline->is_active);
        $this->assertTrue($incremental->fresh()->is_active);
    }
}

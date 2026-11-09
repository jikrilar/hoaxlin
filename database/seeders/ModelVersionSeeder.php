<?php

namespace Database\Seeders;

use App\Models\ModelVersion;
use App\Services\Bert\BertTrainingService;
use Illuminate\Database\Seeder;

class ModelVersionSeeder extends Seeder
{
    public const BASELINE_TOTAL_ROWS = 7816;

    public const BASELINE_EVALUATION_SAMPLES = 781;

    public function run(): void
    {
        $otherActiveExists = ModelVersion::query()
            ->where('version_name', '!=', BertTrainingService::BASELINE_VERSION)
            ->where('is_active', true)
            ->exists();

        ModelVersion::query()->updateOrCreate(
            ['version_name' => BertTrainingService::BASELINE_VERSION],
            [
                'dataset_path' => null,
                'total_rows' => self::BASELINE_TOTAL_ROWS,
                'status' => ModelVersion::STATUS_COMPLETED,
                'accuracy' => 1.0,
                'f1_score' => 1.0,
                'baseline_accuracy' => 1.0,
                'baseline_f1_score' => 1.0,
                'training_duration' => null,
                'error_message' => null,
                'is_active' => ! $otherActiveExists,
            ],
        );
    }
}

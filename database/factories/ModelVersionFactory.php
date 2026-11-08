<?php

namespace Database\Factories;

use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModelVersion> */
class ModelVersionFactory extends Factory
{
    protected $model = ModelVersion::class;

    public function definition(): array
    {
        return [
            'version_name' => 'v1.'.fake()->unique()->numberBetween(1, 9999),
            'dataset_path' => 'train_'.fake()->lexify('????').'.csv',
            'total_rows' => fake()->numberBetween(50, 5000),
            'status' => ModelVersion::STATUS_PENDING,
            'accuracy' => null,
            'f1_score' => null,
            'training_duration' => null,
            'error_message' => null,
            'is_active' => false,
        ];
    }

    public function training(): static
    {
        return $this->state(fn (): array => [
            'status' => ModelVersion::STATUS_TRAINING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ModelVersion::STATUS_COMPLETED,
            'accuracy' => 0.9425,
            'f1_score' => 0.9410,
            'training_duration' => 185,
        ]);
    }

    public function failed(string $message = 'Training gagal dijalankan.'): static
    {
        return $this->state(fn (): array => [
            'status' => ModelVersion::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    public function active(): static
    {
        return $this->completed()->state(fn (): array => [
            'is_active' => true,
        ]);
    }
}

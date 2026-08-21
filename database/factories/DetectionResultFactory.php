<?php

namespace Database\Factories;

use App\Models\DetectionResult;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DetectionResult> */
class DetectionResultFactory extends Factory
{
    protected $model = DetectionResult::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'label' => fake()->randomElement(['valid', 'hoax', 'meragukan']),
            'confidence_score' => fake()->randomFloat(4, 0.55, 0.99),
            'model_version' => fake()->randomElement(['indobert-v1.0.0', 'indobert-v1.1.0']),
            'explanation' => fake('id_ID')->paragraph(),
        ];
    }
}

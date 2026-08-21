<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dataset> */
class DatasetFactory extends Factory
{
    protected $model = Dataset::class;

    public function definition(): array
    {
        return [
            'text' => fake('id_ID')->paragraphs(3, true),
            'label' => fake()->randomElement(['valid', 'hoax', 'meragukan']),
            'source' => fake('id_ID')->company(),
            'verified_by' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminLog> */
class AdminLogFactory extends Factory
{
    protected $model = AdminLog::class;

    public function definition(): array
    {
        return [
            'admin_id' => User::factory(),
            'action' => fake()->randomElement(['login', 'dataset_updated', 'user_updated', 'system_configuration_updated']),
            'target_table' => fake()->randomElement(['users', 'datasets', 'submissions', 'system']),
            'target_id' => fake()->optional()->numberBetween(1, 100),
            'metadata' => ['source' => 'seeder'],
        ];
    }
}

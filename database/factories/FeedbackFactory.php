<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feedback> */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'user_id' => User::factory(),
            'is_correct' => fake()->boolean(70),
            'comment' => fake()->optional(0.7)->sentence(),
            // New feedback starts unreviewed; tests opt in with ->reviewed().
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    public function reviewed(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer?->getKey() ?? User::factory()->state(['is_admin' => true]),
        ]);
    }
}

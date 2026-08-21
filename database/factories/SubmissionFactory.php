<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Submission> */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'input_type' => 'text',
            'raw_input' => fake('id_ID')->paragraphs(3, true),
            'extracted_text' => null,
            'media_path' => null,
            'source_url' => null,
            'status' => 'pending',
            'failure_reason' => null,
        ];
    }
}

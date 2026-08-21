<?php

namespace App\Actions\Submissions;

use App\Enums\InputType;
use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateSubmission
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated, ?Authenticatable $user, ?UploadedFile $media): Submission
    {
        $mediaPath = null;

        try {
            if ($media !== null) {
                $directory = $validated['input_type'] === 'image'
                    ? 'submissions/images'
                    : 'submissions/videos';
                $mediaPath = $media->store($directory);
            }

            return DB::transaction(function () use ($validated, $user, $mediaPath): Submission {
                $inputType = InputType::fromRequest($validated['input_type'])->value;

                $submission = Submission::create([
                    'user_id' => $user?->getAuthIdentifier(),
                    'input_type' => $inputType,
                    'raw_input' => Arr::get($validated, 'raw_input'),
                    'media_path' => $mediaPath,
                    'source_url' => Arr::get($validated, 'source_url'),
                    'status' => 'pending',
                ]);

                ProcessSubmission::dispatch($submission)->afterCommit();

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($mediaPath !== null) {
                Storage::delete($mediaPath);
            }

            throw $exception;
        }
    }
}

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
                // C12: malware scan before storing
                $scanner = app(\App\Services\Media\MalwareScanner::class);
                if (! $scanner->isClean($media)) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator: validator([], []),
                        response: response()->json(['message' => 'File terdeteksi mengandung konten mencurigakan.'], 422)
                    );
                }

                $directory = $validated['input_type'] === 'image'
                    ? 'submissions/images'
                    : 'submissions/videos';
                $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
                // D5: use dedicated submissions disk (local or S3) for multi-instance
                $mediaPath = $media->store($directory, $disk);
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
                $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
                Storage::disk($disk)->delete($mediaPath);
            }

            throw $exception;
        }
    }
}

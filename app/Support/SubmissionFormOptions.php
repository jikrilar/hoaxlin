<?php

namespace App\Support;

use App\Http\Requests\StoreSubmissionRequest;
use App\Services\Media\TranscriptionMediaContract;
use LogicException;
use Symfony\Component\Mime\MimeTypes;

/** Presentation data derived from the existing request/service, without queries. */
final class SubmissionFormOptions
{
    public static function from(TranscriptionMediaContract $media): array
    {
        $imageRules = StoreSubmissionRequest::create('/', 'POST', ['input_type' => 'image'])->rules()['media_file'];
        $urlRules = StoreSubmissionRequest::create('/', 'POST', ['input_type' => 'url'])->rules()['source_url'];
        $videoUrlRules = StoreSubmissionRequest::create('/', 'POST', ['input_type' => 'video_url'])->rules()['source_url'];
        $extensions = explode(',', self::argument($imageRules, 'mimes'));
        parse_str(str_replace(',', '&', self::argument($imageRules, 'dimensions')), $dimensions);
        $imageBytes = (int) self::argument($imageRules, 'max') * 1024;

        return [
            'image' => [
                'accept' => array_map(fn ($extension) => '.'.$extension, $extensions),
                'extensions' => $extensions,
                'mimeTypes' => array_values(array_unique(array_merge(...array_map(fn ($extension) => MimeTypes::getDefault()->getMimeTypes($extension), $extensions)))),
                'maxBytes' => $imageBytes,
                'maxSizeLabel' => number_format($imageBytes / 1048576, 0).' MiB',
                'maxWidth' => (int) $dimensions['max_width'],
                'maxHeight' => (int) $dimensions['max_height'],
            ],
            'video' => [
                'accept' => [...array_map(fn ($extension) => '.'.$extension, $media->uploadExtensions()), ...$media->uploadMimeTypes()],
                'extensions' => $media->uploadExtensions(),
                'uploadTypes' => $media->uploadTypes(),
                'mimeTypes' => $media->uploadMimeTypes(),
                'maxBytes' => $media->maxBytes(),
                'maxSizeLabel' => $media->maxSizeLabel(),
                'maxDurationSeconds' => $media->maxDurationSeconds(),
            ],
            'url' => ['maxLength' => (int) self::argument($urlRules, 'max')],
            'videoUrl' => [
                'maxLength' => (int) self::argument($videoUrlRules, 'max'),
                'extensions' => array_keys($media->remoteTypes()),
                'blockedHosts' => array_values((array) config('media.transcription.blocked_platform_hosts', [])),
            ],
        ];
    }

    private static function argument(array $rules, string $name): string
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, $name.':')) {
                return substr($rule, strlen($name) + 1);
            }
        }

        throw new LogicException('Submission presentation requires the '.$name.' rule.');
    }
}

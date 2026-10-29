<?php

namespace App\Services\Pipeline;

use App\Models\Submission;

/** Text-free fingerprints of exact stage inputs, not normalized cache keys. */
final class InputTrace
{
    public static function text(
        ?string $text,
        string $transformVersion,
        ?string $language = null,
        ?string $parent = null,
        ?bool $truncated = null,
    ): array {
        return [
            'schema_version' => 'input-trace-v1',
            'present' => $text !== null,
            'sha256' => $text === null ? null : hash('sha256', $text),
            'characters' => $text === null ? null : mb_strlen($text),
            'bytes' => $text === null ? null : strlen($text),
            'tokens' => null,
            'token_count_status' => 'tokenizer_unavailable_in_laravel',
            'language' => $language,
            'language_basis' => $language === null ? 'unknown' : 'pipeline_metadata_not_redetected',
            'transform_version' => $transformVersion,
            'parent_sha256' => $parent === null ? null : hash('sha256', $parent),
            'changed_from_parent' => $parent === null || $text === null ? null : $parent !== $text,
            'truncated' => $truncated,
            'service_truncated' => null,
            'service_truncation_status' => 'not_observable_at_this_boundary',
        ];
    }

    public static function analysis(Submission $submission): array
    {
        $translated = filled($submission->translated_text);

        return self::text(
            $submission->analysis_text,
            $translated ? 'analysis-text:translated-first-v1' : 'analysis-text:extracted-fallback-v1',
            $translated ? 'id' : $submission->source_language,
            $translated ? $submission->translated_text : $submission->extracted_text,
            false,
        );
    }
}

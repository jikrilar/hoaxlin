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
        ?int $tokens = null,
        ?bool $serviceTruncated = null,
    ): array {
        return [
            'schema_version' => 'input-trace-v1',
            'present' => $text !== null,
            'sha256' => $text === null ? null : hash('sha256', $text),
            'characters' => $text === null ? null : mb_strlen($text),
            'bytes' => $text === null ? null : strlen($text),
            'tokens' => $tokens,
            'token_count_status' => $tokens !== null ? 'reported_by_bert_service' : 'tokenizer_unavailable_in_laravel',
            'language' => $language,
            'language_basis' => $language === null ? 'unknown' : 'pipeline_metadata_not_redetected',
            'transform_version' => $transformVersion,
            'parent_sha256' => $parent === null ? null : hash('sha256', $parent),
            'changed_from_parent' => $parent === null || $text === null ? null : $parent !== $text,
            'truncated' => $truncated,
            'service_truncated' => $serviceTruncated,
            'service_truncation_status' => $serviceTruncated !== null ? 'reported_by_bert_service' : 'not_observable_at_this_boundary',
        ];
    }

    public static function analysis(
        Submission $submission,
        ?int $tokens = null,
        ?bool $serviceTruncated = null,
        ?string $classifierInput = null,
    ): array {
        $translated = filled($submission->translated_text);
        $text = $classifierInput ?? $submission->analysis_text;
        $parent = $translated ? $submission->translated_text : $submission->extracted_text;
        $isTruncated = $classifierInput !== null && $parent !== null && mb_strlen($parent) > mb_strlen($classifierInput);

        return self::text(
            $text,
            $classifierInput !== null && $isTruncated
                ? 'classifier-input:trim-head-'.(int) config('services.bert.max_text_length', 20000).'-v1'
                : ($translated ? 'analysis-text:translated-first-v1' : 'analysis-text:extracted-fallback-v1'),
            $translated ? 'id' : $submission->source_language,
            $parent,
            $isTruncated,
            tokens: $tokens,
            serviceTruncated: $serviceTruncated,
        );
    }
}

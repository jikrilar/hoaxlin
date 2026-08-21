<?php

namespace App\Support;

/**
 * Canonicalizes text before hashing so cosmetic differences in forwarded
 * messages (spacing, casing, invisible characters) still hit the same cache
 * entry and produce the same content hash.
 */
final class TextNormalizer
{
    private const ZERO_WIDTH_PATTERN = '/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u';

    public static function normalize(string $text): string
    {
        $normalized = preg_replace(self::ZERO_WIDTH_PATTERN, '', $text) ?? $text;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower(trim($normalized));
    }

    public static function hash(string $text): string
    {
        return hash('sha256', self::normalize($text));
    }
}

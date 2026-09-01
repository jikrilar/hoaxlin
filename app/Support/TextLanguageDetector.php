<?php

namespace App\Support;

/**
 * Lightweight routing detector. It only decides whether OpenAI translation is
 * needed; it is not used as an analytical signal by the hoax classifier.
 */
class TextLanguageDetector
{
    /** @var list<string> */
    private const ENGLISH_MARKERS = [
        'the', 'and', 'of', 'to', 'in', 'is', 'are', 'was', 'were', 'for',
        'on', 'that', 'with', 'as', 'by', 'from', 'at', 'this', 'these',
        'has', 'have', 'had', 'will', 'would', 'after', 'before', 'about',
        'according', 'said', 'says', 'government', 'official', 'officials',
        'people', 'news', 'new', 'all', 'not', 'their', 'its', 'into',
        'president', 'minister', 'police', 'court', 'election', 'report',
        'reports', 'announced', 'announces', 'claim', 'claims', 'country',
        'state', 'city', 'citizens', 'authorities', 'national', 'public',
        'health', 'could', 'should', 'may',
    ];

    /** @var list<string> */
    private const INDONESIAN_MARKERS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'ini', 'itu', 'untuk', 'pada',
        'dengan', 'adalah', 'akan', 'telah', 'tidak', 'dalam', 'sebagai',
        'oleh', 'karena', 'menurut', 'masyarakat', 'pemerintah', 'berita',
        'setelah', 'sebelum', 'bahwa', 'mereka', 'tersebut', 'juga', 'atau',
    ];

    public function detect(string $text): string
    {
        $tokens = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $english = $this->score($tokens, self::ENGLISH_MARKERS);
        $indonesian = $this->score($tokens, self::INDONESIAN_MARKERS);

        if ($english >= 2 && $english > ($indonesian * 1.35)) {
            return 'en';
        }

        if ($indonesian >= 2 && $indonesian >= $english) {
            return 'id';
        }

        return 'unknown';
    }

    /** @param list<string> $tokens @param list<string> $markers */
    private function score(array $tokens, array $markers): int
    {
        $lookup = array_fill_keys($markers, true);

        return count(array_filter($tokens, fn (string $token): bool => isset($lookup[$token])));
    }
}

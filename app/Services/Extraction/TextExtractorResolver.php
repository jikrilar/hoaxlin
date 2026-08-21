<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\Models\Submission;
use RuntimeException;

class TextExtractorResolver
{
    /** @param iterable<TextExtractor> $extractors */
    public function __construct(private readonly iterable $extractors) {}

    public function resolve(Submission $submission): TextExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($submission)) {
                return $extractor;
            }
        }

        throw new RuntimeException('Tidak ada extractor yang mendukung submission ini.');
    }
}

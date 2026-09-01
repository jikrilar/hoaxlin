<?php

namespace App\DataObjects;

use InvalidArgumentException;

final readonly class Translation
{
    public function __construct(
        public string $text,
        public string $sourceLanguage,
        public bool $translated,
        public string $provider,
        public ?string $model = null,
        public bool $cached = false,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $estimatedCostUsd = 0.0,
    ) {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Translation text must not be empty.');
        }

        if ($inputTokens < 0 || $outputTokens < 0 || $estimatedCostUsd < 0) {
            throw new InvalidArgumentException('Translation usage values must not be negative.');
        }
    }

    public static function notRequired(string $text, string $sourceLanguage): self
    {
        return new self(
            text: $text,
            sourceLanguage: $sourceLanguage,
            translated: false,
            provider: 'local',
        );
    }
}

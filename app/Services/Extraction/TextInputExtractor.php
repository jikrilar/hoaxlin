<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\DataObjects\ExtractedText;
use App\Enums\InputType;
use App\Models\Submission;
use App\Support\TextNormalizer;

class TextInputExtractor implements TextExtractor
{
    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Text->value;
    }

    public function extract(Submission $submission): ExtractedText
    {
        $text = TextNormalizer::normalize((string) $submission->raw_input);

        return new ExtractedText($text, InputType::Text, 'laravel', 1.0);
    }
}

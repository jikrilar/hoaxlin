<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\DataObjects\ExtractedText;
use App\Enums\InputType;
use App\Models\Submission;
use App\Support\TextNormalizer;

class StoredTextExtractor implements TextExtractor
{
    public function supports(Submission $submission): bool
    {
        return filled($submission->extracted_text)
            && in_array($submission->input_type, [InputType::Image->value, InputType::Video->value, InputType::Url->value], true);
    }

    public function extract(Submission $submission): ExtractedText
    {
        return new ExtractedText(
            TextNormalizer::normalize((string) $submission->extracted_text),
            InputType::from($submission->input_type),
            'stored',
        );
    }
}

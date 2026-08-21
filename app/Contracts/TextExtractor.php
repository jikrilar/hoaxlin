<?php

namespace App\Contracts;

use App\DataObjects\ExtractedText;
use App\Exceptions\AiServiceException;
use App\Models\Submission;

/**
 * Converts a submission of any input type into analysable plain text.
 *
 * One implementation exists per input type (plain text, OCR, transcription,
 * article scraping); the resolver selects the right one at runtime.
 */
interface TextExtractor
{
    public function supports(Submission $submission): bool;

    /**
     * @throws AiServiceException
     */
    public function extract(Submission $submission): ExtractedText;
}

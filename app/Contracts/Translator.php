<?php

namespace App\Contracts;

use App\DataObjects\Translation;
use App\Exceptions\AiServiceException;

/**
 * Prepares extracted text for the Indonesian classifier.
 *
 * Indonesian text is returned unchanged. Implementations may translate other
 * supported languages, but must never classify or alter the factual claims.
 */
interface Translator
{
    /**
     * @throws AiServiceException
     */
    public function translate(string $text): Translation;
}

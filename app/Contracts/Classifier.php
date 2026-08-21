<?php

namespace App\Contracts;

use App\DataObjects\Classification;
use App\Exceptions\AiServiceException;

/**
 * Classifies extracted text into valid, hoax, or meragukan using the
 * fine-tuned IndoBERT model.
 *
 * Implementations must throw AiServiceException so the orchestrator can decide
 * between retrying and failing without inspecting HTTP details.
 */
interface Classifier
{
    /**
     * @throws AiServiceException
     */
    public function classify(string $text): Classification;
}

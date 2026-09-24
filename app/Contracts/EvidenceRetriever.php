<?php

namespace App\Contracts;

use App\DataObjects\RetrievedEvidence;
use App\Exceptions\AiServiceException;

/**
 * Retrieves references related to supplied text.
 *
 * The contract describes evidence only. Implementations must report temporary
 * and permanent provider failures through AiServiceException so callers do
 * not need to know about HTTP or the retrieval service framework.
 */
interface EvidenceRetriever
{
    /**
     * @return list<RetrievedEvidence>
     *
     * @throws AiServiceException
     */
    public function retrieve(string $text, ?int $topK = null, ?float $minScore = null): array;
}

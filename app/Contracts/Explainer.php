<?php

namespace App\Contracts;

use App\DataObjects\Classification;
use App\DataObjects\Explanation;

/**
 * Turns a BERT verdict into a lay-reader narrative.
 *
 * The classification is fixed input: an explainer phrases the verdict and must
 * never re-classify it. Implementations must not throw for service failures;
 * they return an unavailable Explanation so a degraded narrative cannot fail
 * an otherwise successful submission.
 */
interface Explainer
{
    /**
     * @param list<array{
     *     document_id: string,
     *     title: string,
     *     source: string,
     *     source_url: string,
     *     published_at: string|null,
     *     snippet: string,
     *     similarity_score: float,
     *     rank: int,
     *     knowledge_base_version: string|null
     * }> $evidence Persisted retrieval evidence; similarity_score is relevance only.
     */
    public function explain(Classification $classification, string $excerpt, array $evidence): Explanation;
}

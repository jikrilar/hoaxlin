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
    public function explain(Classification $classification, string $excerpt): Explanation;
}

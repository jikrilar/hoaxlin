<?php

namespace App\Services\Rag;

use App\DataObjects\RetrievedEvidence;
use App\Models\EvidenceReference;
use App\Models\Submission;
use InvalidArgumentException;

/** Persists retrieval output without coupling it to the submission pipeline. */
class EvidenceReferencePersister
{
    /**
     * Insert or update evidence references for a submission in one upsert.
     *
     * @param  list<RetrievedEvidence>  $evidence
     */
    public function persist(Submission $submission, array $evidence, ?string $knowledgeBaseVersion = null): void
    {
        if (! $submission->exists || $submission->getKey() === null) {
            throw new InvalidArgumentException('Evidence can only be persisted for a saved submission.');
        }

        if ($evidence === []) {
            return;
        }

        $rows = [];
        $documentIds = [];
        foreach ($evidence as $item) {
            if (! $item instanceof RetrievedEvidence) {
                throw new InvalidArgumentException('Evidence input must contain RetrievedEvidence values.');
            }
            if (isset($documentIds[$item->documentId])) {
                throw new InvalidArgumentException('Evidence input contains duplicate document IDs.');
            }

            $documentIds[$item->documentId] = true;
            $rows[] = EvidenceReference::makeFromRetrievedEvidence($submission, $item, $knowledgeBaseVersion)->getAttributes();
        }

        EvidenceReference::query()->upsert(
            $rows,
            ['submission_id', 'document_id'],
            [
                'title',
                'source',
                'source_url',
                'published_at',
                'similarity_score',
                'rank',
                'snippet',
                'knowledge_base_version',
            ],
        );
    }
}

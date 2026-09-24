<?php

namespace App\Models;

use App\DataObjects\RetrievedEvidence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceReference extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'submission_id',
        'document_id',
        'title',
        'source',
        'source_url',
        'published_at',
        'similarity_score',
        'rank',
        'snippet',
        'knowledge_base_version',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date:Y-m-d',
            'similarity_score' => 'decimal:8',
            'rank' => 'integer',
        ];
    }

    public static function makeFromRetrievedEvidence(
        Submission $submission,
        RetrievedEvidence $evidence,
        ?string $knowledgeBaseVersion = null,
    ): self {
        return new self([
            'submission_id' => $submission->getKey(),
            'document_id' => $evidence->documentId,
            'title' => $evidence->title,
            'source' => $evidence->source,
            'source_url' => $evidence->sourceUrl,
            'published_at' => $evidence->publishedAt,
            'similarity_score' => $evidence->score,
            'rank' => $evidence->rank,
            'snippet' => $evidence->snippet,
            'knowledge_base_version' => $knowledgeBaseVersion,
        ]);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

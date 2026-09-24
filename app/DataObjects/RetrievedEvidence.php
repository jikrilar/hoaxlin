<?php

namespace App\DataObjects;

use InvalidArgumentException;

/**
 * One source-backed retrieval result. Score is semantic cosine similarity,
 * not classifier confidence or a measure of whether a claim is true.
 */
final readonly class RetrievedEvidence
{
    public function __construct(
        public string $documentId,
        public string $title,
        public string $source,
        public string $sourceUrl,
        public ?string $publishedAt,
        public string $snippet,
        public float $score,
        public int $rank,
    ) {
        foreach ([
            'document_id' => $documentId,
            'title' => $title,
            'source' => $source,
            'source_url' => $sourceUrl,
            'snippet' => $snippet,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Evidence field [{$field}] must not be empty.");
            }
        }

        self::validateSourceUrl($sourceUrl);

        if ($publishedAt !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $publishedAt)) {
                throw new InvalidArgumentException('Evidence field [published_at] must be YYYY-MM-DD or null.');
            }

            [$year, $month, $day] = array_map('intval', explode('-', $publishedAt));
            if (! checkdate($month, $day, $year)) {
                throw new InvalidArgumentException('Evidence field [published_at] is not a valid date.');
            }
        }

        if (! is_finite($score) || $score < -1.0 || $score > 1.0) {
            throw new InvalidArgumentException('Evidence field [score] must be finite and between -1 and 1.');
        }

        if ($rank < 1) {
            throw new InvalidArgumentException('Evidence field [rank] must be a positive integer.');
        }
    }

    /**
     * Build a DTO from one R3 response result and reject unknown fields.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $fields = [
            'document_id', 'title', 'source', 'source_url', 'published_at',
            'snippet', 'score', 'rank',
        ];
        $actualFields = array_keys($payload);
        sort($actualFields);
        $expectedFields = $fields;
        sort($expectedFields);

        if ($actualFields !== $expectedFields) {
            throw new InvalidArgumentException('Evidence result fields do not match the R3 response contract.');
        }

        foreach (['document_id', 'title', 'source', 'source_url', 'snippet'] as $field) {
            if (! is_string($payload[$field])) {
                throw new InvalidArgumentException("Evidence field [{$field}] must be a string.");
            }
        }

        if ($payload['published_at'] !== null && ! is_string($payload['published_at'])) {
            throw new InvalidArgumentException('Evidence field [published_at] must be a date string or null.');
        }

        if ((! is_int($payload['score']) && ! is_float($payload['score'])) || is_bool($payload['score'])) {
            throw new InvalidArgumentException('Evidence field [score] must be a JSON number.');
        }

        if (! is_int($payload['rank'])) {
            throw new InvalidArgumentException('Evidence field [rank] must be a JSON integer.');
        }

        return new self(
            documentId: $payload['document_id'],
            title: $payload['title'],
            source: $payload['source'],
            sourceUrl: $payload['source_url'],
            publishedAt: $payload['published_at'],
            snippet: $payload['snippet'],
            score: (float) $payload['score'],
            rank: $payload['rank'],
        );
    }

    /**
     * @return array<string, string|float|int|null>
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'title' => $this->title,
            'source' => $this->source,
            'source_url' => $this->sourceUrl,
            'published_at' => $this->publishedAt,
            'snippet' => $this->snippet,
            'score' => $this->score,
            'rank' => $this->rank,
        ];
    }

    private static function validateSourceUrl(string $url): void
    {
        if (preg_match('/\s/u', $url)) {
            throw new InvalidArgumentException('Evidence field [source_url] is not a valid HTTP/HTTPS URL.');
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null)
            || $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new InvalidArgumentException('Evidence field [source_url] is not a valid HTTP/HTTPS URL.');
        }
    }
}

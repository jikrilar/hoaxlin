<?php

namespace App\Services\Rag;

use App\Contracts\EvidenceRetriever;
use App\DataObjects\RetrievedEvidence;
use App\Exceptions\AiServiceException;
use App\Support\RetryAfter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/** HTTP adapter for the local R3 evidence retrieval service. */
class RagRetriever implements EvidenceRetriever
{
    private const SERVICE = 'rag';

    private const MAX_QUERY_LENGTH = 4000;

    private const MAX_TOP_K = 10;

    /**
     * @return list<RetrievedEvidence>
     */
    public function retrieve(string $text, ?int $topK = null, ?float $minScore = null): array
    {
        /** @var array<string, mixed> $config */
        $config = config('services.rag', []);
        $query = trim($text);
        $topK ??= (int) ($config['top_k'] ?? 3);
        $minScore ??= $this->configuredMinScore($config['min_score'] ?? null);

        $this->validateRequest($query, $topK, $minScore);
        $baseUrl = $config['url'] ?? null;
        if (! is_string($baseUrl) || ! filter_var($baseUrl, FILTER_VALIDATE_URL)
            || ! in_array(strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Konfigurasi URL layanan RAG tidak valid.',
            );
        }

        $payload = ['text' => $query, 'top_k' => $topK];
        if ($minScore !== null) {
            $payload['min_score'] = $minScore;
        }

        try {
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->connectTimeout(max(1, (int) ($config['connect_timeout'] ?? 3)))
                ->timeout(max(1, (int) ($config['timeout'] ?? 15)))
                ->withHeaders(array_filter([
                    'X-Request-Id' => request()?->header('X-Request-Id'),
                ]))
                ->when(filled($config['internal_token'] ?? null), fn ($request) => $request->withToken($config['internal_token']))
                ->acceptJson()
                ->post('/retrieve', $payload);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan retrieval RAG tidak dapat dihubungi.',
                previous: $exception,
            );
        }

        $this->guardAgainstFailure($response);

        try {
            return $this->mapResponse($response->json(), $topK);
        } catch (InvalidArgumentException $exception) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Respons layanan RAG tidak sesuai kontrak: '.$exception->getMessage(),
                statusCode: $response->status(),
                previous: $exception,
            );
        }
    }

    private function configuredMinScore(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Konfigurasi min_score layanan RAG tidak valid.',
            );
        }

        return (float) $value;
    }

    private function validateRequest(string $text, int $topK, ?float $minScore): void
    {
        if ($text === '' || mb_strlen($text) > self::MAX_QUERY_LENGTH) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Teks pencarian RAG harus berisi 1 sampai 4000 karakter.',
            );
        }

        if ($topK < 1 || $topK > self::MAX_TOP_K) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'top_k harus berupa bilangan bulat antara 1 dan 10.',
            );
        }

        if ($minScore !== null && (! is_finite($minScore) || $minScore < -1.0 || $minScore > 1.0)) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'min_score harus bernilai antara -1 dan 1.',
            );
        }
    }

    private function guardAgainstFailure(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $previous = $response->toException();

        throw match (true) {
            $status === 429 => AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan RAG sedang membatasi permintaan.',
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            $status === 503 => AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan RAG belum siap menerima permintaan.',
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            $response->serverError() => AiServiceException::transient(
                service: self::SERVICE,
                message: "Layanan RAG mengembalikan galat server ({$status}).",
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            default => AiServiceException::permanent(
                service: self::SERVICE,
                message: "Permintaan ke layanan RAG ditolak ({$status}).",
                statusCode: $status,
                previous: $previous,
            ),
        };
    }

    /**
     * @return list<RetrievedEvidence>
     */
    private function mapResponse(mixed $payload, int $topK): array
    {
        if (! is_array($payload) || count($payload) !== 1 || ! array_key_exists('results', $payload)
            || ! is_array($payload['results']) || ! array_is_list($payload['results'])) {
            throw new InvalidArgumentException('Response harus berisi daftar results.');
        }

        if (count($payload['results']) > $topK) {
            throw new InvalidArgumentException('Jumlah results melebihi top_k yang diminta.');
        }

        $results = [];
        $seenIds = [];
        foreach ($payload['results'] as $index => $result) {
            if (! is_array($result)) {
                throw new InvalidArgumentException('Setiap result harus berupa object.');
            }

            $evidence = RetrievedEvidence::fromResponse($result);
            if ($evidence->rank !== $index + 1) {
                throw new InvalidArgumentException('Rank results harus berurutan mulai dari 1.');
            }
            if (isset($seenIds[$evidence->documentId])) {
                throw new InvalidArgumentException('Response tidak boleh memuat document_id duplikat.');
            }

            $seenIds[$evidence->documentId] = true;
            $results[] = $evidence;
        }

        return $results;
    }
}

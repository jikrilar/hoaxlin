<?php

namespace App\Services\Bert;

use App\Contracts\Classifier;
use App\DataObjects\Classification;
use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * HTTP adapter for the FastAPI inference service.
 *
 * Deliberately thin: retries, caching and circuit breaking are added by
 * decorators in phase 4 so each concern stays independently testable.
 */
class BertClassifier implements Classifier
{
    private const SERVICE = 'bert';

    public function classify(string $text): Classification
    {
        /** @var array<string, mixed> $config */
        $config = config('services.bert');

        try {
            $response = Http::baseUrl($config['url'])
                ->connectTimeout((int) $config['connect_timeout'])
                ->timeout((int) $config['timeout'])
                ->withHeaders(array_filter([
                    'X-Request-Id' => request()?->header('X-Request-Id'),
                ]))
                ->when(filled($config['internal_token'] ?? null), fn ($request) => $request->withToken($config['internal_token']))
                ->acceptJson()
                ->post('/predict', ['text' => $text]);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan inferensi BERT tidak dapat dihubungi.',
                previous: $exception,
            );
        }

        $this->guardAgainstFailure($response);

        try {
            return Classification::fromResponse($response->json())
                ->withConfidenceThreshold((float) $config['confidence_threshold']);
        } catch (InvalidArgumentException $exception) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Respons layanan BERT tidak sesuai kontrak: '.$exception->getMessage(),
                statusCode: $response->status(),
                previous: $exception,
            );
        }
    }

    /**
     * Splits failures into retryable and terminal so a malformed payload never
     * burns the retry budget, while an overloaded or restarting service does.
     */
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
                message: 'Layanan BERT sedang membatasi permintaan.',
                statusCode: $status,
                retryAfterSeconds: $this->retryAfter($response),
                previous: $previous,
            ),
            $status === 503 => AiServiceException::transient(
                service: self::SERVICE,
                message: 'Model BERT belum siap menerima permintaan.',
                statusCode: $status,
                retryAfterSeconds: $this->retryAfter($response),
                previous: $previous,
            ),
            $response->serverError() => AiServiceException::transient(
                service: self::SERVICE,
                message: "Layanan BERT mengembalikan galat server ({$status}).",
                statusCode: $status,
                previous: $previous,
            ),
            default => AiServiceException::permanent(
                service: self::SERVICE,
                message: "Permintaan ke layanan BERT ditolak ({$status}).",
                statusCode: $status,
                previous: $previous,
            ),
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}

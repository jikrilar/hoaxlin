<?php

namespace App\Services\Bert;

use App\Exceptions\AiServiceException;
use App\Models\ModelVersion;
use App\Support\RetryAfter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BertTrainingService
{
    public const BASELINE_VERSION = 'v1.0.0';

    private const SERVICE = 'bert';

    /**
     * Submit an asynchronous incremental training request to the BERT service.
     *
     * @return array<string, mixed>
     */
    public function startTraining(ModelVersion $version): array
    {
        $relativePath = trim((string) ($version->dataset_path ?? ''));
        $disk = Storage::disk('datasets');

        if ($relativePath === '' || ! $disk->exists($relativePath)) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Berkas dataset pelatihan CSV tidak ditemukan.',
            );
        }

        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Berkas dataset pelatihan CSV tidak dapat dibuka.',
            );
        }

        $config = config('services.bert', []);
        $timeout = max(1, (int) ($config['train_timeout'] ?? 15));
        $callbackUrl = $this->resolveCallbackUrl($config);

        try {
            $response = $this->client($config, $timeout)
                ->attach('csv_file', $stream, basename($relativePath), ['Content-Type' => 'text/csv'])
                ->post('/train', [
                    'version_id' => (string) $version->getKey(),
                    'version_name' => $version->version_name,
                    'callback_url' => $callbackUrl,
                ]);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan pelatihan BERT tidak dapat dihubungi.',
                previous: $exception,
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->guardAgainstTrainingFailure($response);

        $payload = $response->json();
        if (! is_array($payload) || ($payload['status'] ?? null) !== 'accepted') {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Respons layanan pelatihan BERT tidak sesuai kontrak.',
                statusCode: $response->status(),
            );
        }

        return $payload;
    }

    /**
     * Hot-swap the active model inside BERT first, then persist DB active state.
     */
    public function activateModelVersion(ModelVersion $version): ModelVersion
    {
        if (! $version->isCompleted()) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Hanya versi model berstatus selesai yang dapat diaktifkan.',
            );
        }

        $this->activateRemoteVersion($version->version_name);

        return $version->activate();
    }

    /**
     * Roll back runtime inference to the verified baseline v1.0.0 artifact.
     */
    public function activateBaseline(): void
    {
        $this->activateRemoteVersion(self::BASELINE_VERSION);

        DB::transaction(function (): void {
            $baseline = ModelVersion::query()
                ->where('version_name', self::BASELINE_VERSION)
                ->first();

            if ($baseline !== null) {
                $baseline->activate();

                return;
            }

            ModelVersion::query()
                ->where('is_active', true)
                ->get()
                ->each(fn (ModelVersion $active): bool => $active->update(['is_active' => false]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function activateRemoteVersion(string $versionName): array
    {
        $config = config('services.bert', []);
        $timeout = max(5, (int) ($config['activate_timeout'] ?? 60));

        try {
            $response = $this->client($config, $timeout)
                ->post('/activate-model', ['version_name' => $versionName]);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan BERT tidak dapat dihubungi saat mengaktifkan model.',
                previous: $exception,
            );
        }

        $this->guardAgainstActivationFailure($response);

        $payload = $response->json();
        if (
            ! is_array($payload)
            || ($payload['status'] ?? null) !== 'ok'
            || ($payload['model_version'] ?? null) !== $versionName
            || ($payload['model_status'] ?? null) !== 'ready'
        ) {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'Respons aktivasi layanan BERT tidak sesuai kontrak.',
                statusCode: $response->status(),
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config, int $timeout): PendingRequest
    {
        $baseUrl = rtrim((string) ($config['url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw AiServiceException::permanent(
                service: self::SERVICE,
                message: 'URL layanan BERT belum dikonfigurasi.',
            );
        }

        $connectTimeout = max(1, (int) ($config['connect_timeout'] ?? 3));
        $token = $config['internal_token'] ?? null;

        return Http::baseUrl($baseUrl)
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->withHeaders(array_filter([
                'X-Request-Id' => request()?->header('X-Request-Id'),
            ]))
            ->when(filled($token), fn (PendingRequest $request): PendingRequest => $request->withToken((string) $token))
            ->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveCallbackUrl(array $config): string
    {
        $configured = $config['callback_url'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return route('internal.bert.callback');
    }

    private function guardAgainstTrainingFailure(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $previous = $response->toException();
        $errorCode = data_get($response->json(), 'error.code');
        $remoteMessage = $this->safeRemoteMessage($response);

        if ($status === 409) {
            if ($errorCode === 'version_already_exists') {
                throw AiServiceException::permanent(
                    service: self::SERVICE,
                    message: $remoteMessage ?? 'Nama versi model sudah ada pada penyimpanan artefak BERT.',
                    statusCode: $status,
                    previous: $previous,
                );
            }

            throw AiServiceException::transient(
                service: self::SERVICE,
                message: $remoteMessage ?? 'Layanan BERT sedang menjalankan pelatihan model lain.',
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')) ?? 60,
                previous: $previous,
            );
        }

        $this->throwCommonFailure($response, $status, $remoteMessage, $previous);
    }

    private function guardAgainstActivationFailure(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $previous = $response->toException();
        $remoteMessage = $this->safeRemoteMessage($response);

        $this->throwCommonFailure($response, $status, $remoteMessage, $previous);
    }

    private function throwCommonFailure(
        Response $response,
        int $status,
        ?string $remoteMessage,
        ?\Throwable $previous,
    ): never {
        throw match (true) {
            $status === 429 => AiServiceException::transient(
                service: self::SERVICE,
                message: 'Layanan BERT sedang membatasi permintaan.',
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            $status === 503 => AiServiceException::transient(
                service: self::SERVICE,
                message: $remoteMessage ?? 'Layanan BERT belum siap menerima permintaan.',
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            $response->serverError() => AiServiceException::transient(
                service: self::SERVICE,
                message: "Layanan BERT mengalami galat server ({$status}).",
                statusCode: $status,
                retryAfterSeconds: RetryAfter::seconds($response->header('Retry-After')),
                previous: $previous,
            ),
            default => AiServiceException::permanent(
                service: self::SERVICE,
                message: $remoteMessage ?? "Permintaan ke layanan BERT ditolak ({$status}).",
                statusCode: $status,
                previous: $previous,
            ),
        };
    }

    private function safeRemoteMessage(Response $response): ?string
    {
        $message = data_get($response->json(), 'error.message');
        if (! is_string($message)) {
            return null;
        }

        $cleaned = trim($message);

        return $cleaned !== '' ? mb_substr($cleaned, 0, 500) : null;
    }
}

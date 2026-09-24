<?php

namespace App\Services\Pipeline;

use App\DataObjects\PipelineFailure;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Exceptions\SanitizedPipelineException;
use App\Models\Submission;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use LogicException;
use PDOException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Throwable;

class PipelineFailureReporter
{
    public const DEPENDENCY_UNAVAILABLE = 'DEPENDENCY_UNAVAILABLE';

    public const PROVIDER_REQUEST_REJECTED = 'PROVIDER_REQUEST_REJECTED';

    public const PERSISTENCE_ERROR = 'PERSISTENCE_ERROR';

    public const MEDIA_IO_ERROR = 'MEDIA_IO_ERROR';

    public const PIPELINE_CONFIGURATION_ERROR = 'PIPELINE_CONFIGURATION_ERROR';

    public const UNKNOWN_ERROR = 'PIPELINE_UNKNOWN_ERROR';

    /** @var array<string, string> */
    private const PUBLIC_MESSAGES = [
        self::DEPENDENCY_UNAVAILABLE => 'Layanan pendukung sementara tidak tersedia. Silakan coba lagi nanti.',
        self::PROVIDER_REQUEST_REJECTED => 'Permintaan analisis tidak dapat diproses oleh layanan pendukung.',
        self::PERSISTENCE_ERROR => 'Data analisis tidak dapat disimpan. Silakan coba lagi.',
        self::MEDIA_IO_ERROR => 'Media tidak dapat dibaca atau disimpan. Silakan unggah ulang media.',
        self::PIPELINE_CONFIGURATION_ERROR => 'Layanan analisis belum tersedia karena konfigurasi sistem.',
        self::UNKNOWN_ERROR => 'Terjadi kendala internal saat memproses berita. Silakan coba lagi.',
        'STALE_RECOVERY_UNSAFE' => 'Pemrosesan tidak dapat dipulihkan secara aman. Silakan kirim ulang berita.',
    ];

    public function report(
        Submission $submission,
        ProcessingStage $stage,
        Throwable $exception,
        int $attempt,
    ): PipelineFailure {
        $failure = $this->describe($exception);
        $context = [
            'submission_id' => $submission->getKey(),
            'stage' => $stage->value,
            'service' => $failure->service,
            'error_code' => $failure->errorCode,
            'attempt' => $attempt,
            'exception_class' => $this->safeExceptionClass($exception),
            'error_reference' => $failure->reference,
            'retryable' => $failure->retryable,
        ];

        if ($failure->providerStatus !== null) {
            $context['provider_status'] = $failure->providerStatus;
        }

        if ($failure->retryAfterSeconds !== null) {
            $context['retry_after_seconds'] = $failure->retryAfterSeconds;
        }

        // Never attach the exception, message, trace, payload, headers, or
        // connection details: any of them may contain credentials or user data.
        Log::error('Submission pipeline failure.', $context);

        return $failure;
    }

    public function describe(Throwable $exception): PipelineFailure
    {
        if ($exception instanceof SanitizedPipelineException) {
            $errorCode = array_key_exists($exception->errorCode, self::PUBLIC_MESSAGES)
                ? $exception->errorCode
                : self::UNKNOWN_ERROR;

            return new PipelineFailure(
                self::PUBLIC_MESSAGES[$errorCode],
                $this->safeMetadataService($exception->service),
                $errorCode,
                $exception->retryable,
                (string) Str::uuid(),
                $this->safeHttpStatus($exception->providerStatus),
                $this->safeRetryAfter($exception->retryAfterSeconds),
            );
        }

        if ($exception instanceof AiServiceException) {
            $unavailable = $exception->retryable
                || $exception->statusCode === 408
                || $exception->statusCode === 429
                || ($exception->statusCode !== null && $exception->statusCode >= 500);
            $code = $unavailable ? self::DEPENDENCY_UNAVAILABLE : self::PROVIDER_REQUEST_REJECTED;

            return $this->failure(
                $code,
                $this->safeService($exception->service),
                $exception->retryable,
                $this->safeHttpStatus($exception->statusCode),
                $this->safeRetryAfter($exception->retryAfterSeconds),
            );
        }

        if ($this->chainContains($exception, fn (Throwable $item): bool => $item instanceof ConnectionException || $item instanceof TimeoutExceededException)) {
            return $this->failure(self::DEPENDENCY_UNAVAILABLE, 'pipeline', true);
        }

        if ($this->chainContains($exception, fn (Throwable $item): bool => $item instanceof QueryException || $item instanceof PDOException)) {
            return $this->failure(self::PERSISTENCE_ERROR, 'database');
        }

        if ($this->chainContains($exception, fn (Throwable $item): bool => $item instanceof FilesystemException || $item instanceof FileException)) {
            return $this->failure(self::MEDIA_IO_ERROR, 'filesystem');
        }

        if ($this->chainContains($exception, fn (Throwable $item): bool => $item instanceof BindingResolutionException || $item instanceof InvalidArgumentException || $item instanceof LogicException)) {
            return $this->failure(self::PIPELINE_CONFIGURATION_ERROR, 'pipeline');
        }

        return $this->failure(self::UNKNOWN_ERROR, 'pipeline');
    }

    public function sanitizedException(PipelineFailure $failure): SanitizedPipelineException
    {
        return new SanitizedPipelineException(
            $failure->publicMessage,
            $failure->service,
            $failure->errorCode,
            $failure->retryable,
            $failure->providerStatus,
            $failure->retryAfterSeconds,
        );
    }

    public function publicMessageForCode(?string $errorCode): string
    {
        return self::PUBLIC_MESSAGES[$errorCode ?? ''] ?? self::PUBLIC_MESSAGES[self::UNKNOWN_ERROR];
    }

    private function failure(
        string $code,
        string $service,
        bool $retryable = false,
        ?int $providerStatus = null,
        ?int $retryAfterSeconds = null,
    ): PipelineFailure {
        return new PipelineFailure(
            self::PUBLIC_MESSAGES[$code],
            $service,
            $code,
            $retryable,
            (string) Str::uuid(),
            $providerStatus,
            $retryAfterSeconds,
        );
    }

    private function safeService(string $service): string
    {
        $service = strtolower($service);

        return in_array($service, ['article', 'bert', 'openai', 'rag'], true) ? $service : 'provider';
    }

    private function safeMetadataService(string $service): string
    {
        $service = strtolower($service);

        return in_array($service, ['article', 'bert', 'database', 'filesystem', 'openai', 'pipeline', 'provider', 'rag', 'watchdog'], true)
            ? $service
            : 'pipeline';
    }

    private function safeHttpStatus(?int $status): ?int
    {
        return $status !== null && $status >= 100 && $status <= 599 ? $status : null;
    }

    private function safeRetryAfter(?int $seconds): ?int
    {
        return $seconds !== null && $seconds > 0
            ? min($seconds, max(1, (int) config('pipeline.retry.max_delay_seconds', 900)))
            : null;
    }

    private function safeExceptionClass(Throwable $exception): string
    {
        $class = $exception::class;

        return str_contains($class, '@anonymous') ? 'AnonymousThrowable' : $class;
    }

    /** @param callable(Throwable): bool $predicate */
    private function chainContains(Throwable $exception, callable $predicate): bool
    {
        $current = $exception;

        for ($depth = 0; $depth < 10; $depth++) {
            if ($predicate($current)) {
                return true;
            }

            $current = $current->getPrevious();
            if ($current === null) {
                return false;
            }
        }

        return false;
    }
}

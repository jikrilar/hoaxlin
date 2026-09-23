<?php

namespace App\Services\Media;

use App\Exceptions\AiServiceException;

class TranscriptionMediaContract
{
    /** @return array<string, list<string>> */
    public function uploadTypes(): array
    {
        return config('media.transcription.upload_types', []);
    }

    /** @return array<string, list<string>> */
    public function remoteTypes(): array
    {
        return config('media.transcription.remote_types', []);
    }

    /** @return list<string> */
    public function uploadExtensions(): array
    {
        return array_keys($this->uploadTypes());
    }

    /** @return list<string> */
    public function uploadMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->uploadTypes()))));
    }

    public function maxBytes(): int
    {
        return max(1, (int) config('media.transcription.max_bytes'));
    }

    public function maxKilobytes(): int
    {
        return intdiv($this->maxBytes(), 1024);
    }

    public function maxSizeLabel(): string
    {
        $bytes = $this->maxBytes();

        if ($bytes % 1048576 === 0) {
            return intdiv($bytes, 1048576).' MiB';
        }

        if ($bytes % 1024 === 0) {
            return intdiv($bytes, 1024).' KiB';
        }

        return $bytes.' byte';
    }

    public function maxDurationSeconds(): int
    {
        return max(1, (int) config('media.transcription.max_duration_seconds'));
    }

    public function directUrlError(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ((array) config('media.transcription.blocked_platform_hosts', []) as $blockedHost) {
            $blockedHost = strtolower((string) $blockedHost);
            if ($host === $blockedHost || str_ends_with($host, '.'.$blockedHost)) {
                return 'Platform video tidak didukung. Gunakan URL langsung ke file audio/video.';
            }
        }

        $extension = $this->extensionFromUrl($url);
        if ($extension === null || ! array_key_exists($extension, $this->remoteTypes())) {
            return 'URL harus langsung mengarah ke file audio/video dengan format yang didukung.';
        }

        return null;
    }

    public function isUploadCompatible(string $filename, ?string $mimeType): bool
    {
        $extension = $this->extensionFromFilename($filename);
        $mimeType = $this->normalizeMime($mimeType);

        return $extension !== null
            && isset($this->uploadTypes()[$extension])
            && in_array($mimeType, $this->uploadTypes()[$extension], true);
    }

    public function assertUpload(string $filename, ?string $mimeType, string $bytes): void
    {
        if (strlen($bytes) > $this->maxBytes()) {
            throw AiServiceException::permanent('openai', 'Ukuran media melebihi batas transkripsi.');
        }

        if (! $this->isUploadCompatible($filename, $mimeType)) {
            throw AiServiceException::permanent('openai', 'Format atau MIME media tidak didukung untuk transkripsi.');
        }

        $extension = $this->extensionFromFilename($filename);
        if ($extension === null || ! $this->hasExpectedSignature($extension, $bytes)) {
            throw AiServiceException::permanent('openai', 'Isi file tidak sesuai dengan format media yang dinyatakan.');
        }
    }

    public function assertDirectUrl(string $url): void
    {
        if ($message = $this->directUrlError($url)) {
            throw AiServiceException::permanent('openai', $message);
        }
    }

    public function assertTimeoutHierarchy(): void
    {
        $provider = (int) config('media.transcription.provider_timeout_seconds');
        $job = (int) config('media.transcription.job_timeout_seconds');
        $worker = (int) config('media.transcription.worker_timeout_seconds');
        $retryAfter = (int) config('media.transcription.retry_after_seconds');

        if (! ($provider > 0 && $provider < $job && $job < $worker && $worker < $retryAfter)) {
            throw new \LogicException('Media timeout hierarchy is invalid.');
        }
    }

    public function validateRemoteResponse(
        string $effectiveUrl,
        ?string $contentDisposition,
        ?string $contentType,
        ?string $contentLength,
        string $bytes,
    ): string {
        if ($contentLength !== null && $contentLength !== '') {
            if (! ctype_digit(trim($contentLength)) || (int) $contentLength < 0) {
                throw AiServiceException::permanent('openai', 'Content-Length media tidak valid.');
            }

            if ((int) $contentLength > $this->maxBytes()) {
                throw AiServiceException::permanent('openai', 'Ukuran media melebihi batas transkripsi.');
            }
        }

        $actualBytes = strlen($bytes);
        if ($actualBytes > $this->maxBytes()) {
            throw AiServiceException::permanent('openai', 'Ukuran media melebihi batas transkripsi.');
        }

        if ($actualBytes < max(1, (int) config('media.transcription.minimum_bytes', 1024))) {
            throw AiServiceException::permanent('openai', 'File media terlalu kecil atau tidak valid.');
        }

        $filename = $this->filename($effectiveUrl, $contentDisposition);
        $extension = $this->extensionFromFilename($filename);
        $urlExtension = $this->extensionFromUrl($effectiveUrl);
        $types = $this->remoteTypes();
        if ($extension === null || ! isset($types[$extension])) {
            throw AiServiceException::permanent('openai', 'Format file media tidak didukung.');
        }
        if ($urlExtension === null || $urlExtension !== $extension) {
            throw AiServiceException::permanent('openai', 'Extension URL dan nama file media tidak kompatibel.');
        }

        $mimeType = $this->normalizeMime($contentType);
        if ($mimeType === 'application/octet-stream') {
            if (! $this->hasExpectedSignature($extension, $bytes)) {
                throw AiServiceException::permanent('openai', 'Media biner tidak memiliki signature format yang sesuai.');
            }
        } elseif (! in_array($mimeType, $types[$extension], true)) {
            throw AiServiceException::permanent('openai', 'Extension dan Content-Type media tidak kompatibel.');
        }

        if (! $this->hasExpectedSignature($extension, $bytes)) {
            throw AiServiceException::permanent('openai', 'Isi file tidak sesuai dengan format media yang dinyatakan.');
        }

        return $filename;
    }

    private function filename(string $effectiveUrl, ?string $contentDisposition): string
    {
        if (is_string($contentDisposition)
            && preg_match('/filename\*?=(?:UTF-8\'\')?["\']?([^"\';]+)/i', $contentDisposition, $matches)) {
            $filename = basename(str_replace('\\', '/', rawurldecode(trim($matches[1]))));
            if ($filename !== '') {
                return $filename;
            }
        }

        $path = rawurldecode((string) parse_url($effectiveUrl, PHP_URL_PATH));
        $filename = basename($path);

        return $filename !== '' ? $filename : 'media';
    }

    private function extensionFromUrl(string $url): ?string
    {
        return $this->extensionFromFilename((string) parse_url($url, PHP_URL_PATH));
    }

    private function extensionFromFilename(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    private function normalizeMime(?string $mimeType): string
    {
        return strtolower(trim(explode(';', (string) $mimeType, 2)[0]));
    }

    private function hasExpectedSignature(string $extension, string $bytes): bool
    {
        return match ($extension) {
            'flac' => str_starts_with($bytes, 'fLaC'),
            'mp3', 'mpga' => str_starts_with($bytes, 'ID3') || $this->hasMp3FrameSync($bytes),
            'mp4', 'm4a' => strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp',
            'mpeg' => str_starts_with($bytes, "\x00\x00\x01\xBA")
                || str_starts_with($bytes, "\x00\x00\x01\xB3")
                || $this->hasMp3FrameSync($bytes),
            'ogg' => str_starts_with($bytes, 'OggS'),
            'wav' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WAVE',
            'webm' => str_starts_with($bytes, "\x1A\x45\xDF\xA3"),
            default => false,
        };
    }

    private function hasMp3FrameSync(string $bytes): bool
    {
        return strlen($bytes) >= 2
            && ord($bytes[0]) === 0xFF
            && (ord($bytes[1]) & 0xE0) === 0xE0;
    }
}

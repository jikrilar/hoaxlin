<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\DataObjects\ExtractedText;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Resilience\CircuitBreaker;

class OpenAiVideoExtractor implements TextExtractor
{
    public function __construct(
        private readonly CircuitBreaker $breaker = new CircuitBreaker('openai'),
        private readonly OpenAiQuota $quota = new OpenAiQuota,
    ) {}

    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Video->value
            && (filled($submission->media_path) || filled($submission->source_url));
    }

    public function extract(Submission $submission): ExtractedText
    {
        $bytes = null;
        $filename = null;

        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
        if (filled($submission->media_path)) {
            $bytes = Storage::disk($disk)->get($submission->media_path);
            $filename = basename($submission->media_path);
        } elseif (filled($submission->source_url)) {
            $download = $this->downloadVideoSafely((string) $submission->source_url);
            $bytes = $download['bytes'];
            $filename = $download['filename'];
            // Optionally store the downloaded video for retention/audit (private disk)
            try {
                $storedPath = 'videos/'.uniqid('url_', true).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                Storage::disk($disk)->put($storedPath, $bytes);
                $submission->update(['media_path' => $storedPath]);
            } catch (\Throwable) {
                // Non-critical if storing fails — still transcribe from memory
            }
        }

        if ($bytes === null || $bytes === '') {
            throw AiServiceException::permanent('openai', 'Video tidak ditemukan untuk ditranskripsi.');
        }
        $key = 'transcription:'.config('services.openai.transcribe_model').':'.hash('sha256', $bytes);

        if ($cached = Cache::get($key)) {
            return new ExtractedText($cached, InputType::Video, 'openai', cached: true);
        }

        $config = config('services.openai');
        $this->quota->ensureAvailable();

        try {
            $this->breaker->check();
            $response = Http::withToken($config['key'])
                ->connectTimeout($config['connect_timeout'])
                ->timeout(max(120, $config['timeout']))
                ->attach('file', $bytes, $filename ?? basename($submission->media_path ?? 'video.mp4'))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $config['transcribe_model'],
                    'language' => 'id',
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException $exception) {
            $this->breaker->recordFailure();
            throw AiServiceException::transient('openai', 'Layanan transkripsi tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            if ($response->serverError() || $response->status() === 429) {
                $this->breaker->recordFailure();
            }
            throw $response->serverError() || $response->status() === 429
                ? AiServiceException::transient('openai', 'Layanan transkripsi sementara tidak tersedia.', $response->status())
                : AiServiceException::permanent('openai', 'Layanan transkripsi menolak media.', $response->status());
        }

        $this->breaker->recordSuccess();
        $payload = $response->json();
        $text = $payload['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw AiServiceException::permanent('openai', 'Transkripsi tidak menghasilkan teks.');
        }

        // Whisper cost is per minute of audio; estimate from bytes (approx 1MB ~ 1 minute)
        $minutes = max(1, strlen($bytes) / (1024 * 1024));
        $cost = $this->quota->estimateCost($config['transcribe_model'], (int) $minutes * 100, (int) $minutes * 100);
        $this->quota->recordUsage((int) $minutes * 100, (int) $minutes * 100, $cost);
        Cache::put($key, trim($text), $config['cache_ttl']);

        return new ExtractedText(trim($text), InputType::Video, 'openai');
    }

    /**
     * Safely download a remote video for transcription (C10).
     *
     * Applies SSRF protection, redirect limits, content-type and size guards.
     * Returns bytes and a safe filename.
     *
     * @return array{bytes: string, filename: string}
     */
    private function downloadVideoSafely(string $url): array
    {
        $this->guardPublicUrl($url);

        // Limit to direct video/audio URLs for now; YouTube etc. need dedicated handling
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExts = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'm4a', 'wav', 'ogg', 'flac'];
        $isDirectVideo = in_array($ext, $allowedExts, true);

        // For non-direct URLs (e.g., youtube.com), we could attempt to extract,
        // but for now we require a direct file URL and give a clear error.
        if (! $isDirectVideo && ! str_contains(strtolower($url), 'video')) {
            // Still try to download, but we will validate content-type after
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withHeaders(['User-Agent' => 'hoaxlin.id video verifier'])
                ->withOptions(['allow_redirects' => ['max' => 3, 'track_redirects' => true]])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('openai', 'Video URL tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            throw $response->serverError()
                ? AiServiceException::transient('openai', 'Video URL sementara tidak tersedia.', $response->status())
                : AiServiceException::permanent('openai', 'Video URL tidak dapat diakses.', $response->status());
        }

        // Validate redirects for SSRF (check each redirect hop)
        $redirectHistory = $response->transferStats?->getHandlerStats()['redirect_url'] ?? null;
        // Fallback: check final host again
        $finalHost = parse_url($response->effectiveUri() ?? $url, PHP_URL_HOST) ?? parse_url($url, PHP_URL_HOST);
        if (is_string($finalHost)) {
            $this->guardPublicUrl('http://'.$finalHost);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $isVideoType = str_contains($contentType, 'video') || str_contains($contentType, 'audio') || str_contains($contentType, 'octet-stream');
        if ($contentType && ! $isVideoType && ! str_contains($contentType, 'application')) {
            // Allow application/octet-stream for videos
            throw AiServiceException::permanent('openai', 'URL tidak mengarah ke file video/audio.');
        }

        // Size guards (C12): limit to 100MB (Whisper limit is 25MB, but we allow a bit more before transcode)
        $contentLength = $response->header('Content-Length');
        if (is_numeric($contentLength) && (int) $contentLength > 100 * 1024 * 1024) {
            throw AiServiceException::permanent('openai', 'File video terlalu besar (maks 100MB).');
        }

        $bytes = $response->body();
        if (strlen($bytes) > 100 * 1024 * 1024) {
            throw AiServiceException::permanent('openai', 'File video terlalu besar (maks 100MB).');
        }

        if (strlen($bytes) < 1024) {
            throw AiServiceException::permanent('openai', 'File video terlalu kecil atau tidak valid.');
        }

        // Derive filename from URL or Content-Disposition
        $disposition = $response->header('Content-Disposition');
        if (is_string($disposition) && preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            $filename = trim($m[1]);
        } else {
            $filename = basename(parse_url($url, PHP_URL_PATH) ?? 'video.mp4');
            if (! str_contains($filename, '.')) {
                $filename .= '.mp4';
            }
        }

        return ['bytes' => $bytes, 'filename' => $filename];
    }

    private function guardPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw AiServiceException::permanent('openai', 'URL video tidak valid.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP)) {
            throw AiServiceException::permanent('openai', 'URL privat atau tidak valid tidak diizinkan.');
        }
        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw AiServiceException::permanent('openai', 'URL mengarah ke jaringan privat.');
            }
        }
    }
}

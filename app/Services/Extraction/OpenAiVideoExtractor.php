<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\DataObjects\ExtractedText;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use App\Services\Media\MediaDurationProbe;
use App\Services\Media\TranscriptionMediaContract;
use App\Services\Network\SafeExternalHttpClient;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Resilience\CircuitBreaker;
use App\Support\RetryAfter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAiVideoExtractor implements TextExtractor
{
    public function __construct(
        private readonly CircuitBreaker $breaker = new CircuitBreaker('openai'),
        private readonly OpenAiQuota $quota = new OpenAiQuota,
        private readonly ?SafeExternalHttpClient $externalHttp = null,
        private readonly ?TranscriptionMediaContract $mediaContract = null,
        private readonly ?MediaDurationProbe $durationProbe = null,
    ) {}

    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Video->value
            && (filled($submission->media_path) || filled($submission->source_url));
    }

    public function extract(Submission $submission): ExtractedText
    {
        $this->contract()->assertTimeoutHierarchy();

        $bytes = null;
        $filename = null;
        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));

        if (filled($submission->media_path)) {
            $bytes = Storage::disk($disk)->get($submission->media_path);
            $filename = basename($submission->media_path);
            $this->contract()->assertUpload(
                $filename,
                Storage::disk($disk)->mimeType($submission->media_path),
                $bytes,
            );
        } elseif (filled($submission->source_url)) {
            $download = $this->downloadVideoSafely((string) $submission->source_url);
            $bytes = $download['bytes'];
            $filename = $download['filename'];

            $storedPath = 'videos/'.uniqid('url_', true).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            if (! Storage::disk($disk)->put($storedPath, $bytes)) {
                throw new \RuntimeException('Media storage write failed.');
            }
            $submission->update(['media_path' => $storedPath]);
        }

        if ($bytes === null || $bytes === '') {
            throw AiServiceException::permanent('openai', 'Video tidak ditemukan untuk ditranskripsi.');
        }

        $duration = $this->probe()->probeBytes($bytes);
        if ($duration !== null && $duration > $this->contract()->maxDurationSeconds()) {
            throw AiServiceException::permanent('openai', 'Durasi media melebihi batas transkripsi.');
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
                ->timeout((int) config('media.transcription.provider_timeout_seconds', 90))
                ->attach('file', $bytes, $filename ?? 'video.mp4')
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $config['transcribe_model'],
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException $exception) {
            $this->breaker->recordFailure();
            throw AiServiceException::transient('openai', 'Layanan transkripsi tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            $transient = $response->serverError() || in_array($response->status(), [408, 429], true);
            if ($transient) {
                $this->breaker->recordFailure();
            }

            throw $transient
                ? AiServiceException::transient('openai', 'Layanan transkripsi sementara tidak tersedia.', $response->status(), RetryAfter::seconds($response->header('Retry-After')))
                : AiServiceException::permanent('openai', 'Layanan transkripsi menolak media.', $response->status());
        }

        $this->breaker->recordSuccess();
        $text = $response->json('text');
        if (! is_string($text) || trim($text) === '') {
            throw AiServiceException::permanent('openai', 'Transkripsi tidak menghasilkan teks.');
        }

        $minutes = max(1, strlen($bytes) / (1024 * 1024));
        $cost = $this->quota->estimateCost($config['transcribe_model'], (int) $minutes * 100, (int) $minutes * 100);
        $this->quota->recordUsage((int) $minutes * 100, (int) $minutes * 100, $cost);
        Cache::put($key, trim($text), $config['cache_ttl']);

        return new ExtractedText(trim($text), InputType::Video, 'openai');
    }

    /** @return array{bytes: string, filename: string} */
    private function downloadVideoSafely(string $url): array
    {
        $this->contract()->assertDirectUrl($url);

        try {
            $download = $this->externalHttp()->get($url, Http::connectTimeout(5)
                ->timeout((int) config('media.transcription.download_timeout_seconds', 20))
                ->withHeaders(['User-Agent' => 'hoaxlin.id video verifier']), 'openai');
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('openai', 'Video URL tidak dapat dihubungi.', previous: $exception);
        }

        $response = $download['response'];
        if ($response->failed()) {
            $transient = $response->serverError() || in_array($response->status(), [408, 429], true);

            throw $transient
                ? AiServiceException::transient('openai', 'Video URL sementara tidak tersedia.', $response->status(), RetryAfter::seconds($response->header('Retry-After')))
                : AiServiceException::permanent('openai', 'Video URL tidak dapat diakses.', $response->status());
        }

        $bytes = $response->body();
        $this->contract()->assertDirectUrl($download['url']);
        $filename = $this->contract()->validateRemoteResponse(
            $download['url'],
            $response->header('Content-Disposition'),
            $response->header('Content-Type'),
            $response->header('Content-Length'),
            $bytes,
        );

        return ['bytes' => $bytes, 'filename' => $filename];
    }

    private function externalHttp(): SafeExternalHttpClient
    {
        return $this->externalHttp ?? app(SafeExternalHttpClient::class);
    }

    private function contract(): TranscriptionMediaContract
    {
        return $this->mediaContract ?? app(TranscriptionMediaContract::class);
    }

    private function probe(): MediaDurationProbe
    {
        return $this->durationProbe ?? app(MediaDurationProbe::class);
    }
}

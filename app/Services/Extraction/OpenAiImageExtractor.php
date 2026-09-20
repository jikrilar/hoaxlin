<?php

namespace App\Services\Extraction;

use App\Contracts\TextExtractor;
use App\DataObjects\ExtractedText;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Resilience\CircuitBreaker;
use App\Support\RetryAfter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAiImageExtractor implements TextExtractor
{
    public function __construct(
        private readonly CircuitBreaker $breaker = new CircuitBreaker('openai'),
        private readonly OpenAiQuota $quota = new OpenAiQuota,
    ) {}

    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Image->value && filled($submission->media_path);
    }

    public function extract(Submission $submission): ExtractedText
    {
        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
        $bytes = Storage::disk($disk)->get($submission->media_path);
        $hash = hash('sha256', $bytes);
        $key = 'ocr:'.config('services.openai.vision_model').':'.$hash;

        if ($cached = Cache::get($key)) {
            return new ExtractedText($cached, InputType::Image, 'openai', cached: true);
        }

        $config = config('services.openai');
        $reservation = null;

        try {
            $this->breaker->check();
            $reservation = $this->quota->reserve('ocr');
            $response = Http::withToken($config['key'])
                ->connectTimeout($config['connect_timeout'])
                ->timeout($config['timeout'])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $config['vision_model'],
                    'temperature' => 0,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Ekstrak seluruh teks berita berbahasa Indonesia dari gambar ini. Kembalikan teks saja.'],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.(Storage::disk($disk)->mimeType($submission->media_path) ?: 'image/jpeg').';base64,'.base64_encode($bytes)]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            $this->quota->release($reservation);
            $this->breaker->recordFailure();
            throw AiServiceException::transient('openai', 'Layanan OCR tidak dapat dihubungi.', previous: $exception);
        } catch (\Throwable $exception) {
            $this->quota->release($reservation);
            throw $exception;
        }

        if ($response->failed()) {
            $this->quota->release($reservation);
            if ($response->serverError() || $response->status() === 429) {
                $this->breaker->recordFailure();
            }
            throw $response->serverError() || $response->status() === 429
                ? AiServiceException::transient('openai', 'Layanan OCR sementara tidak tersedia.', $response->status(), RetryAfter::seconds($response->header('Retry-After')))
                : AiServiceException::permanent('openai', 'Layanan OCR menolak gambar.', $response->status());
        }

        $this->breaker->recordSuccess();
        $payload = $response->json();
        $text = data_get($payload, 'choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            $this->quota->release($reservation);
            throw AiServiceException::permanent('openai', 'OCR tidak menemukan teks yang dapat dianalisis.');
        }

        $usage = $payload['usage'] ?? [];
        $usageAvailable = is_numeric($usage['prompt_tokens'] ?? null)
            || is_numeric($usage['completion_tokens'] ?? null);
        $cost = $usageAvailable
            ? $this->quota->estimateCost($config['vision_model'], (int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0))
            : $reservation->reservedMicrousd / 1_000_000;
        try {
            $this->quota->finalize($reservation, (int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0), $cost);
        } catch (\Throwable $exception) {
            $this->quota->release($reservation);
            throw $exception;
        }
        Cache::put($key, trim($text), $config['cache_ttl']);

        return new ExtractedText(trim($text), InputType::Image, 'openai');
    }
}

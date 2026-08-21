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

class OpenAiImageExtractor implements TextExtractor
{
    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Image->value && filled($submission->media_path);
    }

    public function extract(Submission $submission): ExtractedText
    {
        $bytes = Storage::get($submission->media_path);
        $hash = hash('sha256', $bytes);
        $key = 'ocr:'.config('services.openai.vision_model').':'.$hash;

        if ($cached = Cache::get($key)) {
            return new ExtractedText($cached, InputType::Image, 'openai', cached: true);
        }

        $config = config('services.openai');

        try {
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
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.(Storage::mimeType($submission->media_path) ?: 'image/jpeg').';base64,'.base64_encode($bytes)]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('openai', 'Layanan OCR tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            throw $response->serverError() || $response->status() === 429
                ? AiServiceException::transient('openai', 'Layanan OCR sementara tidak tersedia.', $response->status())
                : AiServiceException::permanent('openai', 'Layanan OCR menolak gambar.', $response->status());
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw AiServiceException::permanent('openai', 'OCR tidak menemukan teks yang dapat dianalisis.');
        }

        Cache::put($key, trim($text), $config['cache_ttl']);

        return new ExtractedText(trim($text), InputType::Image, 'openai');
    }
}

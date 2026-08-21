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

class OpenAiVideoExtractor implements TextExtractor
{
    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Video->value && filled($submission->media_path);
    }

    public function extract(Submission $submission): ExtractedText
    {
        $bytes = Storage::get($submission->media_path);
        $key = 'transcription:'.config('services.openai.transcribe_model').':'.hash('sha256', $bytes);

        if ($cached = Cache::get($key)) {
            return new ExtractedText($cached, InputType::Video, 'openai', cached: true);
        }

        $config = config('services.openai');

        try {
            $response = Http::withToken($config['key'])
                ->connectTimeout($config['connect_timeout'])
                ->timeout(max(120, $config['timeout']))
                ->attach('file', $bytes, basename($submission->media_path))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $config['transcribe_model'],
                    'language' => 'id',
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('openai', 'Layanan transkripsi tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            throw $response->serverError() || $response->status() === 429
                ? AiServiceException::transient('openai', 'Layanan transkripsi sementara tidak tersedia.', $response->status())
                : AiServiceException::permanent('openai', 'Layanan transkripsi menolak media.', $response->status());
        }

        $text = $response->json('text');
        if (! is_string($text) || trim($text) === '') {
            throw AiServiceException::permanent('openai', 'Transkripsi tidak menghasilkan teks.');
        }

        Cache::put($key, trim($text), $config['cache_ttl']);

        return new ExtractedText(trim($text), InputType::Video, 'openai');
    }
}

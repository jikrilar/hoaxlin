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

class ArticleExtractor implements TextExtractor
{
    public function supports(Submission $submission): bool
    {
        return $submission->input_type === InputType::Url->value && filled($submission->source_url);
    }

    public function extract(Submission $submission): ExtractedText
    {
        $url = (string) $submission->source_url;
        $this->guardPublicUrl($url);
        $key = 'article:1.0:'.hash('sha256', $url);

        if ($cached = Cache::get($key)) {
            return new ExtractedText($cached, InputType::Url, 'article-parser', cached: true);
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders(['User-Agent' => 'hoaxlin.id article verifier'])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('article', 'Situs berita tidak dapat dihubungi.', previous: $exception);
        }

        if ($response->failed()) {
            throw $response->serverError()
                ? AiServiceException::transient('article', 'Situs berita sementara tidak tersedia.', $response->status())
                : AiServiceException::permanent('article', 'Konten artikel tidak dapat diambil.', $response->status());
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'text/plain')) {
            throw AiServiceException::permanent('article', 'URL tidak mengarah ke artikel teks.');
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($response->body())) ?? '');
        if (mb_strlen($text) < 50) {
            throw AiServiceException::permanent('article', 'Teks artikel terlalu pendek untuk dianalisis.');
        }

        $text = mb_substr($text, 0, 50000);
        Cache::put($key, $text, 3600);

        return new ExtractedText($text, InputType::Url, 'article-parser');
    }

    private function guardPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP)) {
            throw AiServiceException::permanent('article', 'URL privat atau tidak valid tidak diizinkan.');
        }

        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw AiServiceException::permanent('article', 'URL mengarah ke jaringan privat.');
            }
        }
    }
}

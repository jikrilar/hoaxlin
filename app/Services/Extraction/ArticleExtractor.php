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
                ->withOptions(['allow_redirects' => ['max' => 3, 'track_redirects' => true]])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw AiServiceException::transient('article', 'Situs berita tidak dapat dihubungi.', previous: $exception);
        }

        // SSRF: verify all redirect targets were public (C11)
        if (method_exists($response, 'transferStats') && $response->transferStats) {
            $stats = $response->transferStats->getHandlerStats();
            $redirectHistory = $stats['redirect_url'] ?? null;
            if (is_string($redirectHistory) && $redirectHistory !== '') {
                $this->guardPublicUrl($redirectHistory);
            }
        }
        // Also verify the effective URI if available
        $effectiveUri = method_exists($response, 'effectiveUri') ? $response->effectiveUri() : null;
        if (is_string($effectiveUri) && $effectiveUri !== $url) {
            $this->guardPublicUrl($effectiveUri);
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

        // Size guard (C12): limit to 2MB body
        $body = $response->body();
        if (strlen($body) > 2 * 1024 * 1024) {
            throw AiServiceException::permanent('article', 'Artikel terlalu besar (maks 2MB).');
        }

        $text = $this->extractReadableText($body, $url);
        if (mb_strlen($text) < 50) {
            throw AiServiceException::permanent('article', 'Teks artikel terlalu pendek untuk dianalisis.');
        }

        $text = mb_substr($text, 0, 50000);
        Cache::put($key, $text, 3600);

        return new ExtractedText($text, InputType::Url, 'article-parser');
    }

    /**
     * Extract the main article text with a lightweight readability heuristic (C11).
     *
     * Falls back to strip_tags if DOM parsing fails, but prefers the largest
     * text container among <article>, <main>, or <div> after removing boilerplate.
     */
    private function extractReadableText(string $html, string $url): string
    {
        // Suppress libxml errors for malformed HTML
        $prev = libxml_use_internal_errors(true);

        try {
            $doc = new \DOMDocument();
            // Prepend XML encoding to handle UTF-8
            $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
            if (! $loaded) {
                throw new \RuntimeException('DOM load failed');
            }

            $xpath = new \DOMXPath($doc);

            // Remove boilerplate: script, style, nav, header, footer, aside, form, iframe, noscript
            $boilerplate = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'form', 'iframe', 'noscript', 'svg', 'canvas'];
            foreach ($boilerplate as $tag) {
                $nodes = $xpath->query('//'.$tag);
                if ($nodes) {
                    foreach (iterator_to_array($nodes) as $node) {
                        $node->parentNode?->removeChild($node);
                    }
                }
            }
            // Remove hidden elements and known ad containers
            $hidden = $xpath->query('//*[contains(@style,"display:none") or contains(@class,"ad-") or contains(@id,"ad-")]');
            if ($hidden) {
                foreach (iterator_to_array($hidden) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }

            // Prefer <article>, then <main>, then the largest <div> by text length
            $candidates = [];
            foreach (['//article', '//main', '//div[contains(@class,"content")]', '//div[contains(@class,"article")]', '//body'] as $expr) {
                $nodes = $xpath->query($expr);
                if ($nodes) {
                    foreach ($nodes as $node) {
                        $text = trim($node->textContent ?? '');
                        if (mb_strlen($text) > 200) {
                            $candidates[] = $text;
                        }
                    }
                }
                if (! empty($candidates)) {
                    // Use the longest candidate from this priority level
                    usort($candidates, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
                    $best = $candidates[0];
                    libxml_clear_errors();
                    libxml_use_internal_errors($prev);

                    return trim(preg_replace('/\s+/u', ' ', $best) ?? '');
                }
            }

            // Fallback: body text
            $body = $doc->getElementsByTagName('body')->item(0);
            $text = $body ? trim($body->textContent ?? '') : '';

            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if (mb_strlen($text) >= 50) {
                return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            }

            // Final fallback to strip_tags
            return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        } catch (\Throwable) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        }
    }

    private function guardPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw AiServiceException::permanent('article', 'URL tidak valid.');
        }

        // Direct IP check (no DNS lookup needed)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw AiServiceException::permanent('article', 'URL privat atau tidak valid tidak diizinkan.');
            }
            return;
        }

        // DNS rebinding protection: resolve and check all IPs
        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw AiServiceException::permanent('article', 'Host tidak dapat di-resolve.');
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw AiServiceException::permanent('article', 'URL mengarah ke jaringan privat.');
            }
        }
    }
}

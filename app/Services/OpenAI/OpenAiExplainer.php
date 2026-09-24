<?php

namespace App\Services\OpenAI;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\Exceptions\AiServiceException;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class OpenAiExplainer implements Explainer
{
    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly OpenAiQuota $quota = new OpenAiQuota,
    ) {}

    /**
     * @param  list<array{
     *     document_id: string,
     *     title: string,
     *     source: string,
     *     source_url: string,
     *     published_at: string|null,
     *     snippet: string,
     *     similarity_score: float,
     *     rank: int,
     *     knowledge_base_version: string|null
     * }>  $evidence
     */
    public function explain(Classification $classification, string $excerpt, array $evidence): Explanation
    {
        $config = config('services.openai');
        $evidence = $this->canonicalEvidence($evidence);
        $cacheContext = [
            'prompt_version' => config('app.ai_prompt_version', '1.0'),
            'model' => $config['chat_model'],
            'label' => $classification->label->value,
            'confidence_band' => $classification->confidenceBand(),
            'excerpt' => $excerpt,
            'evidence' => $evidence,
        ];
        $key = 'explanation:'.sha1(json_encode(
            $cacheContext,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        if ($cached = Cache::get($key)) {
            return Explanation::ready(
                narrative: $cached['narrative'],
                model: $cached['model'],
                promptTokens: $cached['prompt_tokens'],
                completionTokens: $cached['completion_tokens'],
                estimatedCostUsd: 0,
                cached: true,
            );
        }

        if (blank($config['key'] ?? null)) {
            return Explanation::unavailable();
        }

        try {
            $this->breaker->check();
        } catch (AiServiceException $exception) {
            if ($this->isNonCriticalProviderFailure($exception)) {
                return Explanation::unavailable();
            }

            throw $exception;
        }

        try {
            $reservation = $this->quota->reserve('explanation');
        } catch (AiServiceException $exception) {
            if ($this->isNonCriticalProviderFailure($exception)) {
                return Explanation::unavailable();
            }

            throw $exception;
        }

        try {
            $response = Http::withToken($config['key'])
                ->connectTimeout($config['connect_timeout'])
                ->timeout($config['timeout'])
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $config['chat_model'],
                    'temperature' => 0.1,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => json_encode([
                            'classification' => [
                                'label' => $classification->label->value,
                                'confidence_score' => $classification->confidenceScore,
                                'model_version' => $classification->modelVersion,
                            ],
                            'excerpt' => $excerpt,
                            'evidence' => $evidence,
                        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->quota->release($reservation);
            $this->breaker->recordFailure();

            return Explanation::unavailable();
        } catch (\Throwable $exception) {
            $this->quota->release($reservation);
            throw $exception;
        }

        if ($response->failed()) {
            $this->quota->release($reservation);
            if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
                $this->breaker->recordFailure();

                return Explanation::unavailable();
            }

            throw AiServiceException::permanent(
                service: 'openai',
                message: "Permintaan explanation OpenAI ditolak ({$response->status()}).",
                statusCode: $response->status(),
                previous: $response->toException(),
            );
        }

        $payload = $response->json();
        $narrative = data_get($payload, 'choices.0.message.content');

        if (! is_string($narrative) || trim($narrative) === '') {
            $this->quota->release($reservation);
            $this->breaker->recordFailure();

            return Explanation::unavailable();
        }

        $this->breaker->recordSuccess();
        $usage = $payload['usage'] ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $usageAvailable = is_numeric($usage['prompt_tokens'] ?? null)
            || is_numeric($usage['completion_tokens'] ?? null);
        $cost = $usageAvailable
            ? $this->quota->estimateCost($config['chat_model'], $promptTokens, $completionTokens)
            : $reservation->reservedMicrousd / 1_000_000;
        try {
            $this->quota->finalize($reservation, $promptTokens, $completionTokens, $cost);
        } catch (\Throwable $exception) {
            $this->quota->release($reservation);
            throw $exception;
        }
        $result = Explanation::ready(
            narrative: trim($narrative),
            model: $config['chat_model'],
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $cost,
        );

        Cache::put($key, [
            'narrative' => $result->narrative,
            'model' => $result->model,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
        ], $config['cache_ttl']);

        return $result;
    }

    private function isNonCriticalProviderFailure(AiServiceException $exception): bool
    {
        return $exception->service === 'openai'
            && ($exception->retryable || $exception->statusCode === 429);
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @return list<array{
     *     document_id: string,
     *     title: string,
     *     source: string,
     *     source_url: string,
     *     published_at: string|null,
     *     snippet: string,
     *     similarity_score: float,
     *     rank: int,
     *     knowledge_base_version: string|null
     * }>
     */
    private function canonicalEvidence(array $evidence): array
    {
        $canonical = [];
        foreach ($evidence as $item) {
            if (! is_array($item)
                || ! is_string($item['document_id'] ?? null)
                || ! is_string($item['title'] ?? null)
                || ! is_string($item['source'] ?? null)
                || ! is_string($item['source_url'] ?? null)
                || (! is_string($item['published_at'] ?? null) && ($item['published_at'] ?? null) !== null)
                || ! is_string($item['snippet'] ?? null)
                || ! is_numeric($item['similarity_score'] ?? null)
                || ! is_numeric($item['rank'] ?? null)
                || (! is_string($item['knowledge_base_version'] ?? null) && ($item['knowledge_base_version'] ?? null) !== null)) {
                throw new InvalidArgumentException('Evidence payload does not match the persisted evidence contract.');
            }

            $canonical[] = [
                'document_id' => $item['document_id'],
                'title' => $item['title'],
                'source' => $item['source'],
                'source_url' => $item['source_url'],
                'published_at' => $item['published_at'],
                'snippet' => $item['snippet'],
                'similarity_score' => (float) $item['similarity_score'],
                'rank' => (int) $item['rank'],
                'knowledge_base_version' => $item['knowledge_base_version'],
            ];
        }

        usort($canonical, static fn (array $left, array $right): int => [
            $left['rank'],
            $left['document_id'],
        ] <=> [
            $right['rank'],
            $right['document_id'],
        ]);

        return $canonical;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Jelaskan hasil klasifikasi berita dalam Bahasa Indonesia secara netral, singkat, dan mudah dipahami.

Aturan wajib:
- Label classification adalah hasil tetap dari classifier; jangan mengubah atau melakukan re-classification, termasuk ketika evidence tampak bertentangan dengan label.
- Evidence hanya konteks pendukung explanation. IndoBERT tetap menjadi classifier dan evidence tidak boleh mengganti keputusannya.
- Gunakan hanya informasi yang terdapat pada classification, excerpt, dan evidence yang diberikan. Jangan melakukan pencarian web atau retrieval tambahan.
- Jangan menciptakan sumber, URL, judul sumber, tanggal publikasi, kutipan, fakta, atau daftar referensi yang tidak diberikan.
- Jangan membuat daftar referensi fiktif.
- Jangan mengarang fakta atau referensi untuk mengisi evidence kosong. Jika evidence kosong, nyatakan dengan jelas bahwa tidak tersedia evidence yang relevan pada basis pengetahuan saat ini.
- similarity_score hanya menunjukkan relevansi hasil retrieval. Itu bukan confidence classifier, ukuran kebenaran, atau bukti bahwa klaim benar atau salah.
- Keberadaan suatu sumber tidak otomatis membuktikan klaim benar atau salah.
- Jangan menyatakan bahwa sumber telah memverifikasi informasi kecuali hal itu secara eksplisit terdapat pada konteks yang diberikan.
PROMPT;
    }
}

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

class OpenAiExplainer implements Explainer
{
    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly OpenAiQuota $quota = new OpenAiQuota,
    ) {}

    public function explain(Classification $classification, string $excerpt): Explanation
    {
        $config = config('services.openai');
        $this->quota->ensureAvailable();
        $key = 'explanation:'.sha1(implode('|', [
            config('app.ai_prompt_version', '1.0'),
            $config['chat_model'],
            $classification->label->value,
            $classification->confidenceBand(),
            $excerpt,
        ]));

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

        try {
            $this->breaker->check();
            $response = Http::withToken($config['key'])
                ->connectTimeout($config['connect_timeout'])
                ->timeout($config['timeout'])
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $config['chat_model'],
                    'temperature' => 0.1,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Jelaskan hasil klasifikasi berita dalam Bahasa Indonesia secara singkat, netral, dan mudah dipahami. Jangan mengubah label model.'],
                        ['role' => 'user', 'content' => json_encode([
                            'label' => $classification->label->value,
                            'confidence_score' => $classification->confidenceScore,
                            'model_version' => $classification->modelVersion,
                            'excerpt' => $excerpt,
                        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->breaker->recordFailure();

            return Explanation::unavailable('Penjelasan AI sedang tidak tersedia. Hasil klasifikasi BERT tetap dapat digunakan.');
        } catch (\Throwable $exception) {
            if ($exception instanceof AiServiceException && $exception->retryable) {
                $this->breaker->recordFailure();
            }

            return Explanation::unavailable('Penjelasan AI sedang tidak tersedia. Hasil klasifikasi BERT tetap dapat digunakan.');
        }

        if ($response->failed()) {
            if ($response->serverError() || $response->status() === 429) {
                $this->breaker->recordFailure();
            }

            return Explanation::unavailable('Penjelasan AI sedang tidak tersedia. Hasil klasifikasi BERT tetap dapat digunakan.');
        }

        $this->breaker->recordSuccess();
        $payload = $response->json();
        $narrative = data_get($payload, 'choices.0.message.content');

        if (! is_string($narrative) || trim($narrative) === '') {
            return Explanation::unavailable('Penjelasan AI tidak menghasilkan narasi.');
        }

        $usage = $payload['usage'] ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $cost = $this->quota->estimateCost($config['chat_model'], $promptTokens, $completionTokens);
        $this->quota->recordUsage($promptTokens, $completionTokens, $cost);
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
}

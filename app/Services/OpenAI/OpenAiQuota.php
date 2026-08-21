<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Facades\Cache;

class OpenAiQuota
{
    public function ensureAvailable(): void
    {
        $config = config('services.openai');
        $limit = (int) ($config['rate_limit_per_minute'] ?? 60);
        $quotaUsd = (float) ($config['monthly_quota_usd'] ?? 25.0);

        // Rate limit: simple sliding window via cache
        $key = 'openai:rate:'.now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);
        if ($count >= $limit) {
            throw new \App\Exceptions\AiServiceException(
                message: 'Batas permintaan OpenAI per menit tercapai. Coba lagi nanti.',
                service: 'openai',
                retryable: true,
                statusCode: 429,
                retryAfterSeconds: 60,
            );
        }

        // Monthly quota: sum of estimated_cost_usd in current month from detection_results
        // For now, use cache counter for cost (more robust would query DB)
        $costKey = 'openai:cost:'.now()->format('Ym');
        $spent = (float) Cache::get($costKey, 0);
        if ($spent >= $quotaUsd) {
            throw new \App\Exceptions\AiServiceException(
                message: 'Kuota bulanan OpenAI telah tercapai.',
                service: 'openai',
                retryable: false,
                statusCode: 429,
            );
        }
    }

    public function recordUsage(int $promptTokens, int $completionTokens, float $costUsd): void
    {
        $config = config('services.openai');

        // Rate limit increment
        $rateKey = 'openai:rate:'.now()->format('YmdHi');
        Cache::increment($rateKey);
        Cache::put($rateKey, Cache::get($rateKey), 65);

        // Cost increment
        $costKey = 'openai:cost:'.now()->format('Ym');
        $current = (float) Cache::get($costKey, 0);
        Cache::put($costKey, $current + $costUsd, 32 * 24 * 3600);

        // Also log to Cache for cheap ledger (could be DB table in future)
        $ledgerKey = 'openai:ledger:'.now()->format('Ymd');
        $entry = [
            'at' => now()->toIso8601String(),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_usd' => $costUsd,
        ];
        $ledger = Cache::get($ledgerKey, []);
        $ledger[] = $entry;
        Cache::put($ledgerKey, array_slice($ledger, -100), 24 * 3600);
    }

    public function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Pricing as of 2024-2025 for gpt-4o-mini and whisper
        return match ($model) {
            'gpt-4o-mini', 'gpt-4o-mini-2024-07-18' => ($promptTokens * 0.15 + $completionTokens * 0.60) / 1_000_000,
            'whisper-1' => ($promptTokens + $completionTokens) * 0.006 / 60, // $0.006 per minute, approx 150 tokens per minute
            default => ($promptTokens * 0.15 + $completionTokens * 0.60) / 1_000_000,
        };
    }
}

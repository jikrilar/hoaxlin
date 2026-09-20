<?php

namespace App\Services\OpenAI;

use App\DataObjects\OpenAiQuotaReservation;
use App\Exceptions\AiServiceException;
use App\Services\Security\AtomicCounterLimiter;
use Throwable;

class OpenAiQuota
{
    public function __construct(
        private readonly AtomicCounterLimiter $limiter = new AtomicCounterLimiter,
        private readonly OpenAiUsageLedger $ledger = new OpenAiUsageLedger,
    ) {}

    public function reserve(string $operation): OpenAiQuotaReservation
    {
        $config = config('services.openai');
        $now = now(config('app.timezone'));
        $rateKey = 'openai:rate:'.$now->format('YmdHi');
        $rateLimit = $this->limiter->reserve(
            $rateKey,
            (int) ($config['rate_limit_per_minute'] ?? 60),
            $now->copy()->startOfMinute()->addMinute(),
        );

        if ($rateLimit === null) {
            throw AiServiceException::transient(
                'openai',
                'Batas permintaan OpenAI per menit tercapai. Coba lagi nanti.',
                429,
                max(1, $now->diffInSeconds($now->copy()->startOfMinute()->addMinute())),
            );
        }

        $reservationUsd = (float) data_get($config, "reservation_usd.{$operation}", 1.0);
        $budgetUsd = (float) ($config['monthly_quota_usd'] ?? 25.0);
        $reservationMicrousd = max(1, $this->toMicrousd($reservationUsd));

        try {
            $durable = $this->ledger->reserve(
                $operation,
                $reservationMicrousd,
                $this->toMicrousd($budgetUsd),
            );
        } catch (Throwable $exception) {
            $this->limiter->release($rateLimit);
            throw $exception;
        }

        return new OpenAiQuotaReservation(
            $durable['id'],
            $durable['period'],
            $operation,
            $durable['reserved_microusd'],
            $rateLimit,
        );
    }

    public function finalize(OpenAiQuotaReservation $reservation, int $inputTokens, int $outputTokens, float $costUsd): void
    {
        $this->ledger->finalize(
            $reservation->id,
            $inputTokens,
            $outputTokens,
            $this->toMicrousd($costUsd),
        );
    }

    public function release(?OpenAiQuotaReservation $reservation): void
    {
        if ($reservation !== null) {
            $this->ledger->release($reservation->id);
        }
    }

    public function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        return match ($model) {
            'gpt-4o-mini', 'gpt-4o-mini-2024-07-18' => ($promptTokens * 0.15 + $completionTokens * 0.60) / 1_000_000,
            'whisper-1' => ($promptTokens + $completionTokens) * 0.006 / 60,
            default => ($promptTokens * 0.15 + $completionTokens * 0.60) / 1_000_000,
        };
    }

    private function toMicrousd(float $usd): int
    {
        return max(0, (int) ceil($usd * 1_000_000));
    }
}

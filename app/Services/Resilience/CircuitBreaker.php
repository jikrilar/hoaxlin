<?php

namespace App\Services\Resilience;

use App\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function __construct(private readonly string $service) {}

    public function check(): void
    {
        if (Cache::has($this->openKey())) {
            throw AiServiceException::transient(
                service: $this->service,
                message: "Layanan {$this->service} sedang dalam mode pemulihan.",
            );
        }
    }

    public function recordFailure(): void
    {
        $config = config("services.{$this->service}.breaker", []);
        $key = "breaker:{$this->service}:failures";
        $failures = (int) Cache::increment($key);
        Cache::put($key, $failures, (int) ($config['window'] ?? 60));

        if ($failures >= (int) ($config['failures'] ?? 5)) {
            Cache::put($this->openKey(), true, (int) ($config['cooldown'] ?? 30));
        }
    }

    public function recordSuccess(): void
    {
        Cache::forget("breaker:{$this->service}:failures");
        Cache::forget($this->openKey());
    }

    private function openKey(): string
    {
        return "breaker:{$this->service}:open";
    }
}

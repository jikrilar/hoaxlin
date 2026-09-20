<?php

namespace App\DataObjects;

class OpenAiQuotaReservation
{
    public function __construct(
        public readonly string $id,
        public readonly string $period,
        public readonly string $operation,
        public readonly int $reservedMicrousd,
        public readonly AtomicLimitReservation $rateLimit,
    ) {}
}

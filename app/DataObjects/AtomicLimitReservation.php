<?php

namespace App\DataObjects;

class AtomicLimitReservation
{
    public function __construct(
        public readonly string $key,
        public readonly int $expiresAt,
    ) {}
}

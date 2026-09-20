<?php

namespace App\DataObjects;

class CaptchaReservation
{
    public function __construct(
        public readonly string $challengeId,
        public readonly string $reservationId,
    ) {}
}

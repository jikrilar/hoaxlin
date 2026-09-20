<?php

namespace App\DataObjects;

class SubmissionQuotaReservation
{
    /** @param list<AtomicLimitReservation> $limits */
    public function __construct(public readonly array $limits) {}
}

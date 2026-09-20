<?php

namespace App\Services\Security;

use App\DataObjects\AtomicLimitReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class AtomicCounterLimiter
{
    public function reserve(string $key, int $limit, CarbonInterface $expiresAt): ?AtomicLimitReservation
    {
        $limit = max(0, $limit);
        $ttl = max(1, now()->diffInSeconds($expiresAt, false));

        return Cache::lock($this->lockKey($key), (int) config('security.captcha.lock_seconds', 10))
            ->block((int) config('security.captcha.lock_wait_seconds', 5), function () use ($key, $limit, $expiresAt, $ttl): ?AtomicLimitReservation {
                Cache::add($key, 0, $expiresAt);
                $count = (int) Cache::increment($key);

                if ($count > $limit) {
                    Cache::decrement($key);

                    return null;
                }

                // Some stores lose expiry when increment initializes a missing key.
                // Re-apply it only for the first reservation.
                if ($count === 1) {
                    Cache::put($key, 1, $expiresAt);
                }

                return new AtomicLimitReservation($key, now()->addSeconds($ttl)->timestamp);
            });
    }

    public function release(?AtomicLimitReservation $reservation): void
    {
        if ($reservation === null) {
            return;
        }

        Cache::lock($this->lockKey($reservation->key), (int) config('security.captcha.lock_seconds', 10))
            ->block((int) config('security.captcha.lock_wait_seconds', 5), function () use ($reservation): void {
                $current = (int) Cache::get($reservation->key, 0);
                if ($current > 0) {
                    Cache::decrement($reservation->key);
                }
            });
    }

    public function attempts(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    private function lockKey(string $key): string
    {
        return 'atomic-limit-lock:'.hash('sha256', $key);
    }
}

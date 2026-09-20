<?php

namespace App\Services\Security;

use App\DataObjects\SubmissionQuotaReservation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class SubmissionQuota
{
    public function __construct(private readonly AtomicCounterLimiter $limiter) {}

    public function reserve(string $ip, ?Authenticatable $user): SubmissionQuotaReservation
    {
        $now = now(config('app.timezone'));
        $expiresAt = $now->copy()->endOfDay()->addSecond();
        $date = $now->format('Ymd');
        $reservations = [];

        $ipReservation = $this->limiter->reserve(
            $this->ipKey($ip, $date),
            (int) config('security.submission.ip_daily_limit', 30),
            $expiresAt,
        );
        if ($ipReservation === null) {
            throw ValidationException::withMessages([
                'input_type' => 'Batas harian untuk IP ini tercapai. Coba lagi besok.',
            ]);
        }
        $reservations[] = $ipReservation;

        if ($user !== null) {
            try {
                $userReservation = $this->limiter->reserve(
                    $this->accountKey((string) $user->getAuthIdentifier(), $date),
                    (int) config('security.submission.account_daily_limit', 100),
                    $expiresAt,
                );
            } catch (\Throwable $exception) {
                $this->limiter->release($ipReservation);
                throw $exception;
            }
            if ($userReservation === null) {
                $this->limiter->release($ipReservation);

                throw ValidationException::withMessages([
                    'input_type' => 'Batas harian akun tercapai. Coba lagi besok.',
                ]);
            }
            $reservations[] = $userReservation;
        }

        return new SubmissionQuotaReservation($reservations);
    }

    public function release(SubmissionQuotaReservation $reservation): void
    {
        foreach ($reservation->limits as $limit) {
            $this->limiter->release($limit);
        }
    }

    public function ipKey(string $ip, string $date): string
    {
        return 'submission-quota:ip:'.hash('sha256', $ip).':'.$date;
    }

    public function accountKey(string $id, string $date): string
    {
        return 'submission-quota:account:'.$id.':'.$date;
    }
}

<?php

namespace App\Services\Security;

use App\DataObjects\CaptchaReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CaptchaChallenge
{
    public const SESSION_KEY = 'submission_captcha';

    /** @return array{question: string, answer: int} */
    public function issue(Request $request): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $id = (string) Str::uuid();
        $ttl = max(60, (int) config('security.captcha.ttl_seconds', 600));
        $expiresAt = now()->addSeconds($ttl);

        Cache::put($this->key($id), [
            'state' => 'issued',
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);
        $request->session()->put(self::SESSION_KEY, [
            'id' => $id,
            'answer' => $left + $right,
            'expires_at' => $expiresAt->timestamp,
        ]);

        return ['question' => "{$left} + {$right} = ?", 'answer' => $left + $right];
    }

    public function validationError(Request $request, mixed $answer): ?string
    {
        $challenge = $request->session()->get(self::SESSION_KEY);
        if (! is_array($challenge)
            || ! is_string($challenge['id'] ?? null)
            || ! is_int($challenge['answer'] ?? null)
            || (int) ($challenge['expires_at'] ?? 0) <= now()->timestamp
            || Cache::get($this->key($challenge['id'])) === null) {
            return 'Challenge CAPTCHA tidak tersedia atau sudah kedaluwarsa. Muat ulang halaman.';
        }

        if ($answer === null || $answer === '') {
            return 'Jawaban CAPTCHA wajib diisi.';
        }

        if (! hash_equals((string) $challenge['answer'], (string) $answer)) {
            return 'Jawaban CAPTCHA salah.';
        }

        return null;
    }

    public function reserve(Request $request): ?CaptchaReservation
    {
        $challenge = $request->session()->get(self::SESSION_KEY);
        if (! is_array($challenge) || ! is_string($challenge['id'] ?? null)) {
            return null;
        }

        $challengeId = $challenge['id'];
        $reservationId = (string) Str::uuid();

        return Cache::lock($this->lockKey($challengeId), (int) config('security.captcha.lock_seconds', 10))
            ->block((int) config('security.captcha.lock_wait_seconds', 5), function () use ($challengeId, $reservationId): ?CaptchaReservation {
                $state = Cache::get($this->key($challengeId));
                if (! is_array($state) || ($state['state'] ?? null) !== 'issued') {
                    return null;
                }

                Cache::put($this->key($challengeId), [
                    'state' => 'reserved',
                    'reservation_id' => $reservationId,
                    'expires_at' => (int) ($state['expires_at'] ?? now()->addSeconds((int) config('security.captcha.ttl_seconds', 600))->timestamp),
                ], now()->addSeconds(max(5, (int) config('security.captcha.reservation_seconds', 30))));

                return new CaptchaReservation($challengeId, $reservationId);
            });
    }

    public function consume(Request $request, CaptchaReservation $reservation): void
    {
        Cache::lock($this->lockKey($reservation->challengeId), (int) config('security.captcha.lock_seconds', 10))
            ->block((int) config('security.captcha.lock_wait_seconds', 5), function () use ($reservation): void {
                $state = Cache::get($this->key($reservation->challengeId));
                if (is_array($state) && hash_equals((string) ($state['reservation_id'] ?? ''), $reservation->reservationId)) {
                    Cache::forget($this->key($reservation->challengeId));
                }
            });

        $request->session()->forget(self::SESSION_KEY);
    }

    public function release(CaptchaReservation $reservation): void
    {
        Cache::lock($this->lockKey($reservation->challengeId), (int) config('security.captcha.lock_seconds', 10))
            ->block((int) config('security.captcha.lock_wait_seconds', 5), function () use ($reservation): void {
                $state = Cache::get($this->key($reservation->challengeId));
                if (is_array($state) && hash_equals((string) ($state['reservation_id'] ?? ''), $reservation->reservationId)) {
                    $expiresAt = (int) ($state['expires_at'] ?? 0);
                    if ($expiresAt <= now()->timestamp) {
                        Cache::forget($this->key($reservation->challengeId));

                        return;
                    }

                    Cache::put($this->key($reservation->challengeId), [
                        'state' => 'issued',
                        'expires_at' => $expiresAt,
                    ], now()->setTimestamp($expiresAt));
                }
            });
    }

    private function key(string $id): string
    {
        return 'captcha:challenge:'.$id;
    }

    private function lockKey(string $id): string
    {
        return 'captcha:lock:'.$id;
    }
}

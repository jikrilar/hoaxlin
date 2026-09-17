<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Http\Request;
use LogicException;

class SubmissionAccess
{
    private const SESSION_KEY_PREFIX = 'guest_submission_capabilities.';

    public function generateGuestToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashGuestToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function rememberGuestCapability(Request $request, Submission $submission, string $token): void
    {
        if ($submission->user_id !== null
            || ! is_string($submission->guest_access_token_hash)
            || ! hash_equals($submission->guest_access_token_hash, $this->hashGuestToken($token))) {
            throw new LogicException('Guest capability does not match the submission.');
        }

        $request->session()->put(self::sessionKey($submission), $token);
    }

    public function authorize(Request $request, Submission $submission): void
    {
        if ($submission->user_id !== null) {
            $user = $request->user();

            if (! $user || ($user->getKey() !== $submission->user_id && ! $user->is_admin)) {
                abort(403, 'Akses ditolak. Hasil ini bukan milik Anda.');
            }

            return;
        }

        $token = $request->session()->get(self::sessionKey($submission));
        $storedHash = $submission->guest_access_token_hash;

        if (! is_string($token)
            || $token === ''
            || ! is_string($storedHash)
            || ! hash_equals($storedHash, $this->hashGuestToken($token))) {
            abort(404);
        }
    }

    public static function sessionKey(Submission|int $submission): string
    {
        $id = $submission instanceof Submission ? $submission->getKey() : $submission;

        return self::SESSION_KEY_PREFIX.$id;
    }
}

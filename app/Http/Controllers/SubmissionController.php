<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\CreateSubmission;
use App\Http\Requests\StoreSubmissionRequest;
use App\Services\Security\CaptchaChallenge;
use App\Services\Security\SubmissionQuota;
use App\Services\SubmissionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(
        StoreSubmissionRequest $request,
        CreateSubmission $action,
        SubmissionAccess $access,
        CaptchaChallenge $captcha,
        SubmissionQuota $quota,
    ): RedirectResponse {
        $captchaReservation = $captcha->reserve($request);
        if ($captchaReservation === null) {
            throw ValidationException::withMessages([
                'captcha_answer' => 'Challenge CAPTCHA sudah digunakan atau kedaluwarsa. Muat ulang halaman.',
            ]);
        }

        try {
            $quotaReservation = $quota->reserve($request->ip(), $request->user());
        } catch (\Throwable $exception) {
            $captcha->release($captchaReservation);
            throw $exception;
        }

        $guestToken = $request->user() === null ? $access->generateGuestToken() : null;

        try {
            $submission = $action->handle(
                $request->validated(),
                $request->user(),
                $request->file('media_file'),
                $guestToken === null ? null : $access->hashGuestToken($guestToken),
            );
        } catch (\Throwable $exception) {
            $quota->release($quotaReservation);
            $captcha->release($captchaReservation);
            throw $exception;
        }

        $captcha->consume($request, $captchaReservation);

        if ($guestToken !== null) {
            $access->rememberGuestCapability($request, $submission, $guestToken);
        }

        return redirect()->route('hasil', $submission);
    }
}

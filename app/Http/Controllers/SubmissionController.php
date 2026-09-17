<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\CreateSubmission;
use App\Http\Requests\StoreSubmissionRequest;
use App\Services\SubmissionAccess;
use Illuminate\Http\RedirectResponse;

class SubmissionController extends Controller
{
    public function store(
        StoreSubmissionRequest $request,
        CreateSubmission $action,
        SubmissionAccess $access,
    ): RedirectResponse {
        $guestToken = $request->user() === null ? $access->generateGuestToken() : null;

        $submission = $action->handle(
            $request->validated(),
            $request->user(),
            $request->file('media_file'),
            $guestToken === null ? null : $access->hashGuestToken($guestToken),
        );

        if ($guestToken !== null) {
            $access->rememberGuestCapability($request, $submission, $guestToken);
        }

        // Increment quotas (C17)
        cache()->increment('quota:ip:'.request()->ip().':'.now()->format('Ymd'));
        if ($request->user()) {
            cache()->increment('quota:user:'.$request->user()->getKey().':'.now()->format('Ymd'));
        }
        // Clear CAPTCHA after use
        session()->forget('captcha_answer');

        return redirect()->route('hasil', $submission);
    }
}

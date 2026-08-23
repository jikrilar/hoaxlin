<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\CreateSubmission;
use App\Http\Requests\StoreSubmissionRequest;
use Illuminate\Http\RedirectResponse;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, CreateSubmission $action): RedirectResponse
    {
        $submission = $action->handle(
            $request->validated(),
            $request->user(),
            $request->file('media_file'),
        );

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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request): RedirectResponse
    {
        $submission = Submission::query()
            ->whereKey($request->validated('submission_id'))
            ->forUser($request->user())
            ->whereHas('detectionResult')
            ->firstOrFail();

        if (Feedback::query()
            ->where('submission_id', $submission->getKey())
            ->where('user_id', $request->user()->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'submission_id' => 'Kamu sudah memberikan umpan balik untuk hasil ini.',
            ]);
        }

        Feedback::create([
            'submission_id' => $submission->getKey(),
            'user_id' => $request->user()->getKey(),
            'is_correct' => $request->validated('is_correct'),
            'comment' => $request->validated('comment'),
        ]);

        return back()->with('success', 'Umpan balik berhasil dikirim. Terima kasih telah membantu meningkatkan akurasi model!');
    }
}

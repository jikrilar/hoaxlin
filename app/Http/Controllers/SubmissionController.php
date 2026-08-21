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

        return redirect()->route('hasil', $submission);
    }
}

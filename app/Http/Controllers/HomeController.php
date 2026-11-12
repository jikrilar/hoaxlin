<?php

namespace App\Http\Controllers;

use App\Services\Media\TranscriptionMediaContract;
use App\Services\Security\CaptchaChallenge;
use App\Support\SubmissionFormOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        CaptchaChallenge $captcha,
        TranscriptionMediaContract $media,
    ): View {
        $challenge = $captcha->issue($request);

        return view('welcome', [
            'captchaQuestion' => $challenge['question'],
            'submissionOptions' => SubmissionFormOptions::from($media),
        ]);
    }
}

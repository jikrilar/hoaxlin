<?php

namespace App\Http\Controllers;

use App\Services\Security\CaptchaChallenge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, CaptchaChallenge $captcha): View
    {
        $challenge = $captcha->issue($request);

        return view('welcome', ['captchaQuestion' => $challenge['question']]);
    }
}

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
            ...$this->formState($request),
        ]);
    }

    /**
     * Which input panel is active for the first render.
     *
     * Priority: validated old input (so a failed submission returns to the same
     * panel) > explicit `?tab=` deep link > the guest default (text only).
     * Unknown values fall back instead of leaking arbitrary state into the view.
     *
     * @return array{activeTabKey: string, inputTabs: array<int, array{key: string, label: string}>}
     */
    private function formState(Request $request): array
    {
        $tabs = [
            ['key' => 'text', 'label' => 'Teks'],
            ['key' => 'url', 'label' => 'Tautan artikel'],
            ['key' => 'image', 'label' => 'Gambar'],
            ['key' => 'video', 'label' => 'Video'],
            ['key' => 'video-url', 'label' => 'Tautan video'],
        ];
        $keys = array_column($tabs, 'key');

        $oldType = $request->old('input_type');
        $oldType = is_string($oldType) ? $oldType : '';
        $key = match ($oldType) {
            'image' => 'image',
            'video' => 'video',
            'video_url' => 'video-url',
            'url' => 'url',
            'text' => 'text',
            default => null,
        };

        if ($key === null) {
            $tab = $request->query('tab');
            $tab = is_string($tab) ? $tab : '';
            $key = match ($tab) {
                'gambar', 'image' => 'image',
                'video' => 'video',
                'video-url', 'video_url' => 'video-url',
                'url', 'tautan' => 'url',
                default => 'text',
            };
        }

        if ($request->user() === null) {
            $key = 'text';
        }

        return [
            'activeTabKey' => in_array($key, $keys, true) ? $key : 'text',
            'inputTabs' => $tabs,
        ];
    }
}

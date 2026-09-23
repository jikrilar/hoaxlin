<?php

namespace App\Http\Requests;

use App\Services\Media\MediaDurationProbe;
use App\Services\Media\TranscriptionMediaContract;
use App\Services\Security\CaptchaChallenge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null || $this->input('input_type') === 'text';
    }

    public function rules(): array
    {
        $media = app(TranscriptionMediaContract::class);

        return match ($this->input('input_type')) {
            'text' => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
                'raw_input' => ['required', 'string', 'min:50', 'max:50000'],
            ],
            'image' => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
                'media_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
            ],
            'video' => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
                'media_file' => [
                    'required',
                    'file',
                    'extensions:'.implode(',', $media->uploadExtensions()),
                    'mimetypes:'.implode(',', $media->uploadMimeTypes()),
                    'max:'.$media->maxKilobytes(),
                ],
            ],
            'video_url' => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
                'source_url' => ['required', 'url:http,https', 'max:2048'],
            ],
            'url' => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
                'source_url' => ['required', 'url:http,https', 'max:2048'],
            ],
            default => [
                'input_type' => ['required', Rule::in(['text', 'image', 'video', 'video_url', 'url'])],
            ],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $media = app(TranscriptionMediaContract::class);
            $captcha = app(CaptchaChallenge::class);

            // Honeypot (C17)
            if (filled($this->input('website'))) {
                $validator->errors()->add('website', 'Spam terdeteksi.');
            }

            // CAPTCHA is issued by HomeController and must be validated server-side.
            if ($message = $captcha->validationError($this, $this->input('captcha_answer'))) {
                $validator->errors()->add('captcha_answer', $message);
            }

            if ($this->input('input_type') === 'video_url' && filled($this->input('source_url'))) {
                if ($message = $media->directUrlError((string) $this->input('source_url'))) {
                    $validator->errors()->add('source_url', $message);
                }
            }

            $file = $this->file('media_file');
            if (! $file) {
                return;
            }

            // Decoded size guard: prevent decompression bombs (C12)
            if ($this->input('input_type') === 'image') {
                $info = @getimagesize($file->getRealPath());
                if ($info && ($info[0] * $info[1] > 64_000_000)) { // ~64MP
                    $validator->errors()->add('media_file', 'Resolusi gambar terlalu besar.');
                }
            }

            if ($this->input('input_type') === 'video') {
                if ($file->getSize() > $media->maxBytes() && ! $validator->errors()->has('media_file')) {
                    $validator->errors()->add('media_file', $this->videoSizeMessage($media));
                }

                $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
                if (! $media->isUploadCompatible($file->getClientOriginalName(), $mimeType)) {
                    $validator->errors()->add('media_file', 'Extension dan MIME media tidak kompatibel.');
                }

                $duration = app(MediaDurationProbe::class)->probePath($file->getRealPath());
                if ($duration !== null && $duration > $media->maxDurationSeconds()) {
                    $validator->errors()->add('media_file', 'Durasi media melebihi batas transkripsi.');
                }
            }
        });
    }

    public function messages(): array
    {
        $media = app(TranscriptionMediaContract::class);

        return [
            'input_type.required' => 'Jenis input wajib dipilih.',
            'input_type.in' => 'Jenis input tidak didukung.',
            'raw_input.required' => 'Teks berita wajib diisi.',
            'raw_input.string' => 'Teks berita harus berupa teks.',
            'raw_input.min' => 'Teks minimal 50 karakter.',
            'raw_input.max' => 'Teks maksimal 50.000 karakter.',
            'media_file.required' => 'File wajib diunggah.',
            'media_file.file' => 'Unggah file yang valid.',
            'media_file.mimes' => 'Format file tidak didukung.',
            'media_file.mimetypes' => 'MIME file tidak didukung.',
            'media_file.extensions' => 'Extension file tidak didukung.',
            'media_file.max' => $this->input('input_type') === 'video'
                ? $this->videoSizeMessage($media)
                : 'Ukuran file melebihi batas yang diizinkan.',
            'media_file.dimensions' => 'Dimensi gambar melebihi batas yang diizinkan.',
            'source_url.required' => 'Tautan URL wajib diisi.',
            'source_url.url' => 'URL harus valid dan menggunakan protokol HTTP atau HTTPS.',
            'source_url.max' => 'Tautan URL terlalu panjang.',
        ];
    }

    private function videoSizeMessage(TranscriptionMediaContract $media): string
    {
        return 'Ukuran video melebihi batas '.$media->maxSizeLabel().'.';
    }
}

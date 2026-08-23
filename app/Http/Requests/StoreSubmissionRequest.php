<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
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
                'media_file' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:204800'],
            ],
            'video_url', 'url' => [
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
            // Honeypot (C17)
            if (filled($this->input('website'))) {
                $validator->errors()->add('website', 'Spam terdeteksi.');
            }

            // Simple math CAPTCHA (C17) — session key set in welcome.blade.php
            $expected = session('captcha_answer');
            $given = $this->input('captcha_answer');
            if ($expected !== null && (string) $given !== (string) $expected) {
                $validator->errors()->add('captcha_answer', 'Jawaban CAPTCHA salah.');
            }

            // Per-IP quota: 30 per day (C17)
            $ipKey = 'quota:ip:'.request()->ip().':'.now()->format('Ymd');
            if ((int) cache()->get($ipKey, 0) >= 30) {
                $validator->errors()->add('input_type', 'Batas harian untuk IP ini tercapai (30/hari). Coba lagi besok.');
            }

            // Per-account quota: 100 per day
            if ($this->user()) {
                $userKey = 'quota:user:'.$this->user()->getKey().':'.now()->format('Ymd');
                if ((int) cache()->get($userKey, 0) >= 100) {
                    $validator->errors()->add('input_type', 'Batas harian akun tercapai (100/hari).');
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

            // Video duration guard: use file size as proxy if ffprobe not available
            if ($this->input('input_type') === 'video') {
                if ($file->getSize() > 200 * 1024 * 1024) {
                    $validator->errors()->add('media_file', 'File video terlalu besar.');
                }
                // If ffprobe is available, check duration (max 5 minutes)
                $duration = $this->probeVideoDuration($file->getRealPath());
                if ($duration !== null && $duration > 300) {
                    $validator->errors()->add('media_file', 'Durasi video maksimal 5 menit.');
                }
            }
        });
    }

    private function probeVideoDuration(string $path): ?float
    {
        $ffprobe = trim((string) shell_exec('which ffprobe 2>/dev/null'));
        if ($ffprobe === '' || ! is_file($path)) {
            return null;
        }

        $cmd = escapeshellcmd($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($path).' 2>/dev/null';
        $output = trim((string) shell_exec($cmd));

        return is_numeric($output) ? (float) $output : null;
    }

    public function messages(): array
    {
        return [
            'input_type.in' => 'Jenis input tidak didukung.',
            'raw_input.required' => 'Teks berita wajib diisi.',
            'raw_input.min' => 'Teks minimal 50 karakter.',
            'raw_input.max' => 'Teks maksimal 50.000 karakter.',
            'media_file.required' => 'File wajib diunggah.',
            'media_file.mimes' => 'Format file tidak didukung.',
            'media_file.max' => 'Ukuran file melebihi batas yang diizinkan.',
            'source_url.required' => 'Tautan URL wajib diisi.',
            'source_url.url' => 'URL harus valid dan menggunakan protokol HTTP atau HTTPS.',
        ];
    }
}

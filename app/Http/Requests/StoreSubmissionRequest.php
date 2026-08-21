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
                'media_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
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

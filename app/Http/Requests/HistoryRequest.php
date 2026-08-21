<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'label' => ['nullable', Rule::in(['valid', 'hoax', 'meragukan'])],
            'input_type' => ['nullable', Rule::in(['text', 'image', 'video', 'url'])],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'completed', 'failed'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'confidence'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'sort' => $this->input('sort', 'newest'),
        ]);
    }
}

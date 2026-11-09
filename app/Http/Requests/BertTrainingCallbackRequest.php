<?php

namespace App\Http\Requests;

use App\Models\ModelVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BertTrainingCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'version_id' => ['required', 'integer', 'min:1'],
            'status' => [
                'required',
                'string',
                Rule::in([ModelVersion::STATUS_COMPLETED, ModelVersion::STATUS_FAILED]),
            ],
            'accuracy' => [
                'required_if:status,'.ModelVersion::STATUS_COMPLETED,
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'f1_score' => [
                'required_if:status,'.ModelVersion::STATUS_COMPLETED,
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'baseline_accuracy' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'baseline_f1_score' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'training_duration' => [
                'required_if:status,'.ModelVersion::STATUS_COMPLETED,
                'nullable',
                'integer',
                'min:0',
            ],
            'error_message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

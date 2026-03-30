<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReportPlatformSectionAiCommentsRequest extends FormRequest
{
    private const FIELDS = [
        'ai_summary',
        'ai_highlights',
        'ai_concerns',
        'ai_suggested_action',
        'performance_flags',
        'top_performing_ads',
        'worst_performing_ads',
        'human_notes',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ai_summary' => ['sometimes', 'nullable', 'string'],
            'ai_highlights' => ['sometimes', 'nullable', 'array'],
            'ai_concerns' => ['sometimes', 'nullable', 'array'],
            'ai_suggested_action' => ['sometimes', 'nullable', 'string'],
            'performance_flags' => ['sometimes', 'nullable', 'array'],
            'top_performing_ads' => ['sometimes', 'nullable', 'array'],
            'worst_performing_ads' => ['sometimes', 'nullable', 'array'],
            'human_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasAtLeastOneUpdatableField()) {
                return;
            }

            $validator->errors()->add(
                'payload',
                'At least one AI comment field must be provided.'
            );
        });
    }

    private function hasAtLeastOneUpdatableField(): bool
    {
        foreach (self::FIELDS as $field) {
            if ($this->exists($field)) {
                return true;
            }
        }

        return false;
    }
}
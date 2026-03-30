<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportPlatformSectionAiCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ai_summary' => ['required', 'string'],
            'ai_highlights' => ['required', 'array'],
            'ai_concerns' => ['required', 'array'],
            'ai_suggested_action' => ['required', 'string'],
            'performance_flags' => ['required', 'array'],
            'top_performing_ads' => ['required', 'array'],
            'worst_performing_ads' => ['required', 'array'],
            'human_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
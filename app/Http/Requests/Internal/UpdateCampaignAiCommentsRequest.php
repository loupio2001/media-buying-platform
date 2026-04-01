<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignAiCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ai_commentary_summary' => ['required', 'string'],
            'ai_commentary_highlights' => ['nullable', 'array'],
            'ai_commentary_concerns' => ['nullable', 'array'],
            'ai_commentary_suggested_action' => ['nullable', 'string'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
        ];
    }
}

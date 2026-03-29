<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ad_set_id' => 'required|exists:ad_sets,id',
            'external_id' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'format' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,paused,deleted,archived',
            'headline' => 'nullable|string',
            'body' => 'nullable|string',
            'cta' => 'nullable|string|max:50',
            'destination_url' => 'nullable|string|max:500',
            'creative_url' => 'nullable|string|max:500',
            'is_tracked' => 'nullable|boolean',
        ];
    }
}

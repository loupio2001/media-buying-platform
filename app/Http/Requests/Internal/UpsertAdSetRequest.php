<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAdSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_platform_id' => 'required|exists:campaign_platforms,id',
            'external_id' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,paused,deleted,archived',
            'objective' => 'nullable|string|max:100',
            'targeting_summary' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'bid_strategy' => 'nullable|string|max:100',
            'budget_type' => 'nullable|in:lifetime,daily',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_tracked' => 'nullable|boolean',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignPlatformUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaignPlatformId = $this->route('campaign_platform')?->id ?? $this->route('campaign_platform');

        return [
            'campaign_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:campaigns,id'],
            'platform_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'exists:platforms,id',
                Rule::unique('campaign_platforms', 'platform_id')
                    ->where(fn ($query) => $query->where('campaign_id', $this->input('campaign_id')))
                    ->ignore($campaignPlatformId),
            ],
            'platform_connection_id' => ['nullable', 'exists:platform_connections,id'],
            'external_campaign_id' => ['nullable', 'string', 'max:100'],
            'budget' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'budget_type' => ['sometimes', Rule::in(['lifetime', 'daily'])],
            'currency' => ['sometimes', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'last_sync_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

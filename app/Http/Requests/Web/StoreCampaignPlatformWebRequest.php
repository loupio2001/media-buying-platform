<?php

namespace App\Http\Requests\Web;

use App\Models\PlatformConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignPlatformWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaignId = $this->route('campaign')?->id;

        return [
            'platform_id' => [
                'required',
                'integer',
                'exists:platforms,id',
                Rule::unique('campaign_platforms', 'platform_id')
                    ->where(fn ($query) => $query->where('campaign_id', $campaignId)),
            ],
            'platform_connection_id' => ['nullable', 'integer', 'exists:platform_connections,id'],
            'external_campaign_id' => ['required', 'string', 'max:100'],
            'budget' => ['required', 'numeric', 'min:0'],
            'budget_type' => ['required', Rule::in(['lifetime', 'daily'])],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $platformId = $this->integer('platform_id');
            $connectionId = $this->input('platform_connection_id');

            if ($connectionId === null || $connectionId === '') {
                return;
            }

            $connection = PlatformConnection::query()->find($connectionId);

            if ($connection === null || (int) $connection->platform_id !== $platformId) {
                $validator->errors()->add('platform_connection_id', 'Selected connection does not belong to the selected platform.');
            }
        });
    }
}

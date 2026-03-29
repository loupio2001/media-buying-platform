<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $platformId = $this->route('platform')?->id ?? $this->route('platform');

        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:50'],
            'slug' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:50',
                Rule::unique('platforms', 'slug')->ignore($platformId),
            ],
            'icon_url' => ['nullable', 'string', 'max:255'],
            'api_supported' => ['sometimes', 'boolean'],
            'supports_reach' => ['sometimes', 'boolean'],
            'supports_video_metrics' => ['sometimes', 'boolean'],
            'supports_frequency' => ['sometimes', 'boolean'],
            'supports_leads' => ['sometimes', 'boolean'],
            'default_metrics' => ['nullable', 'array'],
            'rate_limit_config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}

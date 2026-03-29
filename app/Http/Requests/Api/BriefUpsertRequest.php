<?php

namespace App\Http\Requests\Api;

use App\Enums\BriefStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BriefUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $briefId = $this->route('brief')?->id ?? $this->route('brief');

        return [
            'campaign_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'exists:campaigns,id',
                Rule::unique('briefs', 'campaign_id')->ignore($briefId),
            ],
            'objective' => ['nullable', 'string', 'max:200'],
            'kpis_requested' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'string'],
            'geo_targeting' => ['nullable', 'array'],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'channels_requested' => ['nullable', 'array'],
            'channels_recommended' => ['nullable', 'array'],
            'creative_formats' => ['nullable', 'array'],
            'flight_start' => ['nullable', 'date'],
            'flight_end' => ['nullable', 'date', 'after_or_equal:flight_start'],
            'constraints' => ['nullable', 'string'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'ai_brief_quality_score' => ['nullable', 'integer', 'between:0,100'],
            'ai_missing_info' => ['nullable', 'array'],
            'ai_kpi_challenges' => ['nullable', 'array'],
            'ai_questions_for_client' => ['nullable', 'array'],
            'ai_channel_rationale' => ['nullable', 'string'],
            'ai_budget_split' => ['nullable', 'array'],
            'ai_media_plan_draft' => ['nullable', 'array'],
            'status' => ['sometimes', new Enum(BriefStatus::class)],
            'reviewed_by' => ['nullable', 'exists:users,id'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }
}

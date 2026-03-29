<?php

namespace App\Http\Requests\Api;

use App\Enums\CampaignObjective;
use App\Enums\CampaignStatus;
use App\Enums\PacingStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CampaignUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:clients,id'],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:200'],
            'status' => ['sometimes', new Enum(CampaignStatus::class)],
            'objective' => [$this->isMethod('post') ? 'required' : 'sometimes', new Enum(CampaignObjective::class)],
            'start_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'end_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date', 'after_or_equal:start_date'],
            'total_budget' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'kpi_targets' => ['nullable', 'array'],
            'pacing_strategy' => ['sometimes', new Enum(PacingStrategy::class)],
            'sheet_id' => ['nullable', 'string', 'max:100'],
            'sheet_url' => ['nullable', 'string', 'max:255'],
            'brief_raw' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}

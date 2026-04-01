<?php

namespace App\Http\Requests\Web;

use App\Enums\CampaignObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCampaignWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:200'],
            'objective' => ['required', new Enum(CampaignObjective::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'kpi_targets' => ['nullable', 'array'],
            'sheet_id' => ['nullable', 'string', 'max:100'],
            'sheet_url' => ['nullable', 'string', 'max:255'],
            'brief_raw' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}

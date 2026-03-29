<?php

namespace App\Http\Requests\Api;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ReportUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:campaigns,id'],
            'type' => [$this->isMethod('post') ? 'required' : 'sometimes', new Enum(ReportType::class)],
            'period_start' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'period_end' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date', 'after_or_equal:period_start'],
            'title' => ['nullable', 'string', 'max:200'],
            'executive_summary' => ['nullable', 'string'],
            'overall_performance' => ['nullable', Rule::in(['on_track', 'underperforming', 'overperforming'])],
            'ai_recommendations' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'reviewed', 'exported'])],
            'version' => ['sometimes', 'integer', 'min:1'],
            'exported_file_path' => ['nullable', 'string', 'max:500'],
            'exported_at' => ['nullable', 'date'],
            'export_format' => ['nullable', Rule::in(['pptx', 'pdf', 'both'])],
            'reviewed_by' => ['nullable', 'exists:users,id'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }
}

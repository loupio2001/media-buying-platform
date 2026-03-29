<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class StoreSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ad_id' => 'required|exists:ads,id',
            'snapshot_date' => 'required|date',
            'granularity' => 'required|in:daily,cumulative',
            'impressions' => 'nullable|integer|min:0',
            'clicks' => 'nullable|integer|min:0',
            'spend' => 'nullable|numeric|min:0',
            'source' => 'nullable|in:api,manual',
        ];
    }
}

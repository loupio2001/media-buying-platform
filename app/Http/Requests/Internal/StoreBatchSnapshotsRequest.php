<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchSnapshotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'snapshots' => 'required|array|min:1|max:500',
            'snapshots.*.ad_id' => 'required|exists:ads,id',
            'snapshots.*.snapshot_date' => 'required|date',
            'snapshots.*.granularity' => 'required|in:daily,cumulative',
            'snapshots.*.source' => 'nullable|in:api,manual',
        ];
    }
}

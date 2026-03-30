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
        return array_merge([
            'snapshots' => 'required|array|min:1|max:500',
        ], StoreSnapshotRequest::snapshotRules('snapshots.*.'));
    }
}

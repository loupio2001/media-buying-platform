<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSyncStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'success' => 'required|boolean',
            'error_msg' => 'nullable|string',
        ];
    }
}

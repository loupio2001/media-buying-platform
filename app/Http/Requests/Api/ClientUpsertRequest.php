<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'category_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:categories,id'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'primary_contact' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'agency_lead' => ['nullable', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'billing_type' => ['sometimes', Rule::in(['retainer', 'project', 'performance'])],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformConnectionWebUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $platformConnectionId = $this->route('platformConnection')?->id;
        $currentPlatformId = $this->route('platformConnection')?->platform_id;

        return [
            'platform_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:platforms,id'],
            'account_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('platform_connections', 'account_id')
                    ->where(fn ($query) => $query->where('platform_id', $this->input('platform_id', $currentPlatformId)))
                    ->ignore($platformConnectionId),
            ],
            'account_name' => ['nullable', 'string', 'max:150'],
            'auth_type' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::in(['oauth2', 'api_key', 'service_account'])],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'api_key' => ['nullable', 'string'],
            'extra_credentials' => ['nullable', 'array'],
            'scopes' => ['nullable', 'array'],
            'is_connected' => ['sometimes', 'boolean'],
            'last_error' => ['nullable', 'string'],
            'error_count' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $platformConnection = $this->route('platformConnection');
            $authType = $this->input('auth_type', $platformConnection?->auth_type);

            if ($authType === null) {
                return;
            }

            $requiresCredentialCheck = $this->isMethod('post') || $this->has('auth_type');
            if (! $requiresCredentialCheck) {
                return;
            }

            if ($authType === 'oauth2') {
                $hasAccessToken = $this->filled('access_token') || (! $this->has('access_token') && ! empty($platformConnection?->access_token));
                if (! $hasAccessToken) {
                    $validator->errors()->add('access_token', 'The access_token field is required when auth_type is oauth2.');
                }
            }

            if ($authType === 'api_key') {
                $hasApiKey = $this->filled('api_key') || (! $this->has('api_key') && ! empty($platformConnection?->api_key));
                if (! $hasApiKey) {
                    $validator->errors()->add('api_key', 'The api_key field is required when auth_type is api_key.');
                }
            }

            if ($authType === 'service_account') {
                $hasExtraCredentials =
                    $this->filled('extra_credentials')
                    || (! $this->has('extra_credentials') && ! empty($platformConnection?->extra_credentials));

                if (! $hasExtraCredentials) {
                    $validator->errors()->add('extra_credentials', 'The extra_credentials field is required when auth_type is service_account.');
                }
            }
        });
    }
}
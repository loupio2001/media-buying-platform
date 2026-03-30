<?php

namespace App\Services\PlatformOAuth;

use App\Models\PlatformConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaOAuthService
{
    private const AUTHORIZE_URL = 'https://www.facebook.com/v22.0/dialog/oauth';
    private const TOKEN_URL = 'https://graph.facebook.com/v22.0/oauth/access_token';
    private const AD_ACCOUNTS_URL = 'https://graph.facebook.com/v22.0/me/adaccounts';

    public function buildAuthorizeUrl(string $state, string $redirectUri): string
    {
        $clientId = (string) config('services.meta_ads.client_id');
        if ($clientId === '') {
            throw new RuntimeException('META_APP_ID is not configured.');
        }

        $scopes = config('services.meta_ads.scopes', ['ads_read', 'ads_management']);
        if (! is_array($scopes) || $scopes === []) {
            $scopes = ['ads_read', 'ads_management'];
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    /**
     * @return array{access_token:string,token_type?:string,expires_in?:int,refresh_token?:string}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $clientId = (string) config('services.meta_ads.client_id');
        $clientSecret = (string) config('services.meta_ads.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('META_APP_ID or META_APP_SECRET is not configured.');
        }

        $response = Http::retry(2, 500)
            ->get(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Meta token exchange failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) Arr::get($payload, 'access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Meta token exchange did not return access_token.');
        }

        return [
            'access_token' => $accessToken,
            'token_type' => Arr::get($payload, 'token_type'),
            'expires_in' => Arr::get($payload, 'expires_in'),
            'refresh_token' => Arr::get($payload, 'refresh_token'),
        ];
    }

    /**
     * @return array{account_id:string,account_name:string}
     */
    public function fetchPrimaryAdAccount(string $accessToken): array
    {
        $response = Http::retry(2, 500)
            ->get(self::AD_ACCOUNTS_URL, [
                'fields' => 'account_id,name',
                'limit' => 1,
                'access_token' => $accessToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Meta ad account lookup failed: ' . $response->body());
        }

        $rows = Arr::get($response->json(), 'data', []);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('No Meta ad account found for this user.');
        }

        $first = is_array($rows[0] ?? null) ? $rows[0] : [];
        $accountId = (string) Arr::get($first, 'account_id', '');
        $accountName = (string) Arr::get($first, 'name', 'Meta Ad Account');

        if ($accountId === '') {
            throw new RuntimeException('Meta ad account response missing account_id.');
        }

        $accountId = str_starts_with($accountId, 'act_') ? substr($accountId, 4) : $accountId;

        return [
            'account_id' => $accountId,
            'account_name' => $accountName,
        ];
    }

    public function upsertConnection(
        int $platformId,
        int $userId,
        array $tokenPayload,
        array $accountPayload,
    ): PlatformConnection {
        $connection = PlatformConnection::query()->firstOrNew([
            'platform_id' => $platformId,
            'account_id' => (string) $accountPayload['account_id'],
        ]);

        if (! $connection->exists) {
            $connection->created_by = $userId;
        }

        $connection->account_name = (string) $accountPayload['account_name'];
        $connection->auth_type = 'oauth2';
        $connection->access_token = (string) $tokenPayload['access_token'];
        $connection->refresh_token = $tokenPayload['refresh_token'] ?? null;
        $connection->token_expires_at = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : null;
        $connection->scopes = config('services.meta_ads.scopes', ['ads_read', 'ads_management']);
        $connection->is_connected = true;
        $connection->error_count = 0;
        $connection->last_error = null;
        $connection->save();

        return $connection->refresh();
    }
}
<?php

namespace App\Services\PlatformOAuth;

use App\Models\PlatformConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TikTokOAuthService
{
    private const AUTHORIZE_URL = 'https://business-api.tiktok.com/portal/auth';
    private const TOKEN_URL = 'https://business-api.tiktok.com/open_api/v1.3/oauth2/access_token/';
    private const ADVERTISERS_URL = 'https://business-api.tiktok.com/open_api/v1.3/oauth2/advertiser/get/';

    public function buildAuthorizeUrl(string $state, string $redirectUri): string
    {
        $appId = (string) config('services.tiktok_ads.client_id');
        if ($appId === '') {
            throw new RuntimeException('TIKTOK_APP_ID is not configured.');
        }

        $query = http_build_query([
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    /**
     * @return array{access_token:string,token_type?:string,expires_in?:int,refresh_token?:string}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $appId = (string) config('services.tiktok_ads.client_id');
        $appSecret = (string) config('services.tiktok_ads.client_secret');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('TIKTOK_APP_ID or TIKTOK_APP_SECRET is not configured.');
        }

        $response = Http::retry(2, 500)
            ->post(self::TOKEN_URL, [
                'app_id' => $appId,
                'auth_code' => $code,
                'secret' => $appSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('TikTok token exchange failed: ' . $response->body());
        }

        $payload = $response->json();
        // TikTok wraps response under data.*
        $data = Arr::get($payload, 'data', []);
        $accessToken = (string) Arr::get($data, 'access_token', '');

        if ($accessToken === '') {
            throw new RuntimeException('TikTok token exchange did not return access_token.');
        }

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => Arr::get($data, 'expires_in'),
            'refresh_token' => Arr::get($data, 'refresh_token'),
        ];
    }

    /**
     * @return array{account_id:string,account_name:string}
     */
    public function fetchPrimaryAdAccount(string $accessToken): array
    {
        $appId = (string) config('services.tiktok_ads.client_id');
        $appSecret = (string) config('services.tiktok_ads.client_secret');

        $response = Http::retry(2, 500)
            ->withHeaders(['Access-Token' => $accessToken])
            ->get(self::ADVERTISERS_URL, [
                'app_id' => $appId,
                'secret' => $appSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('TikTok advertiser list failed: ' . $response->body());
        }

        $list = Arr::get($response->json(), 'data.list', []);
        if (! is_array($list) || $list === []) {
            throw new RuntimeException('No TikTok advertiser found for this account.');
        }

        $first = is_array($list[0] ?? null) ? $list[0] : [];
        $accountId = (string) Arr::get($first, 'advertiser_id', '');
        $accountName = (string) Arr::get($first, 'advertiser_name', 'TikTok Ad Account');

        if ($accountId === '') {
            throw new RuntimeException('TikTok advertiser response missing advertiser_id.');
        }

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
        $connection->scopes = config('services.tiktok_ads.scopes', ['ADVERTISER_READ']);
        $connection->is_connected = true;
        $connection->error_count = 0;
        $connection->last_error = null;
        $connection->save();

        return $connection->refresh();
    }
}

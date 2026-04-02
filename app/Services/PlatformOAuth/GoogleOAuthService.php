<?php

namespace App\Services\PlatformOAuth;

use App\Models\PlatformConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuthService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CUSTOMERS_URL = 'https://googleads.googleapis.com/v23/customers:listAccessibleCustomers';

    public function buildAuthorizeUrl(string $state, string $redirectUri): string
    {
        $clientId = (string) config('services.google_ads.client_id');
        if ($clientId === '') {
            throw new RuntimeException('GOOGLE_ADS_CLIENT_ID is not configured.');
        }

        $scopes = config('services.google_ads.scopes', [
            'https://www.googleapis.com/auth/adwords',
        ]);
        if (! is_array($scopes) || $scopes === []) {
            $scopes = ['https://www.googleapis.com/auth/adwords'];
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    /**
     * @return array{access_token:string,token_type?:string,expires_in?:int,refresh_token?:string}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('GOOGLE_ADS_CLIENT_ID or GOOGLE_ADS_CLIENT_SECRET is not configured.');
        }

        $response = Http::retry(2, 500)
            ->timeout(15)
            ->connectTimeout(5)
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google token exchange failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) Arr::get($payload, 'access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Google token exchange did not return access_token.');
        }

        return [
            'access_token' => $accessToken,
            'token_type' => Arr::get($payload, 'token_type'),
            'expires_in' => Arr::get($payload, 'expires_in'),
            'refresh_token' => Arr::get($payload, 'refresh_token'),
        ];
    }

    /**
     * @return array<int, array{account_id:string,account_name:string}>
     */
    public function listAccessibleAdAccounts(string $accessToken): array
    {
        $developerToken = (string) config('services.google_ads.developer_token', '');

        $headers = ['Authorization' => 'Bearer ' . $accessToken];
        if ($developerToken !== '') {
            $headers['developer-token'] = $developerToken;
        }

        $response = Http::retry(2, 500)
            ->timeout(15)
            ->connectTimeout(5)
            ->withHeaders($headers)
            ->get(self::CUSTOMERS_URL);

        if (! $response->successful()) {
            throw new RuntimeException('Google Ads customer list failed: ' . $response->body());
        }

        $resourceNames = Arr::get($response->json(), 'resourceNames', []);
        if (! is_array($resourceNames) || $resourceNames === []) {
            throw new RuntimeException('No Google Ads customer found for this account.');
        }

        $accounts = [];

        foreach ($resourceNames as $resourceName) {
            if (! is_string($resourceName)) {
                continue;
            }

            // resourceNames look like "customers/1234567890"
            $accountId = str_replace('customers/', '', $resourceName);

            if ($accountId === '' || $accountId === $resourceName) {
                continue;
            }

            $accounts[] = [
                'account_id' => $accountId,
                'account_name' => 'Google Ads Account ' . $accountId,
            ];
        }

        if ($accounts === []) {
            throw new RuntimeException('Google Ads response missing valid customer resource names.');
        }

        return $accounts;
    }

    /**
     * @return array{account_id:string,account_name:string}
     */
    public function fetchPrimaryAdAccount(string $accessToken): array
    {
        return $this->listAccessibleAdAccounts($accessToken)[0];
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
        $newRefreshToken = (string) ($tokenPayload['refresh_token'] ?? '');
        if ($newRefreshToken !== '') {
            $connection->refresh_token = $newRefreshToken;
        }
        $connection->token_expires_at = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : null;
        $connection->scopes = config('services.google_ads.scopes', ['https://www.googleapis.com/auth/adwords']);
        $connection->is_connected = true;
        $connection->error_count = 0;
        $connection->last_error = null;
        $connection->save();

        return $connection->refresh();
    }
}

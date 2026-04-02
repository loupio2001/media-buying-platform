<?php

namespace App\Services\PlatformOAuth;

use App\Models\PlatformConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PlatformConnectionTokenRefreshService
{
    /**
     * @return array{status:string,refreshed:bool,reason:string,expires_at:?string,last_error:?string}
     */
    public function refreshIfNeeded(PlatformConnection $connection, bool $force = false): array
    {
        $connection->loadMissing('platform');

        if ($connection->auth_type !== 'oauth2') {
            return [
                'status' => 'skipped',
                'refreshed' => false,
                'reason' => 'non_oauth_connection',
                'expires_at' => optional($connection->token_expires_at)?->toIso8601String(),
                'last_error' => $connection->last_error,
            ];
        }

        if (! $force && $connection->token_expires_at?->isFuture() && $connection->token_expires_at->gt(now()->addMinutes(10))) {
            return [
                'status' => 'skipped',
                'refreshed' => false,
                'reason' => 'token_still_valid',
                'expires_at' => $connection->token_expires_at->toIso8601String(),
                'last_error' => $connection->last_error,
            ];
        }

        try {
            if ($connection->platform?->slug === 'google') {
                return $this->refreshGoogleConnection($connection);
            }

            if ($connection->platform?->slug !== 'meta') {
                throw new RuntimeException('OAuth refresh is currently supported for Meta and Google only.');
            }

            $clientId = (string) config('services.meta_ads.client_id');
            $clientSecret = (string) config('services.meta_ads.client_secret');
            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('META_APP_ID or META_APP_SECRET is not configured.');
            }

            $currentAccessToken = (string) ($connection->access_token ?? '');
            if ($currentAccessToken === '') {
                throw new RuntimeException('Missing current OAuth access token.');
            }

            $response = Http::retry(2, 500)
                ->get('https://graph.facebook.com/v22.0/oauth/access_token', [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'fb_exchange_token' => $currentAccessToken,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Meta refresh call failed: ' . $response->body());
            }

            $payload = $response->json();
            $newAccessToken = (string) Arr::get($payload, 'access_token', '');
            if ($newAccessToken === '') {
                throw new RuntimeException('Meta refresh response missing access_token.');
            }

            $expiresIn = (int) Arr::get($payload, 'expires_in', 0);

            $connection->access_token = $newAccessToken;
            $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
            $connection->is_connected = true;
            $connection->error_count = 0;
            $connection->last_error = null;
            $connection->save();

            return [
                'status' => 'ok',
                'refreshed' => true,
                'reason' => 'meta_token_refreshed',
                'expires_at' => optional($connection->token_expires_at)?->toIso8601String(),
                'last_error' => null,
            ];
        } catch (Throwable $exception) {
            $connection->recordError('Token refresh failed: ' . $exception->getMessage());

            return [
                'status' => 'failed',
                'refreshed' => false,
                'reason' => 'refresh_error',
                'expires_at' => optional($connection->fresh()->token_expires_at)?->toIso8601String(),
                'last_error' => $connection->fresh()->last_error,
            ];
        }
    }

    /**
     * Google Ads access tokens are usually refreshed with the saved refresh token.
     * If no refresh token exists, skip the refresh and let the current access token
     * be used by the collector instead of failing the sync upfront.
     *
     * @return array{status:string,refreshed:bool,reason:string,expires_at:?string,last_error:?string}
     */
    private function refreshGoogleConnection(PlatformConnection $connection): array
    {
        $refreshToken = (string) ($connection->refresh_token ?? '');
        if ($refreshToken === '') {
            return [
                'status' => 'skipped',
                'refreshed' => false,
                'reason' => 'missing_refresh_token',
                'expires_at' => optional($connection->token_expires_at)?->toIso8601String(),
                'last_error' => $connection->last_error,
            ];
        }

        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('GOOGLE_ADS_CLIENT_ID or GOOGLE_ADS_CLIENT_SECRET is not configured.');
        }

        $response = Http::retry(2, 500)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google refresh call failed: ' . $response->body());
        }

        $payload = $response->json();
        $newAccessToken = (string) Arr::get($payload, 'access_token', '');
        if ($newAccessToken === '') {
            throw new RuntimeException('Google refresh response missing access_token.');
        }

        $expiresIn = (int) Arr::get($payload, 'expires_in', 0);

        $connection->access_token = $newAccessToken;
        $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $connection->is_connected = true;
        $connection->error_count = 0;
        $connection->last_error = null;
        $connection->save();

        return [
            'status' => 'ok',
            'refreshed' => true,
            'reason' => 'google_token_refreshed',
            'expires_at' => optional($connection->token_expires_at)?->toIso8601String(),
            'last_error' => null,
        ];
    }
}

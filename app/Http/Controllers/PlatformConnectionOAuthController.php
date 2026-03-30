<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Services\PlatformOAuth\MetaOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class PlatformConnectionOAuthController extends Controller
{
    public function __construct(private MetaOAuthService $metaOAuthService)
    {
    }

    public function redirectToProvider(string $platform, Request $request): RedirectResponse
    {
        if ($platform !== 'meta') {
            throw new NotFoundHttpException('Unsupported platform.');
        }

        $platformModel = Platform::query()->where('slug', 'meta')->firstOrFail();
        if (! $platformModel->api_supported || ! $platformModel->is_active) {
            return redirect()->route('web.platform-connections.index')->with('error', 'Meta is not available for API connections.');
        }

        $state = Str::random(40);
        $request->session()->put('platform_oauth.meta.state', $state);
        $request->session()->put('platform_oauth.meta.user_id', (int) $request->user()->id);

        $redirectUri = (string) config('services.meta_ads.redirect_uri');
        if ($redirectUri === '') {
            $redirectUri = route('web.platform-connections.oauth.callback', ['platform' => 'meta'], true);
        }

        try {
            $authorizeUrl = $this->metaOAuthService->buildAuthorizeUrl($state, $redirectUri);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('web.platform-connections.index')->with('error', $exception->getMessage());
        }

        return redirect()->away($authorizeUrl);
    }

    public function handleProviderCallback(string $platform, Request $request): RedirectResponse
    {
        if ($platform !== 'meta') {
            throw new NotFoundHttpException('Unsupported platform.');
        }

        $expectedState = (string) $request->session()->pull('platform_oauth.meta.state', '');
        $initiatorUserId = (int) $request->session()->pull('platform_oauth.meta.user_id', 0);

        $actualState = (string) $request->query('state', '');
        if ($expectedState === '' || ! hash_equals($expectedState, $actualState) || $initiatorUserId !== (int) $request->user()->id) {
            return redirect()->route('web.platform-connections.index')->with('error', 'Invalid OAuth state, please retry the connection flow.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('web.platform-connections.index')->with('error', 'Meta OAuth callback did not return a code.');
        }

        $redirectUri = (string) config('services.meta_ads.redirect_uri');
        if ($redirectUri === '') {
            $redirectUri = route('web.platform-connections.oauth.callback', ['platform' => 'meta'], true);
        }

        try {
            $platformModel = Platform::query()->where('slug', 'meta')->firstOrFail();
            $tokenPayload = $this->metaOAuthService->exchangeCodeForToken($code, $redirectUri);
            $accountPayload = $this->metaOAuthService->fetchPrimaryAdAccount((string) $tokenPayload['access_token']);

            $connection = $this->metaOAuthService->upsertConnection(
                platformId: (int) $platformModel->id,
                userId: (int) $request->user()->id,
                tokenPayload: $tokenPayload,
                accountPayload: $accountPayload,
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('web.platform-connections.index')->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', 'Meta connection synced successfully (connection #' . $connection->id . ').');
    }
}
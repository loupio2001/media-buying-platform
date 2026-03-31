<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Services\PlatformOAuth\GoogleOAuthService;
use App\Services\PlatformOAuth\MetaOAuthService;
use App\Services\PlatformOAuth\TikTokOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class PlatformConnectionOAuthController extends Controller
{
    /** Supported platform slugs → service class map (resolved via DI). */
    private const SUPPORTED = ['meta', 'google', 'tiktok'];

    public function __construct(
        private MetaOAuthService   $metaOAuthService,
        private GoogleOAuthService $googleOAuthService,
        private TikTokOAuthService $tikTokOAuthService,
    ) {}

    public function redirectToProvider(string $platform, Request $request): RedirectResponse
    {
        $this->assertSupported($platform);

        $platformModel = Platform::query()->where('slug', $platform)->firstOrFail();
        if (! $platformModel->api_supported || ! $platformModel->is_active) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', ucfirst($platform) . ' is not available for API connections.');
        }

        $state = Str::random(40);
        $request->session()->put("platform_oauth.{$platform}.state", $state);
        $request->session()->put("platform_oauth.{$platform}.user_id", (int) $request->user()->id);

        $redirectUri = $this->resolveRedirectUri($platform);

        try {
            $service      = $this->resolveService($platform);
            $authorizeUrl = $service->buildAuthorizeUrl($state, $redirectUri);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()->away($authorizeUrl);
    }

    public function handleProviderCallback(string $platform, Request $request): RedirectResponse
    {
        $this->assertSupported($platform);

        $expectedState    = (string) $request->session()->pull("platform_oauth.{$platform}.state", '');
        $initiatorUserId  = (int) $request->session()->pull("platform_oauth.{$platform}.user_id", 0);

        $actualState = (string) $request->query('state', '');
        if ($expectedState === '' || ! hash_equals($expectedState, $actualState) || $initiatorUserId !== (int) $request->user()->id) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Invalid OAuth state, please retry the connection flow.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', ucfirst($platform) . ' OAuth callback did not return a code.');
        }

        $redirectUri = $this->resolveRedirectUri($platform);

        try {
            $platformModel = Platform::query()->where('slug', $platform)->firstOrFail();
            $service       = $this->resolveService($platform);
            $tokenPayload  = $service->exchangeCodeForToken($code, $redirectUri);
            $accountPayload = $service->fetchPrimaryAdAccount((string) $tokenPayload['access_token']);

            $connection = $service->upsertConnection(
                platformId:     (int) $platformModel->id,
                userId:         (int) $request->user()->id,
                tokenPayload:   $tokenPayload,
                accountPayload: $accountPayload,
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', ucfirst($platform) . ' connection synced successfully (connection #' . $connection->id . ').');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function assertSupported(string $platform): void
    {
        if (! in_array($platform, self::SUPPORTED, true)) {
            throw new NotFoundHttpException('Unsupported OAuth platform: ' . $platform);
        }
    }

    private function resolveService(string $platform): MetaOAuthService|GoogleOAuthService|TikTokOAuthService
    {
        return match ($platform) {
            'meta'   => $this->metaOAuthService,
            'google' => $this->googleOAuthService,
            'tiktok' => $this->tikTokOAuthService,
        };
    }

    private function resolveRedirectUri(string $platform): string
    {
        $configured = (string) config("services.{$platform}_ads.redirect_uri", '');

        return $configured !== ''
            ? $configured
            : route('web.platform-connections.oauth.callback', ['platform' => $platform], true);
    }
}

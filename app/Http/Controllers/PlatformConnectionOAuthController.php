<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Services\PlatformOAuth\GoogleOAuthService;
use App\Services\PlatformOAuth\MetaOAuthService;
use App\Services\PlatformOAuth\TikTokOAuthService;
use Illuminate\Contracts\View\View;
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

            if ($platform === 'google') {
                return $this->handleGoogleCallback($request, $platformModel, $service, $tokenPayload);
            }

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

    public function showGoogleCustomerSelection(string $platform, Request $request): View|RedirectResponse
    {
        $this->assertSupported($platform);

        if ($platform !== 'google') {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Customer selection is only available for Google Ads OAuth.');
        }

        $pending = $this->pullGooglePendingState($request, false);
        if ($pending === null) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Your Google Ads connection session expired. Please restart the OAuth flow.');
        }

        if ((int) ($pending['user_id'] ?? 0) !== (int) $request->user()->id) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Your Google Ads connection session is no longer valid. Please restart the OAuth flow.');
        }

        return view('settings.platform-connections.google-customer-selection', [
            'platform' => $platform,
            'customers' => $pending['customers'],
        ]);
    }

    public function confirmGoogleCustomerSelection(string $platform, Request $request): RedirectResponse
    {
        $this->assertSupported($platform);

        if ($platform !== 'google') {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Customer selection is only available for Google Ads OAuth.');
        }

        $pending = $this->pullGooglePendingState($request, false);
        if ($pending === null) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Your Google Ads connection session expired. Please restart the OAuth flow.');
        }

        if ((int) ($pending['user_id'] ?? 0) !== (int) $request->user()->id) {
            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', 'Your Google Ads connection session is no longer valid. Please restart the OAuth flow.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'string'],
        ]);

        $selectedAccount = collect($pending['customers'])
            ->firstWhere('account_id', $validated['account_id']);

        if (! is_array($selectedAccount)) {
            return redirect()
                ->route('web.platform-connections.oauth.google.select', ['platform' => 'google'])
                ->with('error', 'Please choose one of the accessible Google Ads customer IDs.');
        }

        try {
            $platformModel = Platform::query()->where('slug', $platform)->firstOrFail();
            $service = $this->resolveService($platform);

            $connection = $service->upsertConnection(
                platformId: (int) $platformModel->id,
                userId: (int) $request->user()->id,
                tokenPayload: $pending['token_payload'],
                accountPayload: $selectedAccount,
            );

            $request->session()->forget('platform_oauth.google.pending');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('web.platform-connections.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', 'Google connection synced successfully for customer ' . $selectedAccount['account_id'] . ' (connection #' . $connection->id . ').');
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

    /**
     * @return array{user_id:int,platform_id:int,token_payload:array,customers:array<int,array{account_id:string,account_name:string}>}|null
     */
    private function pullGooglePendingState(Request $request, bool $forget = true): ?array
    {
        $key = 'platform_oauth.google.pending';

        $pending = $forget ? $request->session()->pull($key) : $request->session()->get($key);

        if (! is_array($pending)) {
            return null;
        }

        $tokenPayload = $pending['token_payload'] ?? null;
        $customers = $pending['customers'] ?? null;

        if (! is_array($tokenPayload) || ! is_array($customers) || $customers === []) {
            return null;
        }

        return [
            'user_id' => (int) ($pending['user_id'] ?? 0),
            'platform_id' => (int) ($pending['platform_id'] ?? 0),
            'token_payload' => $tokenPayload,
            'customers' => array_values(array_filter($customers, static fn ($customer): bool => is_array($customer))),
        ];
    }

    private function handleGoogleCallback(Request $request, Platform $platformModel, GoogleOAuthService $service, array $tokenPayload): RedirectResponse
    {
        $customers = $service->listAccessibleAdAccounts((string) $tokenPayload['access_token']);

        if (count($customers) === 1) {
            $connection = $service->upsertConnection(
                platformId: (int) $platformModel->id,
                userId: (int) $request->user()->id,
                tokenPayload: $tokenPayload,
                accountPayload: $customers[0],
            );

            return redirect()
                ->route('web.platform-connections.index')
                ->with('status', 'Google connection synced successfully for customer ' . $customers[0]['account_id'] . ' (connection #' . $connection->id . ').');
        }

        $request->session()->put('platform_oauth.google.pending', [
            'user_id' => (int) $request->user()->id,
            'platform_id' => (int) $platformModel->id,
            'token_payload' => $tokenPayload,
            'customers' => $customers,
        ]);

        return redirect()
            ->route('web.platform-connections.oauth.google.select', ['platform' => 'google'])
            ->with('status', 'Select the Google Ads customer ID to link.');
    }
}
